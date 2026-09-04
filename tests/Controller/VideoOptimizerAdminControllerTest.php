<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

class VideoOptimizerAdminControllerTest extends TestCase
{
    public function testListLibrariesReturnsClientData(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('listLibraries')->willReturn([['id' => 'lib-1']]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listLibraries();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => [['id' => 'lib-1']]], json_decode((string) $response->getContent(), true));
    }

    public function testApiExceptionBecomesErrorJson(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('listLibraries')->willThrowException(new VideoOptimizerApiException(429, 'Too Many Requests'));

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listLibraries();

        static::assertSame(429, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        static::assertSame('Too Many Requests', $body['errors'][0]['detail']);
    }

    public function testMissingTokenReturns400(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
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
        $client->method('listAllVideos')->with(null)->willReturn([['uuid' => 'v1']]);

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
        $client = $this->createMock(VideoOptimizerClient::class);
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
        $client->method('listThumbnails')->with('v1')->willReturn(['thumbnails' => [['index' => 0]]]);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->listThumbnails('v1');
        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['data' => ['thumbnails' => [['index' => 0]]]], json_decode((string) $response->getContent(), true));
    }

    public function testGetThumbnailImageStreamsBytes(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => 'IMG', 'contentType' => 'image/jpeg']);
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
        $client = $this->createMock(VideoOptimizerClient::class);
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
        $client->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => '<script>evil()</script>', 'contentType' => 'text/html']);
        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->getThumbnailImage('v1', '2');

        static::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        static::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        static::assertSame('inline', $response->headers->get('Content-Disposition'));
    }

    public function testGetThumbnailImageContentTypeComparisonIsCaseInsensitive(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getThumbnailImage')->with('v1', 2)->willReturn(['content' => 'IMG', 'contentType' => 'IMAGE/JPEG']);
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
        $client->expects(static::once())->method('reprocessLibrary')->with('lib-1')->willReturn(['queued' => 3]);

        $controller = new VideoOptimizerAdminController($client);
        $response = $controller->reprocessLibrary('lib-1');

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
     * `POST /videos/ingest` and `GET|PATCH|DELETE /videos/{uuid}` share the same path shape, so
     * this loads the real route attributes and asserts Symfony's matcher resolves POST to the new
     * ingest action while GET/PATCH/DELETE on the same literal path still resolve to the
     * uuid-based actions (Symfony filters candidate routes by method, so no requirements pattern
     * is needed on {uuid}).
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
        $getMatch = (new UrlMatcher($routes, $getContext))->match($path);
        static::assertSame('api.action.scalecommerce-vo.videos.get', $getMatch['_route']);
        static::assertSame('ingest', $getMatch['uuid']);
    }
}
