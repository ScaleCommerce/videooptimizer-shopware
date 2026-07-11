<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Controller;

use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('content')]
class VideoOptimizerAdminController
{
    public function __construct(private readonly VideoOptimizerClient $client)
    {
    }

    #[Route(path: '/api/_action/videooptimizer/libraries', name: 'api.action.videooptimizer.libraries.list', methods: ['GET'], defaults: ['_acl' => ['video_optimizer:read']])]
    public function listLibraries(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listLibraries());
    }

    #[Route(path: '/api/_action/videooptimizer/libraries', name: 'api.action.videooptimizer.libraries.create', methods: ['POST'], defaults: ['_acl' => ['video_optimizer:create']])]
    public function createLibrary(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->createLibrary($this->payload($request)));
    }

    #[Route(path: '/api/_action/videooptimizer/libraries/{id}', name: 'api.action.videooptimizer.libraries.update', methods: ['PATCH'], defaults: ['_acl' => ['video_optimizer:update']])]
    public function updateLibrary(string $id, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateLibrary($id, $this->payload($request)));
    }

    #[Route(path: '/api/_action/videooptimizer/libraries/{id}', name: 'api.action.videooptimizer.libraries.delete', methods: ['DELETE'], defaults: ['_acl' => ['video_optimizer:delete']])]
    public function deleteLibrary(string $id): JsonResponse
    {
        return $this->wrap(function () use ($id): array {
            $this->client->deleteLibrary($id);
            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/videooptimizer/libraries/{id}/videos', name: 'api.action.videooptimizer.videos.list', methods: ['GET'], defaults: ['_acl' => ['video_optimizer:read']])]
    public function listVideos(string $id): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listVideos($id));
    }

    #[Route(path: '/api/_action/videooptimizer/videos', name: 'api.action.videooptimizer.videos.upload', methods: ['POST'], defaults: ['_acl' => ['video_optimizer:create']])]
    public function uploadVideo(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $libraryId = (string) $request->request->get('libraryId');
        $title = $request->request->get('title');
        if ($file === null || $libraryId === '') {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => 'file and libraryId are required.']]], 400);
        }

        return $this->wrap(fn () => $this->client->uploadVideo($libraryId, $file, $title !== null ? (string) $title : null));
    }

    #[Route(path: '/api/_action/videooptimizer/videos/{uuid}', name: 'api.action.videooptimizer.videos.get', methods: ['GET'], defaults: ['_acl' => ['video_optimizer:read']])]
    public function getVideo(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->getVideo($uuid));
    }

    #[Route(path: '/api/_action/videooptimizer/videos/{uuid}', name: 'api.action.videooptimizer.videos.update', methods: ['PATCH'], defaults: ['_acl' => ['video_optimizer:update']])]
    public function updateVideo(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateVideo($uuid, $this->payload($request)));
    }

    #[Route(path: '/api/_action/videooptimizer/videos/{uuid}', name: 'api.action.videooptimizer.videos.delete', methods: ['DELETE'], defaults: ['_acl' => ['video_optimizer:delete']])]
    public function deleteVideo(string $uuid): JsonResponse
    {
        return $this->wrap(function () use ($uuid): array {
            $this->client->deleteVideo($uuid);
            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    private function payload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ($content === '') {
            return [];
        }

        // Malformed JSON must fail loudly instead of silently becoming an empty payload.
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    private function wrap(callable $fn, int $successStatus = Response::HTTP_OK): JsonResponse
    {
        try {
            $data = $fn();
            if ($successStatus === Response::HTTP_NO_CONTENT) {
                // 204 responses carry no body; JsonResponse(null) would emit "{}".
                $response = new JsonResponse(null, $successStatus);
                $response->setContent('');

                return $response;
            }
            return new JsonResponse(['data' => $data], $successStatus);
        } catch (MissingApiTokenException $e) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => $e->getMessage()]]], 400);
        } catch (VideoOptimizerApiException $e) {
            return new JsonResponse(['errors' => [['status' => (string) $e->getStatusCode(), 'detail' => $e->getMessage()]]], $e->getStatusCode());
        } catch (\JsonException) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => 'Invalid JSON body.']]], 400);
        }
    }
}
