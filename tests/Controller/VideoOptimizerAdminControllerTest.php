<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidRequestException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

class VideoOptimizerAdminControllerTest extends TestCase
{
    private const UUID = 'e7ab89b1-b74f-42fd-b2a9-53bfe1d492a8';

    public function testListLibrariesReturnsClientData(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listLibraries')->willReturn([['id' => 'lib-1']]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listLibraries();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => [['id' => 'lib-1']]], json_decode((string) $response->getContent(), true));
    }

    public function testApiExceptionBecomesErrorJson(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listLibraries')->willThrowException(new VideoOptimizerApiException(429, 'Too Many Requests'));

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listLibraries();

        static::assertSame(429, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        static::assertSame('Too Many Requests', $body['errors'][0]['detail']);
    }

    public function testMissingTokenReturns400(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listLibraries')->willThrowException(new MissingApiTokenException());

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listLibraries();

        static::assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        static::assertSame('400', $body['errors'][0]['status']);
    }

    public function testDeleteLibraryReturns204(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('deleteLibrary')->with('lib-1');

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->deleteLibrary('lib-1');

        static::assertSame(204, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testListAllVideosReturnsClientData(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('listAllVideos')->with(null)->willReturn([['uuid' => 'v1']]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listAllVideos(new Request());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => [['uuid' => 'v1']]], json_decode((string) $response->getContent(), true));
    }

    public function testInitiateUploadPassesJsonPayload(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('initiateUpload')
            ->with(['libraryId' => 'lib-1', 'filename' => 'a.mp4'])
            ->willReturn(['uuid' => 'v1', 'uploadId' => 'u1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode(['libraryId' => 'lib-1', 'filename' => 'a.mp4']));
        $response = $controller->initiateUpload($request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('u1', json_decode((string) $response->getContent(), true)['data']['uploadId']);
    }

    public function testCompleteUploadPassesJsonPayload(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('completeUpload')
            ->with(['uuid' => 'v1', 'parts' => [['partNumber' => 1, 'etag' => '"abc"']]])
            ->willReturn(['uuid' => 'v1', 'status' => 'processing']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode(['uuid' => 'v1', 'parts' => [['partNumber' => 1, 'etag' => '"abc"']]]));
        $response = $controller->completeUpload($request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testMalformedJsonReturns400(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('createLibrary');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], '{ not valid json');
        $response = $controller->createLibrary($request);

        static::assertSame(400, $response->getStatusCode());
    }

    public function testListEncodingsReturnsClientData(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listEncodings')->willReturn(['codecs' => [['key' => 'h264']], 'resolutions' => []]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listEncodings();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            ['data' => ['codecs' => [['key' => 'h264']], 'resolutions' => []]],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testListThumbnailsReturnsClientData(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('listThumbnails')->with('v1')->willReturn(['thumbnails' => [['index' => 0]]]);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listThumbnails('v1');
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => ['thumbnails' => [['index' => 0]]]], json_decode((string) $response->getContent(), true));
    }

    public function testGetThumbnailImageStreamsBytes(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => 'IMG', 'contentType' => 'image/jpeg']);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->getThumbnailImage('v1', '2');
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('IMG', $response->getContent());
        static::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        static::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        static::assertSame('inline', $response->headers->get('Content-Disposition'));
    }

    public function testGetThumbnailImageAllowsWebpAndGif(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getThumbnailImage')->willReturnMap([
            ['v1', 2, ['content' => 'WEBP', 'contentType' => 'image/webp']],
            ['v1', 3, ['content' => 'GIF', 'contentType' => 'image/gif; charset=binary']],
        ]);
        $controller = new VideoOptimizerAdminController($client);

        $webp = $controller->getThumbnailImage('v1', '2');
        static::assertSame('image/webp', $webp->headers->get('Content-Type'));

        // The parameter after ";" is not stripped when the media type itself is allowlisted.
        $gif = $controller->getThumbnailImage('v1', '3');
        static::assertSame('image/gif; charset=binary', $gif->headers->get('Content-Type'));
    }

    public function testGetThumbnailImageRewritesDisallowedContentTypeToOctetStream(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => '<script>evil()</script>', 'contentType' => 'text/html']);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->getThumbnailImage('v1', '2');

        static::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        static::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        static::assertSame('inline', $response->headers->get('Content-Disposition'));
    }

    public function testGetThumbnailImageContentTypeComparisonIsCaseInsensitive(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => 'IMG', 'contentType' => 'IMAGE/JPEG']);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->getThumbnailImage('v1', '2');

        static::assertSame('IMAGE/JPEG', $response->headers->get('Content-Type'));
    }

    public function testSelectThumbnailPassesIndex(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('selectThumbnail')->with('v1', 3)->willReturn(['poster' => ['source' => 'thumbnail']]);
        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode(['thumbnailIndex' => 3]));
        $response = $controller->selectThumbnail('v1', $request);
        static::assertSame(200, $response->getStatusCode());
    }

    public function testDeletePosterReturns204(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('deletePoster')->with('v1');
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->deletePoster('v1');
        static::assertSame(204, $response->getStatusCode());
    }

    public function testCreateLibraryDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('createLibrary')
            ->with(['name' => 'Demo', 'description' => 'A library'])
            ->willReturn(['id' => 'lib-1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Demo',
            'description' => 'A library',
            'isCompany' => true,
        ]));
        $response = $controller->createLibrary($request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateLibraryDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('updateLibrary')
            ->with('lib-1', ['name' => 'Demo', 'codec' => 'h264', 'resolutions' => '1080p'])
            ->willReturn(['id' => 'lib-1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Demo',
            'codec' => 'h264',
            'resolutions' => '1080p',
            'organizationId' => 'org-1',
        ]));
        $response = $controller->updateLibrary('lib-1', $request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateLibraryForwardsValidEncodingQuality(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('updateLibrary')
            ->with('lib-1', ['encoding_quality' => 'quality'])
            ->willReturn(['id' => 'lib-1']);

        $response = (new VideoOptimizerAdminController($client))->updateLibrary('lib-1', new Request([], [], [], [], [], [], '{"encoding_quality":"quality"}'));

        static::assertSame(200, $response->getStatusCode());
    }

    public function testMalformedReprocessBodyIsRejected(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('reprocessLibrary');

        $response = (new VideoOptimizerAdminController($client))->reprocessLibrary(self::UUID, new Request([], [], [], [], [], [], '{not json'));

        static::assertSame(400, $response->getStatusCode());
    }

    public function testUpdateVideoOnlyPassesTitle(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('updateVideo')
            ->with('v1', ['title' => 'New title'])
            ->willReturn(['uuid' => 'v1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'title' => 'New title',
            'status' => 'ready',
        ]));
        $response = $controller->updateVideo('v1', $request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testInitiateUploadDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('initiateUpload')
            ->with(['libraryId' => 'lib-1', 'filename' => 'a.mp4', 'contentType' => 'video/mp4', 'fileSize' => 10])
            ->willReturn(['uuid' => 'v1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'libraryId' => 'lib-1',
            'filename' => 'a.mp4',
            'contentType' => 'video/mp4',
            'fileSize' => 10,
            'title' => 'ignored here',
        ]));
        $response = $controller->initiateUpload($request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testCompleteUploadDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('completeUpload')
            ->with(['libraryId' => 'lib-1', 'uuid' => 'v1', 'key' => 'k', 'uploadId' => 'u1', 'title' => 't', 'parts' => []])
            ->willReturn(['uuid' => 'v1']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'libraryId' => 'lib-1',
            'uuid' => 'v1',
            'key' => 'k',
            'uploadId' => 'u1',
            'title' => 't',
            'parts' => [],
            'status' => 'processing',
        ]));
        $response = $controller->completeUpload($request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testInitiatePosterUploadDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('initiatePosterUpload')
            ->with('v1', ['contentType' => 'image/png', 'fileSize' => 10])
            ->willReturn(['key' => 'k']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'contentType' => 'image/png',
            'fileSize' => 10,
            'uuid' => 'ignored',
        ]));
        $response = $controller->initiatePosterUpload('v1', $request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testInitiatePosterUploadRejectsUnsupportedContentType(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('initiatePosterUpload');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'contentType' => 'image/gif',
            'fileSize' => 10,
        ]));
        $response = $controller->initiatePosterUpload('v1', $request);

        static::assertSame(400, $response->getStatusCode());
    }

    #[DataProvider('supportedPosterContentTypeProvider')]
    public function testInitiatePosterUploadAcceptsSupportedContentTypes(string $contentType): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('initiatePosterUpload')
            ->with('v1', ['contentType' => $contentType, 'fileSize' => 10])
            ->willReturn(['key' => 'k']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'contentType' => $contentType,
            'fileSize' => 10,
        ]));
        $response = $controller->initiatePosterUpload('v1', $request);

        static::assertSame(200, $response->getStatusCode());
    }

    public static function supportedPosterContentTypeProvider(): iterable
    {
        yield 'jpeg' => ['image/jpeg'];
        yield 'png' => ['image/png'];
        yield 'webp' => ['image/webp'];
    }

    public function testSelectPosterDropsUnknownKeys(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('selectPoster')
            ->with('v1', ['source' => 'thumbnail', 'thumbnailIndex' => 2])
            ->willReturn(['poster' => ['source' => 'thumbnail']]);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'source' => 'thumbnail',
            'thumbnailIndex' => 2,
            'uuid' => 'ignored',
        ]));
        $response = $controller->selectPoster('v1', $request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testSelectThumbnailMalformedJsonReturns400(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('selectThumbnail');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], '{ not valid json');
        $response = $controller->selectThumbnail('v1', $request);

        static::assertSame(400, $response->getStatusCode());
    }

    public function testCompletePosterUploadMalformedJsonReturns400(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('completePosterUpload');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], '{ not valid json');
        $response = $controller->completePosterUpload('v1', $request);

        static::assertSame(400, $response->getStatusCode());
    }

    public function testReprocessLibraryReturnsClientData(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('reprocessLibrary')->with('lib-1', false)->willReturn(['queued' => 3]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->reprocessLibrary('lib-1', new Request());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => ['queued' => 3]], json_decode((string) $response->getContent(), true));
    }

    public function testIngestVideoUrlForwardsValidPayload(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('ingestVideoUrl')
            ->with(['library_id' => 'lib-1', 'source_url' => 'https://example.com/video.mp4', 'title' => 'Demo'])
            ->willReturn(['uuid' => 'v1', 'status' => 'processing']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'library_id' => 'lib-1',
            'source_url' => 'https://example.com/video.mp4',
            'title' => 'Demo',
        ]));
        $response = $controller->ingestVideoUrl($request);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('processing', json_decode((string) $response->getContent(), true)['data']['status']);
    }

    public function testIngestVideoUrlDropsEmptyTitle(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('ingestVideoUrl')
            ->with(['library_id' => 'lib-1', 'source_url' => 'https://example.com/video.mp4'])
            ->willReturn(['uuid' => 'v1', 'status' => 'processing']);

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'library_id' => 'lib-1',
            'source_url' => 'https://example.com/video.mp4',
            'title' => '',
        ]));
        $response = $controller->ingestVideoUrl($request);

        static::assertSame(200, $response->getStatusCode());
    }

    public function testIngestVideoUrlRejectsNonHttpsSourceUrl(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('ingestVideoUrl');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'library_id' => 'lib-1',
            'source_url' => 'http://example.com/video.mp4',
        ]));
        $response = $controller->ingestVideoUrl($request);

        static::assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        static::assertSame('400', $body['errors'][0]['status']);
    }

    public function testIngestVideoUrlRejectsMissingLibraryId(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('ingestVideoUrl');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], [], [], [], [], [], json_encode([
            'source_url' => 'https://example.com/video.mp4',
        ]));
        $response = $controller->ingestVideoUrl($request);

        static::assertSame(400, $response->getStatusCode());
    }

    /**
     * `POST /videos/ingest` and `GET|PATCH|DELETE /videos/{uuid}` share the same path shape. POST
     * must resolve to the ingest action; on the other methods "ingest" is not a UUID, so the
     * uuid-based routes must not match it.
     */
    public function testIngestRouteDoesNotCollideWithUuidRoutes(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(VideoOptimizerAdminController::class, 'attribute');
        $path = '/api/_action/scalecommerce-vo/videos/ingest';

        $postContext = new RequestContext();
        $postContext->setMethod('POST');
        $postMatch = (new UrlMatcher($routes, $postContext))->match($path);
        static::assertSame('api.action.scalecommerce-vo.videos.ingest', $postMatch['_route']);

        $getContext = new RequestContext();
        $getContext->setMethod('GET');
        $this->expectException(MethodNotAllowedException::class);
        (new UrlMatcher($routes, $getContext))->match($path);
    }

    /**
     * Every {id}/{uuid} route must only match a UUID. Otherwise "%2E%2E" reaches the client as
     * "..", and HttpClient's dot-segment removal re-targets the upstream request (issue #5).
     */
    public function testEveryIdRouteRequiresAUuid(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(VideoOptimizerAdminController::class, 'attribute');
        $checked = 0;
        foreach ($routes as $name => $route) {
            foreach (['id', 'uuid'] as $param) {
                if (!str_contains($route->getPath(), '{' . $param . '}')) {
                    continue;
                }
                $requirement = $route->getRequirement($param);
                static::assertNotNull($requirement, sprintf('Route %s has no requirement for {%s}.', $name, $param));
                static::assertMatchesRegularExpression('#^(?:' . $requirement . ')$#', self::UUID, $name);
                static::assertDoesNotMatchRegularExpression('#^(?:' . $requirement . ')$#', '..', $name);
                ++$checked;
            }
        }
        static::assertGreaterThan(10, $checked);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function traversalRequests(): iterable
    {
        yield 'poster delete' => ['DELETE', '/api/_action/scalecommerce-vo/videos/../poster'];
        yield 'library update' => ['PATCH', '/api/_action/scalecommerce-vo/libraries/..'];
        yield 'video get' => ['GET', '/api/_action/scalecommerce-vo/videos/..'];
        yield 'library videos' => ['GET', '/api/_action/scalecommerce-vo/libraries/../videos'];
    }

    #[DataProvider('traversalRequests')]
    public function testDotSegmentIdsDoNotMatchAnyRoute(string $method, string $path): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(VideoOptimizerAdminController::class, 'attribute');
        $context = new RequestContext();
        $context->setMethod($method);

        try {
            $match = (new UrlMatcher($routes, $context))->match($path);
            static::fail(sprintf('%s %s unexpectedly matched %s.', $method, $path, $match['_route']));
        } catch (ResourceNotFoundException|MethodNotAllowedException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testPlaybackReturnsCdnSourcesFromEmbed(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('vid-1')->willReturn([
            'sources' => [
                ['src' => 'https://cdn.example/v/h264/480p.mp4', 'type' => 'video/mp4', 'size' => 10],
                ['src' => 'https://cdn.example/v/hls/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls'],
                ['src' => 'https://cdn.example/v/h264/1080p.mp4', 'type' => 'video/mp4', 'size' => 30],
            ],
        ]);

        $response = (new VideoOptimizerAdminController($client))->getPlayback('vid-1');

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(
            ['data' => ['hls' => 'https://cdn.example/v/hls/master.m3u8', 'mp4' => 'https://cdn.example/v/h264/1080p.mp4']],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testPlaybackWithoutSourcesReturnsNulls(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => 'garbage']);

        $response = (new VideoOptimizerAdminController($client))->getPlayback('vid-1');

        static::assertSame(['data' => ['hls' => null, 'mp4' => null]], json_decode((string) $response->getContent(), true));
    }

    public function testPlaybackPropagatesUpstreamErrors(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new VideoOptimizerApiException(404, 'Video not found'));

        $response = (new VideoOptimizerAdminController($client))->getPlayback('vid-1');

        static::assertSame(404, $response->getStatusCode());
    }

    public function testPlaybackRouteResolves(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(VideoOptimizerAdminController::class, 'attribute');
        $context = new RequestContext();
        $context->setMethod('GET');
        $match = (new UrlMatcher($routes, $context))->match('/api/_action/scalecommerce-vo/videos/' . self::UUID . '/playback');

        static::assertSame('api.action.scalecommerce-vo.videos.playback', $match['_route']);
        static::assertSame(['scalecommerce_vo:read'], $match['_acl']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function upstreamAuthErrors(): iterable
    {
        yield 'token rejected' => [401, 'SCALECOMMERCE_VO__API_TOKEN_REJECTED'];
        yield 'token not permitted' => [403, 'SCALECOMMERCE_VO__API_TOKEN_FORBIDDEN'];
    }

    /**
     * The administration's HTTP client treats every 401 as an expired admin session (token
     * refresh + retry), so an upstream auth error must not be passed through as-is (issue #2).
     */
    #[DataProvider('upstreamAuthErrors')]
    public function testUpstreamAuthErrorBecomesBadGatewayWithOwnCode(int $upstreamStatus, string $code): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listLibraries')->willThrowException(VideoOptimizerApiException::fromResponse($upstreamStatus, ''));

        $response = (new VideoOptimizerAdminController($client))->listLibraries();

        static::assertSame(502, $response->getStatusCode());
        $error = json_decode((string) $response->getContent(), true)['errors'][0];
        static::assertSame('502', $error['status']);
        static::assertSame($code, $error['code']);
        static::assertStringStartsWith('The VideoOptimizer API', $error['detail']);
        static::assertStringContainsString('token', $error['detail']);
    }

    #[DataProvider('upstreamAuthErrors')]
    public function testUpstreamAuthErrorOnThumbnailBecomesBadGateway(int $upstreamStatus, string $code): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getThumbnailImage')->willThrowException(new VideoOptimizerApiException($upstreamStatus, 'Unauthorized'));

        $response = (new VideoOptimizerAdminController($client))->getThumbnailImage(self::UUID, '1');

        static::assertSame(502, $response->getStatusCode());
        static::assertSame($code, json_decode((string) $response->getContent(), true)['errors'][0]['code']);
    }

    public function testInvalidRequestOnThumbnailReturns400(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getThumbnailImage')->willThrowException(new InvalidRequestException('Invalid VideoOptimizer ID.'));

        $response = (new VideoOptimizerAdminController($client))->getThumbnailImage(self::UUID, '1');

        static::assertSame(400, $response->getStatusCode());
    }

    /**
     * 403 also means "plan limit reached", "not available during the trial" etc.; that upstream
     * message must reach the user instead of a misleading "check the token" hint.
     */
    public function testUpstream403KeepsTheUpstreamMessage(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('createLibrary')->willThrowException(new VideoOptimizerApiException(403, 'Library limit of the plan reached'));

        $response = (new VideoOptimizerAdminController($client))->createLibrary(new Request([], [], [], [], [], [], '{"name":"x"}'));

        static::assertSame(502, $response->getStatusCode());
        $error = json_decode((string) $response->getContent(), true)['errors'][0];
        static::assertSame('SCALECOMMERCE_VO__API_TOKEN_FORBIDDEN', $error['code']);
        static::assertSame('Library limit of the plan reached', $error['detail']);
    }

    /**
     * @return iterable<string, array{\RuntimeException, string}>
     */
    public static function clientErrors(): iterable
    {
        yield 'missing token' => [new MissingApiTokenException(), 'SCALECOMMERCE_VO__API_TOKEN_MISSING'];
        yield 'invalid base url' => [new InvalidApiBaseUrlException(), 'SCALECOMMERCE_VO__INVALID_BASE_URL'];
        yield 'invalid request' => [new InvalidRequestException('library_id is required.'), 'SCALECOMMERCE_VO__INVALID_REQUEST'];
    }

    /**
     * The administration tells these cases apart to show the right hint (issue #11).
     */
    #[DataProvider('clientErrors')]
    public function testClientErrorsCarryAnOwnCode(\RuntimeException $exception, string $code): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('listLibraries')->willThrowException($exception);

        $response = (new VideoOptimizerAdminController($client))->listLibraries();

        static::assertSame(400, $response->getStatusCode());
        static::assertSame($code, json_decode((string) $response->getContent(), true)['errors'][0]['code']);
    }

    public function testListVideosWithLimitReturnsOnePageAndMeta(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('listVideos');
        $client->expects(static::once())->method('listVideosPage')->with(self::UUID, 'c1', 60)->willReturn([
            'items' => [['uuid' => 'v1']],
            'pagination' => ['next_cursor' => 'c2', 'has_more' => true],
        ]);

        $response = (new VideoOptimizerAdminController($client))->listVideos(self::UUID, new Request(['limit' => '60', 'cursor' => 'c1']));

        static::assertSame(
            ['data' => [['uuid' => 'v1']], 'meta' => ['next_cursor' => 'c2', 'has_more' => true]],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testListVideosWithoutPagingKeepsTheFullList(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('listVideos')->with(self::UUID)->willReturn([['uuid' => 'v1']]);

        $response = (new VideoOptimizerAdminController($client))->listVideos(self::UUID, new Request());

        static::assertSame(['data' => [['uuid' => 'v1']]], json_decode((string) $response->getContent(), true));
    }

    public function testListVideosRejectsAnInvalidLimit(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('listVideosPage');

        $response = (new VideoOptimizerAdminController($client))->listVideos(self::UUID, new Request(['limit' => '500']));

        static::assertSame(400, $response->getStatusCode());
    }

    public function testCreateLibraryPassesEncodingQuality(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('createLibrary')
            ->with(['name' => 'Lib', 'encoding_quality' => 'quality'])
            ->willReturn(['id' => self::UUID]);

        $response = (new VideoOptimizerAdminController($client))->createLibrary(new Request([], [], [], [], [], [], '{"name":"Lib","encoding_quality":"quality","foo":"bar"}'));

        static::assertSame(200, $response->getStatusCode());
    }

    public function testUnknownEncodingQualityIsRejected(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('updateLibrary');

        $response = (new VideoOptimizerAdminController($client))->updateLibrary(self::UUID, new Request([], [], [], [], [], [], '{"encoding_quality":"ultra"}'));

        static::assertSame(400, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function reprocessBodies(): iterable
    {
        yield 'no body' => ['', false];
        yield 'force' => ['{"force":true}', true];
        yield 'force false' => ['{"force":false}', false];
    }

    #[DataProvider('reprocessBodies')]
    public function testReprocessPassesForce(string $body, bool $force): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('reprocessLibrary')->with(self::UUID, $force)->willReturn(['queued' => 3]);

        $response = (new VideoOptimizerAdminController($client))->reprocessLibrary(self::UUID, new Request([], [], [], [], [], [], $body));

        static::assertSame(200, $response->getStatusCode());
    }

    /**
     * The API rejects YouTube page links for URL import with data.code "source_url_video_platform";
     * the administration shows its own hint for it.
     */
    public function testRejectedVideoPlatformLinkGetsOwnCode(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('ingestVideoUrl')->willThrowException(VideoOptimizerApiException::fromResponse(
            400,
            '{"statusCode":400,"message":"YouTube links cannot be imported.","data":{"code":"source_url_video_platform","platform":"youtube"}}'
        ));

        $response = (new VideoOptimizerAdminController($client))->ingestVideoUrl(new Request([], [], [], [], [], [], '{"library_id":"' . self::UUID . '","source_url":"https://www.youtube.com/watch?v=x"}'));

        static::assertSame(400, $response->getStatusCode());
        $error = json_decode((string) $response->getContent(), true)['errors'][0];
        static::assertSame('SCALECOMMERCE_VO__SOURCE_URL_VIDEO_PLATFORM', $error['code']);
        static::assertSame('YouTube links cannot be imported.', $error['detail']);
    }
}
