<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Receives VideoOptimizer webhooks (configured in the VideoOptimizer app under Integrations >
 * Webhooks) and drops the cached embeds and the cached pages of the affected video.
 *
 * Signature: `X-VideoOptimizer-Signature: sha256=hex(HMAC-SHA256(secret, "{timestamp}.{rawBody}"))`
 * with the Unix time in `X-VideoOptimizer-Timestamp`. Deliveries older (or newer) than five
 * minutes are rejected (replay protection), and a delivery that was already processed is
 * acknowledged without being processed again.
 */
class WebhookController
{
    public const ROUTE = 'api.scalecommerce-vo.webhook';

    public const SECRET_CONFIG_KEY = 'ScaleVideoOptimizer.config.webhookSecret';

    public const TOLERANCE = 300;

    public const MAX_BODY = 1048576;

    private const DEDUPE_LIFETIME = 86400;

    private const LAST_DELIVERY_KEY = 'scalecommerce_vo_webhook_last';

    // VideoOptimizer sends video.ready, video.failed and webhook.test; the others are handled too
    // (harmless: they only drop caches) in case the API starts to send them.
    private const VIDEO_EVENTS = ['video.ready', 'video.failed', 'video.updated', 'video.deleted'];

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly SystemConfigService $systemConfig,
        private readonly CacheItemPoolInterface $cache,
        private readonly UrlGeneratorInterface $router,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Public API route, authenticated by the signature instead of a user. An API route (not a
     * storefront one) so the URL is the same for every sales channel domain, with or without a
     * path, and for shops without the Storefront.
     */
    #[Route(path: '/api/scalecommerce-vo/webhook', name: self::ROUTE, methods: ['POST'], defaults: ['_routeScope' => ['api'], 'auth_required' => false])]
    public function receive(Request $request): JsonResponse
    {
        $secret = trim($this->systemConfig->getString(self::SECRET_CONFIG_KEY));
        if ($secret === '') {
            return $this->json(['error' => 'Webhook secret not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Checked before the body is read; the signature cannot be verified without it.
        $declared = $request->headers->get('Content-Length');
        if (is_string($declared) && ctype_digit($declared) && (int) $declared > self::MAX_BODY) {
            return $this->json(['error' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        $raw = (string) $request->getContent();
        if (strlen($raw) > self::MAX_BODY) {
            return $this->json(['error' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $timestamp = (string) $request->headers->get('X-VideoOptimizer-Timestamp', '');
        $signature = (string) $request->headers->get('X-VideoOptimizer-Signature', '');
        if (!self::verify($secret, $timestamp, $raw, $signature, time())) {
            // Anyone can POST here: notice, not warning, so this cannot flood the warning log.
            $this->logger->notice('VideoOptimizer webhook rejected: invalid signature or timestamp.');

            return $this->json(['error' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        // The signature claim comes first: the delivery id header is unsigned, so it only counts
        // for a request whose timestamp/signature is new (a retry); a captured request replayed
        // inside the window with another delivery id is recognised by its signature.
        // `X-VideoOptimizer-Delivery` is stable across the retries (which carry fresh timestamps).
        $delivery = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($request->headers->get('X-VideoOptimizer-Delivery') ?? $request->headers->get('X-VideoOptimizer-Delivery-Id', '')));
        $dedupeKeys = ['scalecommerce_vo_wh_sig_' . hash('sha256', $timestamp . '.' . $signature)];
        if ($delivery !== '') {
            $dedupeKeys[] = 'scalecommerce_vo_wh_' . hash('sha256', $delivery);
        }
        foreach ($dedupeKeys as $key) {
            if ($this->cache->getItem($key)->isHit()) {
                return $this->json(['duplicate' => true]);
            }
        }

        $payload = json_decode($raw, true);
        $event = is_array($payload) && is_string($payload['event'] ?? null) ? $payload['event'] : '';
        $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $flushed = false;
        if (in_array($event, self::VIDEO_EVENTS, true)) {
            try {
                $flushed = $this->invalidate($data);
            } catch (\Throwable $e) {
                // Not marked as delivered, so the sender's retry is processed.
                $this->logger->warning('VideoOptimizer webhook could not invalidate the caches.', ['event' => $event, 'error' => $e->getMessage()]);

                return $this->json(['error' => 'Processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        foreach ($dedupeKeys as $key) {
            $item = $this->cache->getItem($key);
            $item->set(1)->expiresAfter(self::DEDUPE_LIFETIME);
            $this->cache->save($item);
        }
        $this->rememberDelivery($event);

        $this->logger->info('VideoOptimizer webhook processed.', ['event' => $event !== '' ? $event : 'unknown', 'delivery' => $delivery, 'flushed' => $flushed]);

        return $this->json($event === 'webhook.test' ? ['ok' => true] : ['received' => true, 'flushed' => $flushed]);
    }

    /**
     * The URL to enter in the VideoOptimizer app and whether the secret is set, for the module.
     */
    #[Route(path: '/api/_action/scalecommerce-vo/webhook', name: 'api.action.scalecommerce-vo.webhook.info', methods: ['GET'], defaults: ['_routeScope' => ['api'], '_acl' => ['scalecommerce_vo:read']])]
    public function info(): JsonResponse
    {
        $last = $this->cache->getItem(self::LAST_DELIVERY_KEY);

        return new JsonResponse(['data' => [
            'url' => $this->router->generate(self::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL),
            'configured' => trim($this->systemConfig->getString(self::SECRET_CONFIG_KEY)) !== '',
            'lastDelivery' => $last->isHit() && is_array($last->get()) ? $last->get() : null,
        ]]);
    }

    public static function verify(string $secret, string $timestamp, string $rawBody, string $signature, int $now): bool
    {
        if ($secret === '' || $timestamp === '' || !ctype_digit($timestamp) || abs($now - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret), $signature);
    }

    /**
     * @param array<string, mixed> $data the video object of the event
     */
    private function invalidate(array $data): bool
    {
        $flushed = false;
        $uuid = $data['uuid'] ?? null;
        if (is_string($uuid) && preg_match('/^[A-Za-z0-9_-]+$/D', $uuid) === 1) {
            // Cached embed plus the pages that show this video (cache tag).
            $this->client->forgetEmbed($uuid);
            $flushed = true;
        }
        // Lists of the library (video grid, new or removed videos) are tagged per library.
        $libraryId = $data['library_id'] ?? null;
        if (is_string($libraryId) && preg_match('/^[A-Za-z0-9_-]+$/D', $libraryId) === 1) {
            $this->client->forgetLibrary($libraryId);
            $flushed = true;
        }

        return $flushed;
    }

    private function rememberDelivery(string $event): void
    {
        $item = $this->cache->getItem(self::LAST_DELIVERY_KEY);
        $item->set(['event' => $event !== '' ? mb_substr($event, 0, 40) : 'unknown', 'time' => time()]);
        $this->cache->save($item);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($body, $status, ['Cache-Control' => 'no-store']);
    }
}
