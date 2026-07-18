<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Controller;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Symfony\Component\HttpFoundation\Request;

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
}
