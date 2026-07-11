<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VideoOptimizerClientTest extends TestCase
{
    private function config(string $token = 'vp_test'): SystemConfigService
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getString')->willReturnMap([
            ['VideoOptimizer.config.apiToken', null, $token],
            ['VideoOptimizer.config.apiBaseUrl', null, 'https://api.videooptimizer.eu/api/v1'],
        ]);
        return $config;
    }

    public function testListLibrariesUnwrapsDataAndSendsBearerToken(): void
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

        static::assertSame('https://api.videooptimizer.eu/api/v1/libraries', $capturedUrl);
        static::assertSame('Authorization: Bearer vp_test', $capturedAuth);
        static::assertSame([['id' => 'lib-1', 'name' => 'Demo']], $result);
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

    public function testGetEmbedDoesNotRequireToken(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(['data' => ['uuid' => 'v1', 'sources' => []]])));
        $client = new VideoOptimizerClient($http, $this->config(''));
        $result = $client->getEmbed('v1');
        static::assertSame('v1', $result['uuid']);
    }

    public function testUploadVideoSendsMultipartHeadersWithTitle(): void
    {
        $path = sys_get_temp_dir() . '/' . uniqid('video-optimizer-test-', true) . '.mp4';
        file_put_contents($path, 'fake-video-bytes');

        try {
            $file = new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);

            $capturedOptions = null;
            $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                $capturedOptions = $options;
                return new MockResponse(json_encode(['data' => ['uuid' => 'vid-1']]));
            });

            $client = new VideoOptimizerClient($http, $this->config());
            $result = $client->uploadVideo('lib-9', $file, 'My Clip');

            $headers = $capturedOptions['headers'] ?? [];

            $libraryHeader = null;
            $titleHeader = null;
            $contentTypeHeader = null;
            foreach ($headers as $header) {
                $header = (string) $header;
                if (str_starts_with(strtolower($header), 'x-library-id:')) {
                    $libraryHeader = $header;
                }
                if (str_starts_with(strtolower($header), 'x-title:')) {
                    $titleHeader = $header;
                }
                if (str_starts_with(strtolower($header), 'content-type:') && str_contains(strtolower($header), 'multipart/form-data')) {
                    $contentTypeHeader = $header;
                }
            }

            static::assertNotNull($libraryHeader);
            static::assertStringStartsWith('x-library-id: lib-9', $libraryHeader);
            static::assertNotNull($titleHeader);
            static::assertStringStartsWith('x-title: My Clip', $titleHeader);
            static::assertNotNull($contentTypeHeader);
            static::assertSame(['uuid' => 'vid-1'], $result);
        } finally {
            @unlink($path);
        }
    }

    public function testUploadVideoOmitsTitleHeaderWhenNull(): void
    {
        $path = sys_get_temp_dir() . '/' . uniqid('video-optimizer-test-', true) . '.mp4';
        file_put_contents($path, 'fake-video-bytes');

        try {
            $file = new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);

            $capturedOptions = null;
            $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
                $capturedOptions = $options;
                return new MockResponse(json_encode(['data' => ['uuid' => 'vid-1']]));
            });

            $client = new VideoOptimizerClient($http, $this->config());
            $client->uploadVideo('lib-9', $file, null);

            $headers = $capturedOptions['headers'] ?? [];

            $libraryHeader = null;
            $titleHeaderFound = false;
            foreach ($headers as $header) {
                $header = (string) $header;
                if (str_starts_with(strtolower($header), 'x-library-id:')) {
                    $libraryHeader = $header;
                }
                if (str_starts_with(strtolower($header), 'x-title:')) {
                    $titleHeaderFound = true;
                }
            }

            static::assertNotNull($libraryHeader);
            static::assertStringStartsWith('x-library-id: lib-9', $libraryHeader);
            static::assertFalse($titleHeaderFound);
        } finally {
            @unlink($path);
        }
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
}
