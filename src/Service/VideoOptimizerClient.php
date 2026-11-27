<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidRequestException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class VideoOptimizerClient
{
    private const DEFAULT_BASE_URL = 'https://api.videooptimizer.eu/api/v1';
    private const DEFAULT_EMBED_BASE_URL = 'https://player.videooptimizer.eu';
    private const PAGE_LIMIT = 100;
    private const MAX_PAGES = 100;
    private const MAX_RETRY_AFTER = 5;
    private const EMBED_CACHE_TTL = 3600;
    private const EMBED_FAILURE_CACHE_TTL = 60;
    private const ADMIN_MAX_DURATION = 30.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfig,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly EmbedAvailability $availability = new EmbedAvailability(),
    ) {
    }

    public function listLibraries(): array
    {
        return $this->requestAllPages('/libraries');
    }

    public function createLibrary(array $payload): array
    {
        return $this->requestData('POST', '/libraries', ['json' => $payload]);
    }

    public function updateLibrary(string $id, array $payload): array
    {
        return $this->requestData('PATCH', '/libraries/' . $this->pathId($id), ['json' => $payload]);
    }

    public function deleteLibrary(string $id): void
    {
        $this->request('DELETE', '/libraries/' . $this->pathId($id));
    }

    /**
     * Requeues every video in a selfhosted/media-managed library for re-encoding with the
     * library's current encoding ladder.
     *
     * @return array{queued: int}
     */
    public function reprocessLibrary(string $id): array
    {
        return $this->requestData('POST', '/libraries/' . $this->pathId($id) . '/reprocess');
    }

    public function listVideos(string $libraryId): array
    {
        return $this->requestAllPages('/libraries/' . $this->pathId($libraryId) . '/videos');
    }

    /**
     * Lists videos across every library (newest first), optionally filtered to one library.
     * Requires the `library:view` permission on the token.
     */
    public function listAllVideos(?string $libraryId = null): array
    {
        $query = ($libraryId !== null && $libraryId !== '') ? ['library_id' => $libraryId] : [];

        return $this->requestAllPages('/videos', $query);
    }

    /**
     * Lists the codecs and resolutions the organization may enable on a library. Returned as-is
     * ({codecs, resolutions}), NOT wrapped in { data } — so use the low-level request() directly.
     */
    public function listEncodings(): array
    {
        return $this->request('GET', '/encodings');
    }

    /**
     * Creates a video from a public https URL (selfhosted libraries only). Returns immediately with
     * status "processing"; poll getVideo() until "ready"/"failed".
     *
     * @param array<string, mixed> $payload expects library_id, source_url and optional title
     */
    public function ingestVideoUrl(array $payload): array
    {
        return $this->requestData('POST', '/videos', ['json' => $payload]);
    }

    public function getVideo(string $uuid): array
    {
        return $this->requestData('GET', '/videos/' . $this->pathId($uuid));
    }

    public function updateVideo(string $uuid, array $payload): array
    {
        $result = $this->requestData('PATCH', '/videos/' . $this->pathId($uuid), ['json' => $payload]);
        $this->forgetEmbed($uuid);

        return $result;
    }

    public function deleteVideo(string $uuid): void
    {
        $this->request('DELETE', '/videos/' . $this->pathId($uuid));
        $this->forgetEmbed($uuid);
    }

    /**
     * Starts a presigned multipart upload. Returns the upload id plus per-part PUT URLs the browser
     * uploads directly to; the token never leaves the server.
     *
     * @param array<string, mixed> $payload expects libraryId, filename, contentType, fileSize
     */
    public function initiateUpload(array $payload): array
    {
        return $this->requestData('POST', '/videos/upload/initiate', ['json' => $payload]);
    }

    /**
     * Finalizes a presigned multipart upload once every part has been PUT to its presigned URL.
     *
     * @param array<string, mixed> $payload expects libraryId, uuid, key, uploadId, parts[] and optional title
     */
    public function completeUpload(array $payload): array
    {
        return $this->requestData('POST', '/videos/upload/complete', ['json' => $payload]);
    }

    /**
     * Public embed payload (theme, sources, poster). No token required. Capped so a slow upstream
     * falls back fast during storefront rendering instead of blocking the response, and never
     * retries on 429 (no sleep() while rendering the storefront). Cached per UUID for 1 hour; a
     * failed lookup is negatively cached for 60s (see below) so an unreachable upstream cannot
     * hammer itself or block storefront rendering on every request.
     */
    public function getEmbed(string $uuid): array
    {
        // The UUID comes from CMS slot config; validate it before it can reach the cache or a URL.
        $this->pathId($uuid);

        // Set only when the callback below actually ran (i.e. this call missed the cache), so we
        // can tell "fresh failure" (propagate the real upstream exception) apart from "served from
        // the negative cache" (synthesize a 503 below) even though both paths return the same
        // sentinel shape from cache->get().
        $freshFailure = null;

        $result = $this->cache->get(
            $this->embedCacheKey($uuid),
            function (ItemInterface $item) use ($uuid, &$freshFailure): array {
                try {
                    $data = $this->requestData('GET', '/embed/' . $this->pathId($uuid), ['max_duration' => 3.0], false, false);
                } catch (\Throwable $e) {
                    // Store the sentinel instead of throwing here - Symfony's CacheInterface::get()
                    // never persists a value when the callback throws, which is exactly what we
                    // don't want for a failure we intend to cache.
                    $freshFailure = $e;
                    $item->expiresAfter(self::EMBED_FAILURE_CACHE_TTL);

                    return ['__scvo_failed' => true, 'message' => $e->getMessage()];
                }

                // No playable sources yet (processing) or at all (failed): cache briefly, so a video
                // that becomes ready shows up within a minute instead of after the full TTL.
                $sources = is_array($data['sources'] ?? null) ? $data['sources'] : [];
                $playable = EmbedSources::hls($sources) !== null || EmbedSources::mp4($sources) !== null;
                $item->expiresAfter($playable ? self::EMBED_CACHE_TTL : self::EMBED_FAILURE_CACHE_TTL);

                return $data;
            }
        );

        if ($freshFailure !== null) {
            $this->availability->markDegraded();

            throw $freshFailure;
        }

        if (($result['__scvo_failed'] ?? false) === true) {
            $this->availability->markDegraded();

            throw new VideoOptimizerApiException(503, 'VideoOptimizer embed temporarily unavailable (cached failure).');
        }

        $sources = is_array($result['sources'] ?? null) ? $result['sources'] : [];
        if (EmbedSources::hls($sources) === null && EmbedSources::mp4($sources) === null) {
            $this->availability->markDegraded();
        }

        return $result;
    }

    /**
     * Invalidates the cached getEmbed() result for a video, so a title/poster/thumbnail change or
     * deletion made through the admin is reflected on the storefront without waiting out the TTL.
     * A no-op if nothing is cached for this UUID (e.g. it was never rendered, or already expired).
     */
    public function forgetEmbed(string $uuid): void
    {
        $this->cache->delete($this->embedCacheKey($uuid));
    }

    public function listThumbnails(string $uuid): array
    {
        return $this->requestData('GET', '/videos/' . $this->pathId($uuid) . '/thumbnails');
    }

    /**
     * Fetches a single thumbnail frame's raw image bytes. Per the current OpenAPI spec the frame
     * URLs are public (no token required), but the admin SPA still proxies through here because
     * it should not fetch cross-origin images with unknown CORS.
     *
     * @return array{content: string, contentType: string}
     */
    public function getThumbnailImage(string $uuid, int $index): array
    {
        $url = $this->baseUrl() . '/videos/' . $this->pathId($uuid) . '/thumbnails/' . $index;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->buildHeaders(true, 'image/*'),
                'max_duration' => self::ADMIN_MAX_DURATION,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw VideoOptimizerApiException::fromResponse($status, $response->getContent(false));
            }

            return [
                'content' => $response->getContent(),
                'contentType' => $response->getHeaders()['content-type'][0] ?? 'image/jpeg',
            ];
        } catch (TransportExceptionInterface $e) {
            throw $this->unreachable('GET', $url, $e);
        }
    }

    public function selectThumbnail(string $uuid, int $index): array
    {
        $result = $this->requestData('POST', '/videos/' . $this->pathId($uuid) . '/thumbnail', ['json' => ['thumbnailIndex' => $index]]);
        $this->forgetEmbed($uuid);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload expects contentType (image/jpeg|png|webp) and fileSize
     *
     * @return array<string, mixed> {key, uploadUrl}
     */
    public function initiatePosterUpload(string $uuid, array $payload): array
    {
        return $this->requestData('POST', '/videos/' . $this->pathId($uuid) . '/poster/initiate', ['json' => $payload]);
    }

    public function completePosterUpload(string $uuid, string $key): array
    {
        $result = $this->requestData('POST', '/videos/' . $this->pathId($uuid) . '/poster/complete', ['json' => ['key' => $key]]);
        $this->forgetEmbed($uuid);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload expects source (custom|thumbnail) and optional thumbnailIndex
     */
    public function selectPoster(string $uuid, array $payload): array
    {
        $result = $this->requestData('POST', '/videos/' . $this->pathId($uuid) . '/poster/select', ['json' => $payload]);
        $this->forgetEmbed($uuid);

        return $result;
    }

    public function deletePoster(string $uuid): void
    {
        $this->request('DELETE', '/videos/' . $this->pathId($uuid) . '/poster');
        $this->forgetEmbed($uuid);
    }

    /**
     * Public host that serves the hosted embed player, used to build storefront iframe/link URLs.
     */
    public function embedBaseUrl(): string
    {
        $base = $this->systemConfig->getString('ScaleVideoOptimizer.config.embedBaseUrl');
        $url = rtrim($base !== '' ? $base : self::DEFAULT_EMBED_BASE_URL, '/');
        $this->assertHttpsUrl($url, 'The VideoOptimizer embed base URL must be an absolute https URL.');

        return $url;
    }

    private function embedCacheKey(string $uuid): string
    {
        // The UUID comes from CMS slot config; rawurlencode() it so it can never smuggle a PSR-6
        // reserved character ("{}()/\@:") into the cache key.
        return 'scalecommerce_vo_embed_' . rawurlencode($uuid);
    }

    /**
     * Fetches every page of a cursor-paginated list endpoint and returns the merged items.
     */
    private function requestAllPages(string $path, array $extraQuery = []): array
    {
        $items = [];
        $cursor = null;
        $previousCursor = null;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $query = $extraQuery + ['limit' => self::PAGE_LIMIT];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $response = $this->request('GET', $path, ['query' => $query]);

            $data = $response['data'] ?? null;
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
            }

            $pagination = $response['pagination'] ?? null;
            if (!is_array($pagination) || ($pagination['has_more'] ?? false) !== true) {
                break;
            }

            $cursor = is_string($pagination['next_cursor'] ?? null) ? $pagination['next_cursor'] : null;
            // Stop if the cursor is empty or stuck, so a misbehaving upstream cannot loop forever.
            if ($cursor === null || $cursor === '' || $cursor === $previousCursor) {
                break;
            }
            $previousCursor = $cursor;
        }

        return $items;
    }

    private function requestData(string $method, string $path, array $options = [], bool $withToken = true, bool $retryOnRateLimit = true): array
    {
        $response = $this->request($method, $path, $options, $withToken, $retryOnRateLimit);
        $payload = $response['data'] ?? null;

        return is_array($payload) ? $payload : [];
    }

    private function request(string $method, string $path, array $options = [], bool $withToken = true, bool $retryOnRateLimit = true): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], $this->buildHeaders($withToken, 'application/json'));
        $options['max_duration'] ??= self::ADMIN_MAX_DURATION;

        $url = $this->baseUrl() . $path;

        try {
            for ($attempt = 0;; ++$attempt) {
                $response = $this->httpClient->request($method, $url, $options);
                $status = $response->getStatusCode();

                // Respect Retry-After once; paginated loops can trip the rate limit. Skipped entirely
                // for calls that opt out (e.g. getEmbed() during storefront rendering), where a
                // blocking sleep() would hold the response open.
                if ($status === 429 && $attempt === 0 && $retryOnRateLimit) {
                    sleep($this->retryAfterSeconds($response));
                    continue;
                }
                break;
            }

            if ($status < 200 || $status >= 300) {
                throw VideoOptimizerApiException::fromResponse($status, $response->getContent(false));
            }

            $content = $response->getContent();
        } catch (TransportExceptionInterface $e) {
            throw $this->unreachable($method, $url, $e);
        }

        // An empty success body (204, DELETE, or any endpoint that answers without content) stays an
        // empty result as before; only a body that is present but not a JSON object is an error.
        if ($content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            // E.g. an HTML maintenance page served with 200 must not be reported as an empty success.
            $this->logger->warning('VideoOptimizer API returned a success status without a JSON body.', [
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'body' => mb_substr($content, 0, 200),
            ]);

            throw VideoOptimizerApiException::badGateway('The VideoOptimizer API returned an invalid response.');
        }

        return $decoded;
    }

    /**
     * Network, TLS, DNS errors and timeouts (incl. max_duration). The transport message contains
     * the full upstream URL, so it is logged and replaced by a neutral message.
     */
    private function unreachable(string $method, string $url, TransportExceptionInterface $e): VideoOptimizerApiException
    {
        $this->logger->warning('VideoOptimizer API could not be reached.', [
            'method' => $method,
            'url' => $url,
            'error' => $e->getMessage(),
        ]);

        return VideoOptimizerApiException::badGateway('The VideoOptimizer API could not be reached or did not respond in time.', $e);
    }

    /**
     * @return list<string> "Name: Value" header lines, the shape Symfony's HttpClient and our
     *                      own request()/getThumbnailImage() calls agree on.
     */
    private function buildHeaders(bool $withToken, string $accept): array
    {
        $headers = [];
        if ($withToken) {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        $headers[] = 'Accept: ' . $accept;

        return $headers;
    }

    private function retryAfterSeconds(ResponseInterface $response): int
    {
        $value = $response->getHeaders(false)['retry-after'][0] ?? null;
        if (!is_string($value) || !ctype_digit($value)) {
            return 1;
        }

        return min((int) $value, self::MAX_RETRY_AFTER);
    }

    private function token(): string
    {
        $token = $this->systemConfig->getString('ScaleVideoOptimizer.config.apiToken');
        if ($token === '') {
            throw new MissingApiTokenException();
        }

        return $token;
    }

    private function baseUrl(): string
    {
        $base = $this->systemConfig->getString('ScaleVideoOptimizer.config.apiBaseUrl');
        $url = rtrim($base !== '' ? $base : self::DEFAULT_BASE_URL, '/');
        $this->assertHttpsUrl($url, 'The VideoOptimizer API base URL must be an absolute https URL.');

        return $url;
    }

    /**
     * Returns an ID for use as a single upstream path segment. Only plain IDs (UUIDs upstream) are
     * allowed: a dot segment would be resolved by HttpClient and re-target the request, and "/",
     * "?" or "#" would change the path or turn it into a query.
     */
    private function pathId(string $id): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $id) !== 1) {
            throw new InvalidRequestException('Invalid VideoOptimizer ID.');
        }

        return $id;
    }

    /**
     * Defence in depth for the configurable base URLs: https only, no userinfo, query or fragment
     * (the request path is appended verbatim), and no localhost or private, loopback, link-local
     * or reserved IP literals, so the token and server-side requests cannot be pointed at
     * internal services. Host names are not resolved here.
     */
    private function assertHttpsUrl(string $url, string $message): void
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new InvalidApiBaseUrlException($message);
        }
        // parse_url() does not normalize scheme case, so "HTTPS://..." must still be accepted.
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            throw new InvalidApiBaseUrlException($message);
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidApiBaseUrlException($message);
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidApiBaseUrlException($message);
        }
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidApiBaseUrlException($message);
        }
    }
}
