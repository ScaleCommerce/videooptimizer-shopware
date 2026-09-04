<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class VideoOptimizerClientTest extends TestCase
{
    private function config(
        string $token = 'vp_test',
        string $baseUrl = 'https://api.videooptimizer.eu/api/v1',
        string $embedBaseUrl = 'https://videooptimizer.eu',
    ): SystemConfigService {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getString')->willReturnMap([
            ['ScaleVideoOptimizer.config.apiToken', null, $token],
            ['ScaleVideoOptimizer.config.apiBaseUrl', null, $baseUrl],
            ['ScaleVideoOptimizer.config.embedBaseUrl', null, $embedBaseUrl],
        ]);
        return $config;
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
        $result = $client->listLibraries();

        static::assertSame(2, $count);
        static::assertSame([['id' => 'lib-1']], $result);
    }

    public function testMissingTokenThrows(): void
    {
        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(''));
        $this->expectException(MissingApiTokenException::class);
        $client->listLibraries();
    }

    public function testApiErrorThrowsWithStatusCode(): void
    {
        $http = new MockHttpClient(new MockResponse(
            json_encode(['statusCode' => 401, 'message' => 'Unauthorized']),
            ['http_code' => 401]
        ));
        $client = new VideoOptimizerClient($http, $this->config());

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
        $client = new VideoOptimizerClient($http, $this->config());

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
        $client = new VideoOptimizerClient($http, $this->config(''));
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
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

        $client = new VideoOptimizerClient($http, $this->config());
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
        $client = new VideoOptimizerClient($http, $this->config());
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
        $client = new VideoOptimizerClient($http, $this->config());
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
        $client = new VideoOptimizerClient($http, $this->config());
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
        $client = new VideoOptimizerClient($http, $this->config());
        $image = $client->getThumbnailImage('vid-1', 2);
        static::assertSame('IMG-BYTES', $image['content']);
        static::assertSame('image/jpeg', $image['contentType']);
    }

    public function testNonHttpsApiBaseUrlThrows(): void
    {
        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(baseUrl: 'http://api.videooptimizer.eu/api/v1'));
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

        $client = new VideoOptimizerClient($http, $this->config(baseUrl: 'https://api.example/api/v1/'));
        $client->listLibraries();

        static::assertStringStartsWith('https://api.example/api/v1/libraries', $capturedUrl);
    }

    public function testEmbedBaseUrlReadsConfigAndTrimsTrailingSlash(): void
    {
        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(embedBaseUrl: 'https://embed.example/'));
        static::assertSame('https://embed.example', $client->embedBaseUrl());
    }

    public function testEmbedBaseUrlFallsBackToDefault(): void
    {
        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(embedBaseUrl: ''));
        static::assertSame('https://videooptimizer.eu', $client->embedBaseUrl());
    }

    public function testNonHttpsEmbedBaseUrlThrows(): void
    {
        $client = new VideoOptimizerClient(new MockHttpClient(), $this->config(embedBaseUrl: 'http://embed.example'));
        $this->expectException(InvalidApiBaseUrlException::class);
        $client->embedBaseUrl();
    }
}
