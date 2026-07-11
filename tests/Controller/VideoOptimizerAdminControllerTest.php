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

    public function testUploadWithoutFileReturns400(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('uploadVideo');

        $controller = new VideoOptimizerAdminController($client);
        $request = new Request([], ['libraryId' => ''], [], [], [], []);
        $response = $controller->uploadVideo($request);

        static::assertSame(400, $response->getStatusCode());
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
}
