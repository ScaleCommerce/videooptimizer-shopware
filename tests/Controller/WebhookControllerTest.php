<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use ScaleCommerce\VideoOptimizer\Controller\WebhookController;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WebhookControllerTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const UUID = 'e7ab89b1-b74f-42fd-b2a9-53bfe1d492a8';

    private const LIBRARY = '15e2662a-e0f3-4c3a-a111-0123456789ab';

    private CacheItemPoolInterface $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    private function controller(VideoOptimizerClient $client, string $secret = self::SECRET): WebhookController
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('getString')->willReturnMap([[WebhookController::SECRET_CONFIG_KEY, null, $secret]]);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://shop.example/api/scalecommerce-vo/webhook');

        return new WebhookController($client, $config, $this->cache, $router);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function delivery(array $body, ?int $time = null, string $secret = self::SECRET, string $deliveryId = 'del-1'): Request
    {
        $raw = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp = (string) ($time ?? time());
        $request = new Request([], [], [], [], [], [], $raw);
        $request->setMethod('POST');
        $request->headers->set('X-VideoOptimizer-Timestamp', $timestamp);
        $request->headers->set('X-VideoOptimizer-Signature', 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret));
        if ($deliveryId !== '') {
            $request->headers->set('X-VideoOptimizer-Delivery', $deliveryId);
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return json_decode((string) $response->getContent(), true);
    }

    public function testVerifyAcceptsAValidSignatureInsideTheWindow(): void
    {
        $signature = 'sha256=' . hash_hmac('sha256', '1000.{"a":1}', 's');

        static::assertTrue(WebhookController::verify('s', '1000', '{"a":1}', $signature, 1000));
        static::assertTrue(WebhookController::verify('s', '1000', '{"a":1}', $signature, 1300));
        static::assertFalse(WebhookController::verify('s', '1000', '{"a":1}', $signature, 1301));
        static::assertFalse(WebhookController::verify('s', '1000', '{"a":1}', $signature, 699));
        static::assertFalse(WebhookController::verify('s', '1000', '{"a":2}', $signature, 1000));
        static::assertFalse(WebhookController::verify('', '1000', '{"a":1}', $signature, 1000));
        static::assertFalse(WebhookController::verify('s', 'abc', '{"a":1}', $signature, 1000));
    }

    public function testWithoutASecretTheEndpointIsDisabled(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('forgetEmbed');

        $response = $this->controller($client, '')->receive($this->delivery(['event' => 'video.ready']));

        static::assertSame(503, $response->getStatusCode());
    }

    public function testAWrongSignatureIsRejected(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('forgetEmbed');

        $response = $this->controller($client)->receive($this->delivery(['event' => 'video.ready', 'data' => ['uuid' => self::UUID]], null, 'other-secret'));

        static::assertSame(401, $response->getStatusCode());
    }

    public function testAnOldDeliveryIsRejectedAsReplay(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('forgetEmbed');

        $response = $this->controller($client)->receive($this->delivery(['event' => 'video.ready', 'data' => ['uuid' => self::UUID]], time() - 301));

        static::assertSame(401, $response->getStatusCode());
    }

    public function testAnOversizedBodyIsRefusedBeforeItIsRead(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $request = $this->delivery(['event' => 'video.ready']);
        $request->headers->set('Content-Length', (string) (WebhookController::MAX_BODY + 1));

        static::assertSame(413, $this->controller($client)->receive($request)->getStatusCode());
    }

    public function testAnOversizedBodyWithoutContentLengthIsRefused(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $raw = str_repeat('a', WebhookController::MAX_BODY + 1);
        $request = new Request([], [], [], [], [], [], $raw);
        $request->setMethod('POST');

        static::assertSame(413, $this->controller($client)->receive($request)->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function videoEvents(): iterable
    {
        yield 'ready' => ['video.ready'];
        yield 'failed' => ['video.failed'];
        yield 'updated' => ['video.updated'];
        yield 'deleted' => ['video.deleted'];
    }

    #[DataProvider('videoEvents')]
    public function testVideoEventsDropTheEmbedAndLibraryPages(string $event): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('forgetEmbed')->with(self::UUID);
        $client->expects(static::once())->method('forgetLibrary')->with(self::LIBRARY);

        $response = $this->controller($client)->receive($this->delivery(['event' => $event, 'data' => ['uuid' => self::UUID, 'library_id' => self::LIBRARY]]));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['received' => true, 'flushed' => true], $this->json($response));
        static::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testARetryOfTheSameDeliveryIsNotProcessedTwice(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('forgetEmbed');
        $controller = $this->controller($client);
        $request = $this->delivery(['event' => 'video.ready', 'data' => ['uuid' => self::UUID]], null, self::SECRET, 'del-9');

        static::assertSame(['received' => true, 'flushed' => true], $this->json($controller->receive($request)));
        static::assertSame(['received' => true, 'duplicate' => true], $this->json($controller->receive($request)));
    }

    /**
     * Every webhook is its own PHP request: the claim must survive in a pool that is shared
     * between them, here a filesystem pool read by two separate controller instances.
     */
    public function testAReplayIsADuplicateAcrossRequests(): void
    {
        $dir = sys_get_temp_dir() . '/scvo-wh-' . bin2hex(random_bytes(4));
        $request = $this->delivery(['event' => 'video.ready', 'data' => ['uuid' => self::UUID]]);
        $processed = 0;
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('forgetEmbed')->willReturnCallback(static function () use (&$processed): void {
            ++$processed;
        });

        try {
            $this->cache = new FilesystemAdapter('scvo_test', 0, $dir);
            $first = $this->controller($client)->receive($request);
            $this->cache = new FilesystemAdapter('scvo_test', 0, $dir);
            $second = $this->controller($client)->receive($request);
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($dir);
        }

        static::assertSame(['received' => true, 'flushed' => true], $this->json($first));
        static::assertSame(['received' => true, 'duplicate' => true], $this->json($second));
        static::assertSame(1, $processed);
    }

    public function testAReplayWithAnotherDeliveryIdIsStillADuplicate(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('forgetEmbed');
        $controller = $this->controller($client);
        $time = time();
        $body = ['event' => 'video.ready', 'data' => ['uuid' => self::UUID]];

        $controller->receive($this->delivery($body, $time, self::SECRET, 'del-a'));
        $replay = $this->delivery($body, $time, self::SECRET, 'del-b');

        static::assertSame(['received' => true, 'duplicate' => true], $this->json($controller->receive($replay)));
    }

    public function testAFailedInvalidationIsRetried(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::exactly(2))->method('forgetEmbed')
            ->willReturnCallback(static function (): void {
                static $calls = 0;
                if (++$calls === 1) {
                    throw new \RuntimeException('cache down');
                }
            });
        $controller = $this->controller($client);
        $request = $this->delivery(['event' => 'video.ready', 'data' => ['uuid' => self::UUID]]);

        static::assertSame(500, $controller->receive($request)->getStatusCode());
        static::assertSame(200, $controller->receive($request)->getStatusCode());
    }

    public function testTheTestEventAndUnknownEventsDoNothing(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('forgetEmbed');
        $client->expects(static::never())->method('forgetLibrary');
        $controller = $this->controller($client);

        static::assertSame(['ok' => true], $this->json($controller->receive($this->delivery(['event' => 'webhook.test'], null, self::SECRET, 'd1'))));
        static::assertSame(['received' => true, 'flushed' => false], $this->json($controller->receive($this->delivery(['event' => 'library.created', 'data' => ['uuid' => self::UUID]], null, self::SECRET, 'd2'))));
    }

    public function testUnsafeIdsInASignedPayloadAreIgnored(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('forgetEmbed');
        $client->expects(static::never())->method('forgetLibrary');

        $response = $this->controller($client)->receive($this->delivery(['event' => 'video.updated', 'data' => ['uuid' => '../x', 'library_id' => 'a/b']]));

        static::assertSame(['received' => true, 'flushed' => false], $this->json($response));
    }

    public function testInfoReportsUrlSecretAndLastDelivery(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $controller = $this->controller($client);

        $before = $this->json($controller->info())['data'];
        static::assertSame('https://shop.example/api/scalecommerce-vo/webhook', $before['url']);
        static::assertTrue($before['configured']);
        static::assertNull($before['lastDelivery']);

        $controller->receive($this->delivery(['event' => 'webhook.test']));
        static::assertSame('webhook.test', $this->json($controller->info())['data']['lastDelivery']['event']);
        static::assertFalse($this->json($this->controller($client, '')->info())['data']['configured']);
    }

    public function testRoutesHaveTheRightScopesAndPrivileges(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(WebhookController::class, 'attribute');

        $receive = $routes->get(WebhookController::ROUTE);
        // An API route without authentication (the signature authenticates): no sales channel, no
        // Storefront, no domain mapping involved.
        static::assertSame('/api/scalecommerce-vo/webhook', $receive->getPath());
        static::assertSame(['api'], $receive->getDefault('_routeScope'));
        static::assertFalse($receive->getDefault('auth_required'));
        static::assertSame(['POST'], $receive->getMethods());
        static::assertNull($receive->getDefault('_acl'));

        $info = $routes->get('api.action.scalecommerce-vo.webhook.info');
        static::assertSame(['api'], $info->getDefault('_routeScope'));
        static::assertSame(['scalecommerce_vo:read'], $info->getDefault('_acl'));
    }
}
