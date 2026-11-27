<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidRequestException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use ScaleCommerce\VideoOptimizer\Service\PluginUserAgent;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VideoOptimizerClientTest extends TestCase
{
    private function config(
        string $token = 'vp_test',
        string $baseUrl = 'https://api.videooptimizer.eu/api/v1',
        string $embedBaseUrl = 'https://videooptimizer.eu',
    ): SystemConfigService {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('getString')->willReturnMap([
            ['ScaleVideoOptimizer.config.apiToken', null, $token],
            ['ScaleVideoOptimizer.config.apiBaseUrl', null, $baseUrl],
            ['ScaleVideoOptimizer.config.embedBaseUrl', null, $embedBaseUrl],
        ]);
        return $config;
    }

    /**
     * Builds a client with a fresh ArrayAdapter cache by default so tests don't leak cached
     * getEmbed() results into each other; pass one explicitly to assert on cache behavior.
     */
    private function client(HttpClientInterface $http, ?SystemConfigService $config = null, ?CacheInterface $cache = null): VideoOptimizerClient
    {
        return new VideoOptimizerClient($http, $config ?? $this->config(), $cache ?? new ArrayAdapter());
    }

    public function testListLibrariesSendsBearerTokenAndUnwrapsData(): void
    {
        $capturedUrl = null;
        $capturedAuth = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedAuth): MockResponse {
            $capturedUrl = $url;
            foreach ($options['headers'] ?? [] as $header) {
                if (str_starts_with((string) $header, 'Authorization:')) {
                    $capturedAuth = $header;
                }
            }
            return new MockResponse(json_encode(['data' => [['id' => 'lib-1', 'name' => 'Demo']]]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->listLibraries();

        static::assertStringContainsString('https://api.videooptimizer.eu/api/v1/libraries', $capturedUrl);
        static::assertStringContainsString('limit=100', $capturedUrl);
        static::assertSame('Authorization: Bearer vp_test', $capturedAuth);
        static::assertSame([['id' => 'lib-1', 'name' => 'Demo']], $result);
    }

    public function testListMergesAllCursorPages(): void
    {
        $urls = [];
        $responses = [
            new MockResponse(json_encode([
                'data' => [['id' => 'lib-1']],
                'pagination' => ['has_more' => true, 'next_cursor' => 'c2'],
            ])),
            new MockResponse(json_encode([
                'data' => [['id' => 'lib-2']],
                'pagination' => ['has_more' => false, 'next_cursor' => null],
            ])),
        ];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$urls, &$responses): MockResponse {
            $urls[] = $url;
            return array_shift($responses);
        });

        $client = $this->client($http, $this->config());
        $result = $client->listLibraries();

        static::assertSame([['id' => 'lib-1'], ['id' => 'lib-2']], $result);
        static::assertCount(2, $urls);
        static::assertStringContainsString('cursor=c2', $urls[1]);
    }

    public function testRetriesOnceAfter429(): void
    {
        $count = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$count): MockResponse {
            ++$count;
            if ($count === 1) {
                return new MockResponse(json_encode(['statusMessage' => 'slow down']), [
                    'http_code' => 429,
                    'response_headers' => ['retry-after' => '0'],
                ]);
            }
            return new MockResponse(json_encode(['data' => [['id' => 'lib-1']]]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->listLibraries();

        static::assertSame(2, $count);
        static::assertSame([['id' => 'lib-1']], $result);
    }

    public function testMissingTokenThrows(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(''));
        $this->expectException(MissingApiTokenException::class);
        $client->listLibraries();
    }

    public function testApiErrorThrowsWithStatusCode(): void
    {
        $http = new MockHttpClient(new MockResponse(
            json_encode(['statusCode' => 401, 'message' => 'Unauthorized']),
            ['http_code' => 401]
        ));
        $client = $this->client($http, $this->config());

        try {
            $client->listLibraries();
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(401, $e->getStatusCode());
            static::assertStringContainsString('Unauthorized', $e->getMessage());
        }
    }

    public function testErrorFallsBackToStatusMessage(): void
    {
        $http = new MockHttpClient(new MockResponse(
            json_encode(['statusCode' => 403, 'statusMessage' => 'Forbidden here']),
            ['http_code' => 403]
        ));
        $client = $this->client($http, $this->config());

        try {
            $client->listLibraries();
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(403, $e->getStatusCode());
            static::assertStringContainsString('Forbidden here', $e->getMessage());
        }
    }

    public function testGetEmbedDoesNotRequireToken(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'sources' => []]])));
        $client = $this->client($http, $this->config(''));
        $result = $client->getEmbed('v1');
        static::assertSame('v1', $result['uuid']);
    }

    public function testInitiateUploadPostsPayloadAndUnwrapsData(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];
            return new MockResponse(json_encode(['data' => [
                'uuid' => 'v1', 'key' => 'k', 'uploadId' => 'u1', 'partSize' => 5242880, 'partCount' => 1,
                'parts' => [['partNumber' => 1, 'url' => 'https://storage/put/1']],
            ]]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->initiateUpload(['libraryId' => 'lib-1', 'filename' => 'a.mp4', 'contentType' => 'video/mp4', 'fileSize' => 10]);

        static::assertSame('POST', $captured['method']);
        static::assertStringEndsWith('/videos/upload/initiate', $captured['url']);
        static::assertSame(json_encode(['libraryId' => 'lib-1', 'filename' => 'a.mp4', 'contentType' => 'video/mp4', 'fileSize' => 10]), $captured['body']);
        static::assertSame('u1', $result['uploadId']);
        static::assertSame([['partNumber' => 1, 'url' => 'https://storage/put/1']], $result['parts']);
    }

    public function testCompleteUploadPostsParts(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'body' => $options['body'] ?? null];
            return new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'status' => 'processing']]));
        });

        $client = $this->client($http, $this->config());
        $payload = ['libraryId' => 'lib-1', 'uuid' => 'v1', 'key' => 'k', 'uploadId' => 'u1', 'parts' => [['partNumber' => 1, 'etag' => '"abc"']]];
        $result = $client->completeUpload($payload);

        static::assertStringEndsWith('/videos/upload/complete', $captured['url']);
        // Compare decoded payloads, not raw JSON strings: Symfony's HttpClientTrait encodes the
        // 'json' option with JSON_HEX_QUOT et al., so a literal quote in the ETag value (e.g.
        // '"abc"') is escaped differently on the wire than plain json_encode() would produce.
        static::assertSame($payload, json_decode((string) $captured['body'], true));
        static::assertSame('processing', $result['status']);
    }

    public function testListAllVideosFiltersByLibrary(): void
    {
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;
            return new MockResponse(json_encode(['data' => [['uuid' => 'v1', 'library_id' => 'lib-1']]]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->listAllVideos('lib-1');

        static::assertStringContainsString('/videos', $capturedUrl);
        static::assertStringContainsString('library_id=lib-1', $capturedUrl);
        static::assertSame([['uuid' => 'v1', 'library_id' => 'lib-1']], $result);
    }

    public function testCreateLibrarySendsJsonPayload(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedOptions = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedOptions): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedOptions = $options;
            return new MockResponse(json_encode(['data' => ['id' => 'lib-9', 'name' => 'Demo']]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->createLibrary(['name' => 'Demo']);

        static::assertSame('POST', $capturedMethod);
        static::assertStringEndsWith('/libraries', $capturedUrl);
        // MockHttpClient normalizes the 'json' option into a 'body' string before the
        // request factory is invoked, so we assert on the encoded JSON body instead.
        static::assertSame(json_encode(['name' => 'Demo']), $capturedOptions['body'] ?? null);
        static::assertSame(['id' => 'lib-9', 'name' => 'Demo'], $result);
    }

    public function testListEncodingsReturnsUnwrappedPayloadWithToken(): void
    {
        $capturedAuth = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedAuth): MockResponse {
            foreach ($options['headers'] ?? [] as $header) {
                if (str_starts_with((string) $header, 'Authorization:')) {
                    $capturedAuth = $header;
                }
            }
            return new MockResponse(json_encode([
                'codecs' => [['key' => 'h264', 'label' => 'H.264', 'access' => 'included', 'available' => true]],
                'resolutions' => [['key' => '1080p', 'label' => '1080p', 'access' => 'included', 'available' => true]],
            ]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->listEncodings();

        static::assertSame('Authorization: Bearer vp_test', $capturedAuth);
        static::assertSame('h264', $result['codecs'][0]['key']);
        static::assertSame('1080p', $result['resolutions'][0]['key']);
    }

    public function testListThumbnailsUnwrapsData(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'data' => ['thumbnails' => [['index' => 0, 'url' => 'https://api/x/thumbnails/0']]],
        ])));
        $client = $this->client($http, $this->config());
        $result = $client->listThumbnails('vid-1');
        static::assertSame(0, $result['thumbnails'][0]['index']);
    }

    public function testSelectThumbnailPostsIndex(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];
            return new MockResponse(json_encode(['data' => ['poster' => ['source' => 'thumbnail']]]));
        });
        $client = $this->client($http, $this->config());
        $client->selectThumbnail('vid-1', 3);
        static::assertSame('POST', $captured['method']);
        static::assertStringEndsWith('/videos/vid-1/thumbnail', $captured['url']);
        static::assertSame(json_encode(['thumbnailIndex' => 3]), $captured['body']);
    }

    public function testPosterInitiateCompleteSelectDelete(): void
    {
        $urls = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$urls): MockResponse {
            $urls[] = "$method $url" . (isset($options['body']) ? ' ' . $options['body'] : '');
            return new MockResponse(json_encode(['data' => ['key' => 'k', 'uploadUrl' => 'https://s3/put']]));
        });
        $client = $this->client($http, $this->config());
        $init = $client->initiatePosterUpload('vid-1', ['contentType' => 'image/png', 'fileSize' => 10]);
        static::assertSame('k', $init['key']);
        $client->completePosterUpload('vid-1', 'k');
        $client->selectPoster('vid-1', ['source' => 'custom']);
        $client->deletePoster('vid-1');
        static::assertStringContainsString('POST https://api.videooptimizer.eu/api/v1/videos/vid-1/poster/initiate', $urls[0]);
        static::assertStringContainsString('POST https://api.videooptimizer.eu/api/v1/videos/vid-1/poster/complete {"key":"k"}', $urls[1]);
        static::assertStringContainsString('POST https://api.videooptimizer.eu/api/v1/videos/vid-1/poster/select {"source":"custom"}', $urls[2]);
        static::assertStringContainsString('DELETE https://api.videooptimizer.eu/api/v1/videos/vid-1/poster', $urls[3]);
    }

    public function testGetThumbnailImageReturnsRawBytes(): void
    {
        $http = new MockHttpClient(new MockResponse('IMG-BYTES', ['response_headers' => ['content-type' => 'image/jpeg']]));
        $client = $this->client($http, $this->config());
        $image = $client->getThumbnailImage('vid-1', 2);
        static::assertSame('IMG-BYTES', $image['content']);
        static::assertSame('image/jpeg', $image['contentType']);
    }

    public function testGetThumbnailImageSendsListStyleHeaders(): void
    {
        $capturedOptions = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;
            return new MockResponse('IMG', ['response_headers' => ['content-type' => 'image/jpeg']]);
        });

        $client = $this->client($http, $this->config());
        $client->getThumbnailImage('vid-1', 2);

        // Same list-of-"Name: Value"-strings shape as request() uses, not a mix of list and
        // assoc keys.
        static::assertSame(
            ['Authorization: Bearer vp_test', 'Accept: image/*', 'User-Agent: ' . (new PluginUserAgent())->value()],
            $capturedOptions['headers'],
        );
    }

    /**
     * VideoOptimizer shows which plugin versions an organization runs, so every request - with or
     * without token - identifies the plugin.
     */
    public function testEveryRequestSendsThePluginUserAgent(): void
    {
        $userAgents = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$userAgents): MockResponse {
            $userAgents[parse_url($url, \PHP_URL_PATH)] = $options['normalized_headers']['user-agent'] ?? null;

            return str_contains($url, '/thumbnails/')
                ? new MockResponse('IMG', ['response_headers' => ['content-type' => 'image/jpeg']])
                : new MockResponse(json_encode(['data' => ['sources' => [['type' => 'hls', 'src' => 'https://cdn/x.m3u8']]]]));
        });
        $userAgent = new PluginUserAgent('6.7.16.2', 'https://shop.example.com', '1.0.3');
        $client = new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), null, $userAgent);

        $client->listLibraries();
        $client->getEmbed('vid-1');
        $client->getThumbnailImage('vid-1', 2);

        $expected = ['User-Agent: VideoOptimizer-Shopware/1.0.3 (Shopware 6.7.16.2; PHP ' . \PHP_VERSION . '; +https://shop.example.com)'];
        static::assertSame([
            '/api/v1/libraries' => $expected,
            '/api/v1/embed/vid-1' => $expected,
            '/api/v1/videos/vid-1/thumbnails/2' => $expected,
        ], $userAgents);
    }

    public function testNonHttpsApiBaseUrlThrows(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(baseUrl: 'http://api.videooptimizer.eu/api/v1'));
        $this->expectException(InvalidApiBaseUrlException::class);
        $client->listLibraries();
    }

    public function testHttpsApiBaseUrlTrimsTrailingSlash(): void
    {
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;
            return new MockResponse(json_encode(['data' => []]));
        });

        $client = $this->client($http, $this->config(baseUrl: 'https://api.example/api/v1/'));
        $client->listLibraries();

        static::assertStringStartsWith('https://api.example/api/v1/libraries', $capturedUrl);
    }

    public function testEmbedBaseUrlReadsConfigAndTrimsTrailingSlash(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(embedBaseUrl: 'https://embed.example/'));
        static::assertSame('https://embed.example', $client->embedBaseUrl());
    }

    public function testEmbedBaseUrlFallsBackToDefault(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(embedBaseUrl: ''));
        static::assertSame('https://player.videooptimizer.eu', $client->embedBaseUrl());
    }

    public function testNonHttpsEmbedBaseUrlThrows(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(embedBaseUrl: 'http://embed.example'));
        $this->expectException(InvalidApiBaseUrlException::class);
        $client->embedBaseUrl();
    }

    public function testGetEmbedCachesResultPerUuid(): void
    {
        $count = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$count): MockResponse {
            ++$count;
            return new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'sources' => []]]));
        });
        $client = $this->client($http, $this->config());

        $first = $client->getEmbed('v1');
        $second = $client->getEmbed('v1');

        static::assertSame(1, $count);
        static::assertSame($first, $second);
    }

    public function testGetEmbedCachesFailedLookupsForSixtySeconds(): void
    {
        $count = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$count): MockResponse {
            ++$count;
            return new MockResponse(json_encode(['statusMessage' => 'Upstream exploded']), ['http_code' => 500]);
        });
        $client = $this->client($http, $this->config());

        try {
            $client->getEmbed('v1');
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            // First failure propagates the real upstream status/message.
            static::assertSame(500, $e->getStatusCode());
            static::assertStringContainsString('Upstream exploded', $e->getMessage());
        }

        try {
            $client->getEmbed('v1');
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            // Second call within the 60s negative-cache TTL is served from cache, not upstream.
            static::assertSame(503, $e->getStatusCode());
            static::assertStringContainsString('cached failure', $e->getMessage());
        }

        static::assertSame(1, $count);
    }

    public function testForgetEmbedClearsNegativeCacheEntry(): void
    {
        $count = 0;
        $responses = [
            new MockResponse('', ['http_code' => 500]),
            new MockResponse(json_encode(['data' => ['uuid' => 'v1']])),
        ];
        $http = new MockHttpClient(function () use (&$count, &$responses): MockResponse {
            ++$count;
            return array_shift($responses);
        });
        $client = $this->client($http, $this->config());

        try {
            $client->getEmbed('v1');
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException) {
        }

        // Admin fixes the poster (or the outage clears) and forgets the cached embed - including
        // the negative cache entry, not just a successful one.
        $client->forgetEmbed('v1');
        $result = $client->getEmbed('v1');

        static::assertSame('v1', $result['uuid']);
        static::assertSame(2, $count);
    }

    public function testForgetEmbedOnNeverCachedUuidIsNoop(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config());

        $client->forgetEmbed('never-cached');

        // No exception, and nothing to assert on the wire - deleting a missing cache entry is a no-op.
        $this->addToAssertionCount(1);
    }

    #[DataProvider('embedInvalidatingMutations')]
    public function testMutatingClientMethodInvalidatesTheEmbedCache(callable $mutate, array $responses): void
    {
        $embedResponses = [
            new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'call' => 1]])),
            new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'call' => 2]])),
        ];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$embedResponses, &$responses): MockResponse {
            if (str_contains($url, '/embed/')) {
                return array_shift($embedResponses);
            }
            return array_shift($responses);
        });
        $client = $this->client($http, $this->config());

        $first = $client->getEmbed('v1');
        $mutate($client);
        $second = $client->getEmbed('v1');

        static::assertSame(1, $first['call']);
        static::assertSame(2, $second['call']);
    }

    public static function embedInvalidatingMutations(): iterable
    {
        yield 'updateVideo' => [
            fn (VideoOptimizerClient $client) => $client->updateVideo('v1', ['title' => 'New title']),
            [new MockResponse(json_encode(['data' => ['uuid' => 'v1']]))],
        ];
        yield 'deleteVideo' => [
            fn (VideoOptimizerClient $client) => $client->deleteVideo('v1'),
            [new MockResponse('')],
        ];
        yield 'selectThumbnail' => [
            fn (VideoOptimizerClient $client) => $client->selectThumbnail('v1', 2),
            [new MockResponse(json_encode(['data' => ['poster' => ['source' => 'thumbnail']]]))],
        ];
        yield 'completePosterUpload' => [
            fn (VideoOptimizerClient $client) => $client->completePosterUpload('v1', 'k'),
            [new MockResponse(json_encode(['data' => ['poster' => ['source' => 'custom']]]))],
        ];
        yield 'selectPoster' => [
            fn (VideoOptimizerClient $client) => $client->selectPoster('v1', ['source' => 'custom']),
            [new MockResponse(json_encode(['data' => ['poster' => ['source' => 'custom']]]))],
        ];
        yield 'deletePoster' => [
            fn (VideoOptimizerClient $client) => $client->deletePoster('v1'),
            [new MockResponse('')],
        ];
    }

    public function testGetEmbedDoesNotRetryOn429(): void
    {
        $count = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$count): MockResponse {
            ++$count;
            return new MockResponse(json_encode(['statusMessage' => 'slow down']), [
                'http_code' => 429,
                'response_headers' => ['retry-after' => '0'],
            ]);
        });
        $client = $this->client($http, $this->config());

        try {
            $client->getEmbed('v1');
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(429, $e->getStatusCode());
        }

        // No sleep()-based retry during storefront rendering: exactly one request.
        static::assertSame(1, $count);
    }

    public function testGetEmbedCapsMaxDurationAtThreeSeconds(): void
    {
        $capturedOptions = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;
            return new MockResponse(json_encode(['data' => ['uuid' => 'v1']]));
        });
        $client = $this->client($http, $this->config());
        $client->getEmbed('v1');

        static::assertSame(3.0, $capturedOptions['max_duration']);
    }

    public function testAdminCallsDefaultToThirtySecondMaxDuration(): void
    {
        $capturedOptions = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;
            return new MockResponse(json_encode(['data' => []]));
        });
        $client = $this->client($http, $this->config());
        $client->listLibraries();

        static::assertSame(30.0, $capturedOptions['max_duration']);
    }

    public function testGetThumbnailImageDefaultsToThirtySecondMaxDuration(): void
    {
        $capturedOptions = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;
            return new MockResponse('IMG', ['response_headers' => ['content-type' => 'image/jpeg']]);
        });
        $client = $this->client($http, $this->config());
        $client->getThumbnailImage('vid-1', 2);

        static::assertSame(30.0, $capturedOptions['max_duration']);
    }

    public function testUppercaseHttpsSchemeIsAcceptedForApiBaseUrl(): void
    {
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;
            return new MockResponse(json_encode(['data' => []]));
        });

        // parse_url() does not normalize scheme case; the https check must not be case-sensitive.
        $client = $this->client($http, $this->config(baseUrl: 'HTTPS://api.videooptimizer.eu/api/v1'));
        $client->listLibraries();

        static::assertStringContainsString('/libraries', $capturedUrl);
    }

    public function testUppercaseHttpsSchemeIsAcceptedForEmbedBaseUrl(): void
    {
        $client = $this->client(new MockHttpClient(), $this->config(embedBaseUrl: 'HTTPS://embed.example'));
        static::assertSame('HTTPS://embed.example', $client->embedBaseUrl());
    }

    /**
     * @return iterable<string, array{callable(VideoOptimizerClient): mixed}>
     */
    public static function invalidPathIdCalls(): iterable
    {
        yield 'update library ..' => [fn (VideoOptimizerClient $c) => $c->updateLibrary('..', ['name' => 'x'])];
        yield 'delete library ..' => [fn (VideoOptimizerClient $c) => $c->deleteLibrary('..')];
        yield 'reprocess library .' => [fn (VideoOptimizerClient $c) => $c->reprocessLibrary('.')];
        yield 'list videos ..' => [fn (VideoOptimizerClient $c) => $c->listVideos('..')];
        yield 'get video ../x' => [fn (VideoOptimizerClient $c) => $c->getVideo('../x')];
        yield 'delete video empty' => [fn (VideoOptimizerClient $c) => $c->deleteVideo('')];
        yield 'delete poster ..' => [fn (VideoOptimizerClient $c) => $c->deletePoster('..')];
        yield 'thumbnail image a/b' => [fn (VideoOptimizerClient $c) => $c->getThumbnailImage('a/b', 1)];
        yield 'embed a/b' => [fn (VideoOptimizerClient $c) => $c->getEmbed('a/b')];
        yield 'embed with query' => [fn (VideoOptimizerClient $c) => $c->getEmbed('v1?x=1')];
        yield 'trailing newline' => [fn (VideoOptimizerClient $c) => $c->getVideo("v1\n")];
        yield 'leading newline' => [fn (VideoOptimizerClient $c) => $c->getVideo("\nv1")];
    }

    /**
     * IDs end up in the upstream path. Dot segments would be resolved by HttpClient and re-target
     * the request (issue #5), so anything but a plain ID is rejected before a request is sent.
     */
    #[DataProvider('invalidPathIdCalls')]
    public function testInvalidPathIdIsRejectedBeforeAnyRequest(callable $call): void
    {
        $http = new MockHttpClient(function (): MockResponse {
            static::fail('No upstream request may be sent for an invalid ID.');
        });

        $this->expectException(InvalidRequestException::class);
        $call($this->client($http));
    }

    public function testUuidPathIdIsAccepted(): void
    {
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse(json_encode(['data' => ['uuid' => 'x']]));
        });

        $this->client($http)->getVideo('E7AB89B1-b74f-42fd-b2a9-53bfe1d492a8');

        static::assertSame('https://api.videooptimizer.eu/api/v1/videos/E7AB89B1-b74f-42fd-b2a9-53bfe1d492a8', $capturedUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedBaseUrls(): iterable
    {
        yield 'query' => ['https://api.example/api/v1?x='];
        yield 'fragment' => ['https://api.example/api/v1#x'];
        yield 'userinfo' => ['https://user:pass@api.example/api/v1'];
        yield 'localhost' => ['https://localhost/api/v1'];
        yield 'localhost subdomain' => ['https://api.localhost/api/v1'];
        yield 'loopback v4' => ['https://127.0.0.1/api/v1'];
        yield 'private 10/8' => ['https://10.0.0.5/api/v1'];
        yield 'private 172.16/12' => ['https://172.16.1.1/api/v1'];
        yield 'private 192.168/16' => ['https://192.168.1.10/api/v1'];
        yield 'link-local metadata' => ['https://169.254.169.254/latest'];
        yield 'unspecified' => ['https://0.0.0.0/api/v1'];
        yield 'loopback v6' => ['https://[::1]/api/v1'];
        yield 'unique local v6' => ['https://[fd00::1]/api/v1'];
        yield 'localhost trailing dot' => ['https://localhost./api/v1'];
        yield 'decimal ipv4' => ['https://2130706433/api/v1'];
        yield 'short ipv4' => ['https://127.1/api/v1'];
        yield 'hex ipv4' => ['https://0x7f000001/api/v1'];
        yield 'cgnat' => ['https://100.64.0.1/api/v1'];
        yield 'localhost upper case' => ['https://LOCALHOST/api/v1'];
        yield 'localhost subdomain upper case' => ['https://API.LocalHost/api/v1'];
        yield 'user name without password' => ['https://user@api.example/api/v1'];
        yield 'password without user name' => ['https://:pass@api.example/api/v1'];
        yield 'hex ipv4 upper case' => ['https://0X7F000001/api/v1'];
        yield 'hex label' => ['https://api.0x7f.example/api/v1'];
        yield 'full-width localhost' => ["https://\u{FF2C}\u{FF2F}\u{FF23}\u{FF21}\u{FF2C}\u{FF28}\u{FF2F}\u{FF33}\u{FF34}/api/v1"];
        yield 'full-width localhost subdomain' => ["https://api.\u{FF4C}\u{FF4F}\u{FF43}\u{FF41}\u{FF4C}\u{FF48}\u{FF4F}\u{FF53}\u{FF54}/api/v1"];
        yield 'full-width loopback ip' => ["https://\u{FF11}\u{FF12}\u{FF17}.\u{FF10}.\u{FF10}.\u{FF11}/api/v1"];
        yield 'ideographic full stop in loopback ip' => ["https://127\u{3002}0\u{3002}0\u{3002}1/api/v1"];
        yield 'full-width private ip' => ["https://\u{FF11}\u{FF10}.\u{FF10}.\u{FF10}.\u{FF15}/api/v1"];
        yield 'full-width decimal ipv4' => ["https://\u{FF12}\u{FF11}\u{FF13}\u{FF10}\u{FF17}\u{FF14}\u{FF13}\u{FF13}\u{FF16}\u{FF14}\u{FF18}/api/v1"];
        yield 'full-width hex ipv4' => ["https://0\u{FF58}7f000001/api/v1"];
        yield 'localhost with soft hyphen' => ["https://local\u{AD}host/api/v1"];
    }

    #[DataProvider('rejectedBaseUrls')]
    public function testRestrictedApiBaseUrlThrows(string $baseUrl): void
    {
        $http = new MockHttpClient(function (): MockResponse {
            static::fail('No upstream request may be sent to a rejected base URL.');
        });

        $this->expectException(InvalidApiBaseUrlException::class);
        $this->client($http, $this->config(baseUrl: $baseUrl))->listLibraries();
    }

    #[DataProvider('rejectedBaseUrls')]
    public function testRestrictedEmbedBaseUrlThrows(string $baseUrl): void
    {
        $this->expectException(InvalidApiBaseUrlException::class);
        $this->client(new MockHttpClient(), $this->config(embedBaseUrl: $baseUrl))->embedBaseUrl();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedBaseUrls(): iterable
    {
        yield 'starts with digits' => ['https://1api.example.com/api/v1'];
        yield 'ends with digits' => ['https://api.example2/api/v1'];
        yield 'digits in the middle' => ['https://my2api.example.com/api/v1'];
        yield 'digit-only label with letters elsewhere' => ['https://123.example.com/api/v1'];
        yield 'upper case host' => ['https://API.Example.COM/api/v1'];
        yield 'internationalized name' => ["https://b\u{FC}cher.example/api/v1"];
        yield 'full-width public name' => ["https://\u{FF41}\u{FF50}\u{FF49}.example.com/api/v1"];
        yield 'hex-like word' => ['https://0xample.example.com/api/v1'];
        yield 'public ipv6' => ['https://[2606:4700:4700::1111]/api/v1'];
    }

    #[DataProvider('acceptedBaseUrls')]
    public function testValidHostNamesAreAccepted(string $baseUrl): void
    {
        $http = new MockHttpClient(fn (): MockResponse => new MockResponse(json_encode(['data' => []])));

        static::assertSame([], $this->client($http, $this->config(baseUrl: $baseUrl))->listLibraries());
    }

    /**
     * Only plain IDs reach the embed lookup queue; a newline-terminated one would match a "$" without "D".
     */
    public function testQueueEmbedIgnoresMalformedIds(): void
    {
        $requested = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;

            return $this->playable(basename($url));
        });
        $client = $this->client($http);
        foreach (["a1\n", "\na1", 'a/1', ''] as $id) {
            $client->queueEmbed($id);
        }
        $client->queueEmbed('ok1');

        $client->getEmbed('ok1');

        static::assertSame(['https://api.videooptimizer.eu/api/v1/embed/ok1'], $requested);
    }

    public function testLibraryActionsIgnoreMalformedVideoIdsFromTheListing(): void
    {
        $http = new MockHttpClient(fn (string $method): MockResponse => $method === 'GET'
            ? new MockResponse(json_encode(['data' => [['uuid' => "v1\n"], ['uuid' => 'v2'], ['uuid' => 5]]]))
            : new MockResponse(json_encode(['data' => []])));
        $invalidated = [];
        $invalidator = $this->createStub(CacheInvalidator::class);
        $invalidator->method('invalidate')->willReturnCallback(function (array $tags) use (&$invalidated): void {
            $invalidated = [...$invalidated, ...$tags];
        });

        (new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator))->deleteLibrary('lib-1');

        static::assertSame(['scalecommerce-vo-library-lib-1', 'scalecommerce-vo-video-v2'], $invalidated);
    }

    private function guarded(HttpClientInterface $inner, array $resolve = ['api.videooptimizer.eu' => '93.184.216.34', 'cdn.example.com' => '93.184.216.35']): HttpClientInterface
    {
        // The guard resolves host names itself; "resolve" pins them so the tests need no DNS.
        return (new NoPrivateNetworkHttpClient($inner))->withOptions(['resolve' => $resolve]);
    }

    /**
     * The shared HTTP client is wrapped in NoPrivateNetworkHttpClient (services.php), which checks
     * the resolved IP of every connection: a public name pointing to a private address is refused (#33).
     *
     * @return iterable<string, array{string}>
     */
    public static function privateAddresses(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'private 10/8' => ['10.0.0.5'];
        yield 'private 192.168/16' => ['192.168.1.10'];
        yield 'link-local metadata' => ['169.254.169.254'];
        yield 'loopback v6' => ['::1'];
    }

    #[DataProvider('privateAddresses')]
    public function testHostNameResolvingToAPrivateAddressIsRefused(string $ip): void
    {
        $http = new MockHttpClient(function (): MockResponse {
            static::fail('No request may reach a private address.');
        });
        $client = $this->client($this->guarded($http, ['api.videooptimizer.eu' => $ip]));

        try {
            $client->listLibraries();
            static::fail('The request must be refused.');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(502, $e->getStatusCode());
            static::assertStringNotContainsString($ip, $e->getMessage());
        }
    }

    public function testThumbnailRedirectToAPrivateAddressIsRefused(): void
    {
        $requested = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://127.0.0.1:8080/secret']]);
        });

        try {
            $this->client($this->guarded($http))->getThumbnailImage('v1', 1);
            static::fail('The redirect must be refused.');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(502, $e->getStatusCode());
        }
        static::assertCount(1, $requested, 'The redirect target must not be requested.');
    }

    public function testThumbnailRedirectToAHostResolvingToAPrivateAddressIsRefused(): void
    {
        $requested = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $requested[] = $url;

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://rebind.example.com/t.jpg']]);
        });
        $client = $this->client($this->guarded($http, ['api.videooptimizer.eu' => '93.184.216.34', 'rebind.example.com' => '10.0.0.5']));

        $this->expectException(VideoOptimizerApiException::class);
        try {
            $client->getThumbnailImage('v1', 1);
        } finally {
            static::assertCount(1, $requested);
        }
    }

    /**
     * Thumbnails may be served from a CDN behind a redirect; the redirect passes the guard.
     */
    public function testThumbnailFollowsARedirectToAPublicHostWithoutTheToken(): void
    {
        $authorization = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$authorization): MockResponse {
            $authorization[$url] = array_values(array_filter($options['headers'] ?? [], static fn (string $h): bool => stripos($h, 'Authorization:') === 0));
            if (str_starts_with($url, 'https://api.videooptimizer.eu')) {
                return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://cdn.example.com/t.jpg']]);
            }

            return new MockResponse('IMG', ['response_headers' => ['Content-Type' => 'image/jpeg']]);
        });

        $image = $this->client($this->guarded($http))->getThumbnailImage('v1', 1);

        static::assertSame('IMG', $image['content']);
        static::assertSame(['Authorization: Bearer vp_test'], $authorization['https://api.videooptimizer.eu/api/v1/videos/v1/thumbnails/1']);
        static::assertSame([], $authorization['https://cdn.example.com/t.jpg']);
    }

    /**
     * The API is not expected to redirect; a redirect must neither be followed nor deliver a foreign body (#33).
     */
    public function testApiCallsDoNotFollowRedirects(): void
    {
        $captured = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = $options['max_redirects'] ?? 'unset';

            return new MockResponse('{"codecs":["secret"]}', ['http_code' => 302, 'response_headers' => ['Location' => 'https://cdn.example.com/x']]);
        });

        try {
            $this->client($http)->listEncodings();
            static::fail('A redirect response is an error for API calls.');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(502, $e->getStatusCode());
            static::assertSame('The VideoOptimizer API answered with a redirect; check the API base URL.', $e->getMessage());
        }
        static::assertSame([0], $captured);
    }

    public function testRedirectOnTheThumbnailRouteIsABadGatewayToo(): void
    {
        $http = new MockHttpClient(fn (): MockResponse => new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://cdn.example.com/x']]));
        // Redirects that are not followed (here: the mock does not follow) must not reach the browser as 3xx.
        try {
            $this->client($http, $this->config())->getThumbnailImage('v1', 1);
            static::fail('A redirect response is an error.');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(502, $e->getStatusCode());
        }
    }

    public function testEveryApiRequestDisablesRedirects(): void
    {
        $captured = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[$method . ' ' . parse_url($url, PHP_URL_PATH)] = $options['max_redirects'] ?? 'unset';

            return $this->playable('v1');
        });
        $client = $this->client($http);
        $client->queueEmbed('v1');
        $client->getEmbed('v1');
        $client->getVideo('v1');
        $client->createLibrary(['name' => 'x']);

        static::assertSame(
            ['GET /api/v1/embed/v1' => 0, 'GET /api/v1/videos/v1' => 0, 'POST /api/v1/libraries' => 0],
            $captured,
        );
    }

    public function testThumbnailRedirectsAreBounded(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options['max_redirects'] ?? 'unset';

            return new MockResponse('IMG');
        });

        $this->client($http)->getThumbnailImage('v1', 1);

        static::assertSame(3, $captured);
    }

    public function testPublicIpApiBaseUrlIsAccepted(): void
    {
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse(json_encode(['data' => []]));
        });

        $this->client($http, $this->config(baseUrl: 'https://93.184.215.14:8443/api/v1'))->listLibraries();

        static::assertStringStartsWith('https://93.184.215.14:8443/api/v1/libraries', $capturedUrl);
    }

    public function testReprocessLibrarySendsPostAndUnwrapsData(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $http = new MockHttpClient(function (string $method, string $url) use (&$capturedMethod, &$capturedUrl): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            return new MockResponse(json_encode(['data' => ['queued' => 3]]));
        });

        $client = $this->client($http, $this->config());
        $result = $client->reprocessLibrary('lib-1');

        static::assertSame('POST', $capturedMethod);
        static::assertStringEndsWith('/libraries/lib-1/reprocess', $capturedUrl);
        static::assertSame(['queued' => 3], $result);
    }

    public function testIngestVideoUrlPostsPayloadAndUnwrapsData(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];
            return new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'status' => 'processing']]));
        });

        $client = $this->client($http, $this->config());
        $payload = ['library_id' => 'lib-1', 'source_url' => 'https://example.com/video.mp4'];
        $result = $client->ingestVideoUrl($payload);

        static::assertSame('POST', $captured['method']);
        static::assertStringEndsWith('/videos', $captured['url']);
        static::assertSame($payload, json_decode((string) $captured['body'], true));
        static::assertSame('processing', $result['status']);
    }

    public function testTransportErrorBecomesNeutralBadGateway(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host: api.example for "https://api.example/api/v1/libraries?limit=100"']));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')
            ->with(static::stringContains('could not be reached'), static::callback(
                fn (array $context) => str_contains((string) $context['error'], 'Could not resolve host')
            ));

        try {
            (new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), $logger))->listLibraries();
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(502, $e->getStatusCode());
            static::assertStringNotContainsString('api.example', $e->getMessage());
            static::assertInstanceOf(TransportExceptionInterface::class, $e->getPrevious());
        }
    }

    public function testTransportErrorOnThumbnailImageBecomesBadGateway(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Connection reset']));

        $this->expectException(VideoOptimizerApiException::class);
        $this->expectExceptionCode(502);
        $this->client($http)->getThumbnailImage('v1', 1);
    }

    public function testTransportErrorOnEmbedBecomesBadGateway(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['error' => 'Connection reset']));

        $this->expectException(VideoOptimizerApiException::class);
        $this->expectExceptionCode(502);
        $this->client($http)->getEmbed('v1');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidSuccessBodies(): iterable
    {
        yield 'html maintenance page' => ['GET', 200, '<html><body>Maintenance</body></html>'];
        yield 'truncated json' => ['GET', 200, '{"data": ['];
        yield 'json scalar' => ['GET', 200, '"ok"'];
    }

    #[DataProvider('invalidSuccessBodies')]
    public function testUndecodableSuccessBodyIsAnError(string $method, int $status, string $body): void
    {
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => $status]));

        $this->expectException(VideoOptimizerApiException::class);
        $this->expectExceptionCode(502);
        $this->client($http)->getVideo('v1');
    }

    public function testEmptySuccessBodyStaysAnEmptyResult(): void
    {
        $http = new MockHttpClient([
            new MockResponse('', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('', ['http_code' => 202]),
        ]);
        $client = $this->client($http);

        $client->deleteVideo('v1');
        $client->deletePoster('v1');

        static::assertSame([], $client->reprocessLibrary('lib-1'));
    }

    public function testUpstreamErrorMessageIsReducedToShortPlainText(): void
    {
        $message = '<a href="https://evil.example">Click here</a>   to <b>fix</b>' . str_repeat(' x', 300);
        $http = new MockHttpClient(new MockResponse(json_encode(['message' => $message]), ['http_code' => 422]));

        try {
            $this->client($http)->getVideo('v1');
            static::fail('Expected VideoOptimizerApiException');
        } catch (VideoOptimizerApiException $e) {
            static::assertSame(422, $e->getStatusCode());
            static::assertStringStartsWith('Click here to fix x x', $e->getMessage());
            static::assertStringNotContainsString('<', $e->getMessage());
            static::assertLessThanOrEqual(200, mb_strlen($e->getMessage()));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function embedCacheTtls(): iterable
    {
        yield 'playable' => [['sources' => [['src' => 'https://cdn/v.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']]], 3600];
        yield 'no playable sources' => [['sources' => [], 'poster' => 'https://cdn/p.jpg'], 60];
    }

    /**
     * An embed without playable sources (still processing, or failed) is cached only briefly, so a
     * video that becomes ready shows up within a minute instead of after the full hour.
     */
    #[DataProvider('embedCacheTtls')]
    public function testGetEmbedCacheTtlDependsOnPlayableSources(array $payload, int $ttl): void
    {
        $item = $this->createMock(ItemInterface::class);
        $item->expects(static::once())->method('expiresAfter')->with($ttl);
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn (string $key, callable $callback) => $callback($item));

        $http = new MockHttpClient(new MockResponse(json_encode(['data' => $payload])));
        $this->client($http, null, $cache)->getEmbed('v1');
    }

    /**
     * @return iterable<string, array{list<MockResponse>, int, bool}>
     */
    public static function embedAvailabilityCases(): iterable
    {
        $playable = new MockResponse(json_encode(['data' => ['sources' => [['src' => 'https://cdn/v.mp4', 'type' => 'video/mp4', 'size' => 1]]]]));
        yield 'playable' => [[$playable], 1, false];
        yield 'no playable sources' => [[new MockResponse(json_encode(['data' => ['sources' => []]]))], 1, true];
        yield 'fresh failure' => [[new MockResponse('{}', ['http_code' => 500])], 1, true];
        yield 'cached failure' => [[new MockResponse('{}', ['http_code' => 500])], 2, true];
    }

    /**
     * A page rendered while an embed lookup failed (or returned no playable sources) must not stay
     * in the HTTP cache for the full page TTL (issue #3); getEmbed() flags it for the response.
     *
     * @param list<MockResponse> $responses
     */
    #[DataProvider('embedAvailabilityCases')]
    public function testGetEmbedFlagsDegradedEmbeds(array $responses, int $calls, bool $degraded): void
    {
        $availability = new EmbedRenderState();
        $client = new VideoOptimizerClient(new MockHttpClient($responses), $this->config(), new ArrayAdapter(), new NullLogger(), $availability);

        for ($i = 0; $i < $calls; ++$i) {
            if ($i === $calls - 1) {
                $availability->reset();
            }
            try {
                $client->getEmbed('v1');
            } catch (VideoOptimizerApiException) {
            }
        }

        static::assertSame($degraded, $availability->isDegraded());
    }

    private function playable(string $uuid): MockResponse
    {
        return new MockResponse(json_encode(['data' => ['uuid' => $uuid, 'sources' => [['src' => "https://cdn/$uuid.mp4", 'type' => 'video/mp4', 'size' => 1]]]]));
    }

    /**
     * Lookups queued during the CMS collect phase start together on the first getEmbed(), instead
     * of one after another with 3 s each (issue #4).
     */
    public function testQueuedEmbedsAreRequestedConcurrently(): void
    {
        $requested = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $uuid = basename(parse_url($url, PHP_URL_PATH));
            $requested[] = $uuid;

            return $this->playable($uuid);
        });
        $client = $this->client($http);

        $client->queueEmbed('a1');
        $client->queueEmbed('b2');
        $client->queueEmbed('c3');
        $client->queueEmbed('a1');

        static::assertSame('a1', $client->getEmbed('a1')['uuid']);
        static::assertSame(['a1', 'b2', 'c3'], $requested, 'all queued lookups start with the first getEmbed()');

        static::assertSame('b2', $client->getEmbed('b2')['uuid']);
        static::assertSame('c3', $client->getEmbed('c3')['uuid']);
        static::assertCount(3, $requested);
    }

    public function testQueuedEmbedAlreadyCachedIsNotRequestedAgain(): void
    {
        $requested = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            $uuid = basename(parse_url($url, PHP_URL_PATH));
            $requested[] = $uuid;

            return $this->playable($uuid);
        });
        $client = $this->client($http);
        $client->getEmbed('a1');

        $client->queueEmbed('a1');
        $client->queueEmbed('b2');
        $client->getEmbed('b2');

        static::assertSame(['a1', 'b2'], $requested);
    }

    /**
     * @return iterable<string, array{MockResponse, bool}>
     */
    public static function unavailabilityCases(): iterable
    {
        yield 'transport error' => [new MockResponse('', ['error' => 'Connection reset']), true];
        yield 'service unavailable' => [new MockResponse('{}', ['http_code' => 503]), true];
        yield 'gateway timeout' => [new MockResponse('{}', ['http_code' => 504]), true];
        yield 'error for a single video' => [new MockResponse('{}', ['http_code' => 500]), false];
        yield 'rate limited' => [new MockResponse('{}', ['http_code' => 429]), true];
        yield 'video not found' => [new MockResponse('{}', ['http_code' => 404]), false];
    }

    /**
     * Once the API is unavailable, further lookups fail fast for 60 s without waiting for another
     * timeout per video; a missing (deleted) video does not count.
     */
    #[DataProvider('unavailabilityCases')]
    public function testUnavailableApiFailsFastForOtherVideos(MockResponse $first, bool $failFast): void
    {
        $count = 0;
        $http = new MockHttpClient(function () use (&$count, $first): MockResponse {
            ++$count;

            return $count === 1 ? $first : $this->playable('b2');
        });
        $client = $this->client($http);

        try {
            $client->getEmbed('a1');
        } catch (VideoOptimizerApiException) {
        }

        if ($failFast) {
            try {
                $client->getEmbed('b2');
                static::fail('Expected a fast failure');
            } catch (VideoOptimizerApiException $e) {
                static::assertSame(503, $e->getStatusCode());
            }
            static::assertSame(1, $count);

            return;
        }

        static::assertSame('b2', $client->getEmbed('b2')['uuid']);
        static::assertSame(2, $count);
    }

    /**
     * Admin changes to a video invalidate the pages that show it (issue #3).
     */
    public function testForgetEmbedInvalidatesTheVideoCacheTag(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::once())->method('invalidate')->with(['scalecommerce-vo-video-v1']);

        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator);
        $client->forgetEmbed('v1');
    }

    /**
     * @return iterable<string, array{callable(VideoOptimizerClient): mixed, string}>
     */
    public static function libraryTagInvalidations(): iterable
    {
        yield 'delete' => [fn (VideoOptimizerClient $c) => $c->deleteLibrary('lib-1'), 'DELETE'];
        yield 'reprocess' => [fn (VideoOptimizerClient $c) => $c->reprocessLibrary('lib-1', true), 'POST'];
        yield 'update (encoding ladder)' => [fn (VideoOptimizerClient $c) => $c->updateLibrary('lib-1', ['encoding_quality' => 'quality']), 'PATCH'];
    }

    /**
     * The per-library tag does not depend on listing the videos, which can be truncated or fail (#29).
     */
    #[DataProvider('libraryTagInvalidations')]
    public function testLibraryChangesInvalidateTheLibraryTagEvenWhenListingFails(callable $action, string $method): void
    {
        $http = new MockHttpClient(fn (string $m): MockResponse => $m === 'GET'
            ? new MockResponse('', ['http_code' => 500])
            : new MockResponse(json_encode(['data' => []])));
        $invalidated = [];
        $invalidator = $this->createStub(CacheInvalidator::class);
        $invalidator->method('invalidate')->willReturnCallback(function (array $tags) use (&$invalidated): void {
            $invalidated = [...$invalidated, ...$tags];
        });

        $action(new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator));

        static::assertContains('scalecommerce-vo-library-lib-1', $invalidated);
    }

    public function testFailedLibraryChangeLeavesTheCacheAlone(): void
    {
        $http = new MockHttpClient(fn (): MockResponse => new MockResponse('{}', ['http_code' => 404]));
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::never())->method('invalidate');

        $this->expectException(VideoOptimizerApiException::class);
        (new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator))->updateLibrary('lib-1', []);
    }

    public function testRenderedLibrariesAreRecordedAsCacheTags(): void
    {
        $availability = new EmbedRenderState();
        $client = new VideoOptimizerClient(new MockHttpClient($this->playable('v1')), $this->config(), new ArrayAdapter(), new NullLogger(), $availability);

        $client->markLibraryRendered('lib-1');
        $client->markLibraryRendered('lib-1');
        $client->markLibraryRendered('lib/../1');
        $client->markLibraryRendered('');
        $client->getEmbed('v1');

        static::assertSame(['scalecommerce-vo-video-v1', 'scalecommerce-vo-library-lib-1'], $availability->cacheTags());
    }

    public function testGetEmbedRecordsTheRenderedVideo(): void
    {
        $availability = new EmbedRenderState();
        $client = new VideoOptimizerClient(new MockHttpClient($this->playable('v1')), $this->config(), new ArrayAdapter(), new NullLogger(), $availability);

        $client->getEmbed('v1');

        static::assertSame(['scalecommerce-vo-video-v1'], $availability->cacheTags());
    }

    public function testResetDropsQueuedLookups(): void
    {
        $count = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$count): MockResponse {
            ++$count;

            return $this->playable(basename(parse_url($url, PHP_URL_PATH)));
        });
        $client = $this->client($http);
        $client->queueEmbed('a1');
        $client->queueEmbed('b2');
        $client->reset();

        $client->getEmbed('c3');

        static::assertSame(1, $count);
    }

    /**
     * With the API down, the concurrently started lookups that are not read anymore must not throw
     * when they are destroyed at the end of the request.
     */
    public function testUnreadFailedLookupsDoNotThrowWhenDestroyed(): void
    {
        $http = new MockHttpClient(fn (): MockResponse => new MockResponse('', ['error' => 'Could not resolve host']));
        $client = $this->client($http);
        foreach (['a1', 'b2', 'c3'] as $uuid) {
            $client->queueEmbed($uuid);
        }

        foreach (['a1', 'b2'] as $uuid) {
            try {
                $client->getEmbed($uuid);
            } catch (VideoOptimizerApiException) {
            }
        }
        $client->reset();
        unset($client, $http);
        gc_collect_cycles();

        $this->addToAssertionCount(1);
    }

    /**
     * The administration pages through a library instead of collecting everything in one
     * request (issue #15).
     */
    public function testListVideosPageRequestsOnePageWithCursor(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode([
                'data' => [['uuid' => 'v1'], ['uuid' => 'v2']],
                'pagination' => ['next_cursor' => 'c2', 'has_more' => true],
            ]));
        });

        $page = $this->client($http)->listVideosPage('lib-1', 'c1', 2);

        static::assertStringContainsString('/libraries/lib-1/videos?', (string) $captured);
        static::assertStringContainsString('limit=2', (string) $captured);
        static::assertStringContainsString('cursor=c1', (string) $captured);
        static::assertSame([['uuid' => 'v1'], ['uuid' => 'v2']], $page['items']);
        static::assertSame(['next_cursor' => 'c2', 'has_more' => true], $page['pagination']);
    }

    public function testListVideosPageWithoutCursorAndLastPage(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
            $captured = $url;

            return new MockResponse(json_encode(['data' => [['uuid' => 'v1']]]));
        });

        $page = $this->client($http)->listVideosPage('lib-1', null, 60);

        static::assertStringNotContainsString('cursor=', (string) $captured);
        static::assertSame(['next_cursor' => null, 'has_more' => false], $page['pagination']);
    }

    public function testListingAllPagesLogsWhenTheListIsTruncated(): void
    {
        $page = 0;
        $http = new MockHttpClient(function () use (&$page): MockResponse {
            ++$page;

            return new MockResponse(json_encode([
                'data' => [['id' => 'l' . $page]],
                'pagination' => ['next_cursor' => 'c' . $page, 'has_more' => true],
            ]));
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(static::stringContains('truncated'));

        $items = (new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), $logger))->listLibraries();

        static::assertCount(100, $items);
    }

    /**
     * A lookup that fails only because the API is flagged unavailable must not extend the flag,
     * otherwise steady traffic keeps the outage alive forever (pre-release review of 1.1.0).
     */
    public function testFailuresFromTheFlagDoNotExtendIt(): void
    {
        $cache = new class extends ArrayAdapter {
            public int $flagWrites = 0;

            public function save(\Psr\Cache\CacheItemInterface $item): bool
            {
                if ($item->getKey() === 'scalecommerce_vo_embed_api_unavailable') {
                    ++$this->flagWrites;
                }

                return parent::save($item);
            }
        };
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{}', ['http_code' => 503]);
        });
        $client = $this->client($http, null, $cache);

        foreach (['a1', 'b2', 'c3', 'a1'] as $uuid) {
            try {
                $client->getEmbed($uuid);
            } catch (VideoOptimizerApiException) {
            }
        }

        static::assertSame(1, $requests);
        static::assertSame(1, $cache->flagWrites);
    }

    public function testConcurrentLookupsAreStartedInBatchesOfEight(): void
    {
        $requested = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$requested): MockResponse {
            ++$requested;

            return $this->playable(basename(parse_url($url, PHP_URL_PATH)));
        });
        $client = $this->client($http);
        $uuids = array_map(fn (int $i): string => 'v' . $i, range(1, 12));
        foreach ($uuids as $uuid) {
            $client->queueEmbed($uuid);
        }

        $client->getEmbed('v1');
        static::assertSame(8, $requested);

        foreach ($uuids as $uuid) {
            static::assertSame($uuid, $client->getEmbed($uuid)['uuid']);
        }
        static::assertSame(12, $requested);
    }

    /**
     * A failed lookup must not keep the client alive through an exception/closure cycle (with
     * zend.exception_ignore_args=Off the trace holds the cache callback), otherwise unread
     * pre-started lookups are only cancelled at script end, in an undefined destructor order (#25).
     */
    public function testClientIsFreedAfterAFailedLookupWithExceptionArgsEnabled(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $http = new MockHttpClient(fn (): MockResponse => new MockResponse('{}', ['http_code' => 404]));
            $client = $this->client($http);
            $client->queueEmbed('a1');
            $client->queueEmbed('b2');
            try {
                $client->getEmbed('a1');
                static::fail('The failed lookup must throw.');
            } catch (VideoOptimizerApiException) {
            }

            $reference = \WeakReference::create($client);
            unset($client, $http);
            gc_collect_cycles();

            static::assertNull($reference->get(), 'The client is still referenced after a failed lookup.');
        } finally {
            ini_set('zend.exception_ignore_args', $previous === false ? '1' : $previous);
        }
    }

    /**
     * @return iterable<string, array{callable(VideoOptimizerClient): mixed, list<string>}>
     */
    public static function libraryActions(): iterable
    {
        yield 'delete' => [fn (VideoOptimizerClient $c) => $c->deleteLibrary('lib-1'), ['GET /libraries/lib-1/videos', 'DELETE /libraries/lib-1']];
        yield 'reprocess' => [fn (VideoOptimizerClient $c) => $c->reprocessLibrary('lib-1'), ['POST /libraries/lib-1/reprocess', 'GET /libraries/lib-1/videos']];
    }

    /**
     * Deleting or reprocessing a library changes all its videos, so the pages showing them are
     * invalidated like after a change to a single video.
     *
     * @param list<string> $expectedCalls
     */
    #[DataProvider('libraryActions')]
    public function testLibraryActionsInvalidateTheirVideos(callable $action, array $expectedCalls): void
    {
        $calls = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            $calls[] = $method . ' ' . str_replace('/api/v1', '', (string) parse_url($url, PHP_URL_PATH));

            return $method === 'GET'
                ? new MockResponse(json_encode(['data' => [['uuid' => 'v1'], ['uuid' => 'v2']]]))
                : new MockResponse(json_encode(['data' => []]));
        });
        $invalidated = [];
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::exactly(3))->method('invalidate')->willReturnCallback(function (array $tags) use (&$invalidated): void {
            $invalidated = [...$invalidated, ...$tags];
        });

        $action(new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator));

        static::assertSame($expectedCalls, $calls);
        static::assertSame(['scalecommerce-vo-library-lib-1', 'scalecommerce-vo-video-v1', 'scalecommerce-vo-video-v2'], $invalidated);
    }

    public function testLibraryDeleteStillRunsWhenListingItsVideosFails(): void
    {
        $calls = [];
        $http = new MockHttpClient(function (string $method) use (&$calls): MockResponse {
            $calls[] = $method;

            return $method === 'GET' ? new MockResponse('{}', ['http_code' => 503]) : new MockResponse('', ['http_code' => 204]);
        });
        // Only the library tag: the videos could not be listed.
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::once())->method('invalidate')->with(['scalecommerce-vo-library-lib-1']);

        (new VideoOptimizerClient($http, $this->config(), new ArrayAdapter(), new NullLogger(), new EmbedRenderState(), $invalidator))->deleteLibrary('lib-1');

        static::assertSame(['GET', 'DELETE'], $calls);
    }

    /**
     * After changing `encoding_quality`, finished renditions are only re-encoded with `force`.
     */
    public function testReprocessLibrarySendsForceOnlyWhenRequested(): void
    {
        $bodies = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$bodies): MockResponse {
            $bodies[] = $options['body'] ?? '';

            return new MockResponse(json_encode(['data' => ['queued' => 1]]));
        });
        $client = $this->client($http);

        $client->reprocessLibrary('lib-1');
        $client->reprocessLibrary('lib-1', true);

        static::assertSame('', $bodies[0]);
        static::assertSame(['force' => true], json_decode((string) $bodies[1], true));
    }

    public function testApiErrorCodeIsExposed(): void
    {
        $e = VideoOptimizerApiException::fromResponse(400, '{"message":"No.","data":{"code":"source_url_video_platform"}}');

        static::assertSame('source_url_video_platform', $e->getApiCode());
        static::assertNull(VideoOptimizerApiException::fromResponse(500, 'oops')->getApiCode());
    }
}
