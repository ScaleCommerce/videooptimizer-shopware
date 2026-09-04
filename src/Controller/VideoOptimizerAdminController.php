<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Controller;

use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
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
    /**
     * Content types the thumbnail proxy will pass through as-is; anything else is rewritten to
     * application/octet-stream so the admin SPA never renders an upstream-controlled response as
     * e.g. text/html.
     */
    private const ALLOWED_THUMBNAIL_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(private readonly VideoOptimizerClient $client)
    {
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries', name: 'api.action.scalecommerce-vo.libraries.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listLibraries(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listLibraries());
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries', name: 'api.action.scalecommerce-vo.libraries.create', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function createLibrary(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->createLibrary($this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}', name: 'api.action.scalecommerce-vo.libraries.update', methods: ['PATCH'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function updateLibrary(string $id, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateLibrary($id, $this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}', name: 'api.action.scalecommerce-vo.libraries.delete', methods: ['DELETE'], defaults: ['_acl' => ['scalecommerce_vo:delete']])]
    public function deleteLibrary(string $id): JsonResponse
    {
        return $this->wrap(function () use ($id): array {
            $this->client->deleteLibrary($id);
            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}/videos', name: 'api.action.scalecommerce-vo.videos.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listVideos(string $id): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listVideos($id));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos', name: 'api.action.scalecommerce-vo.videos.list-all', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listAllVideos(Request $request): JsonResponse
    {
        $libraryId = $request->query->get('libraryId');
        return $this->wrap(fn () => $this->client->listAllVideos($libraryId !== null ? (string) $libraryId : null));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/encodings', name: 'api.action.scalecommerce-vo.encodings.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listEncodings(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listEncodings());
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/upload/initiate', name: 'api.action.scalecommerce-vo.videos.upload-initiate', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function initiateUpload(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->initiateUpload($this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/upload/complete', name: 'api.action.scalecommerce-vo.videos.upload-complete', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function completeUpload(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->completeUpload($this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.get', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getVideo(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->getVideo($uuid));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.update', methods: ['PATCH'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function updateVideo(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateVideo($uuid, $this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.delete', methods: ['DELETE'], defaults: ['_acl' => ['scalecommerce_vo:delete']])]
    public function deleteVideo(string $uuid): JsonResponse
    {
        return $this->wrap(function () use ($uuid): array {
            $this->client->deleteVideo($uuid);
            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnails', name: 'api.action.scalecommerce-vo.thumbnails.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listThumbnails(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listThumbnails($uuid));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnails/{index}', name: 'api.action.scalecommerce-vo.thumbnails.image', methods: ['GET'], requirements: ['index' => '\d+'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getThumbnailImage(string $uuid, string $index): Response
    {
        try {
            $image = $this->client->getThumbnailImage($uuid, (int) $index);
            return new Response($image['content'], Response::HTTP_OK, [
                'Content-Type' => $this->safeThumbnailContentType($image['contentType']),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline',
            ]);
        } catch (MissingApiTokenException|InvalidApiBaseUrlException $e) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => $e->getMessage()]]], 400);
        } catch (VideoOptimizerApiException $e) {
            return new JsonResponse(['errors' => [['status' => (string) $e->getStatusCode(), 'detail' => $e->getMessage()]]], $e->getStatusCode());
        }
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnail', name: 'api.action.scalecommerce-vo.thumbnail.select', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function selectThumbnail(string $uuid, Request $request): JsonResponse
    {
        $index = (int) ($this->payload($request)['thumbnailIndex'] ?? 0);
        return $this->wrap(fn () => $this->client->selectThumbnail($uuid, $index));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/initiate', name: 'api.action.scalecommerce-vo.poster.initiate', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function initiatePosterUpload(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->initiatePosterUpload($uuid, $this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/complete', name: 'api.action.scalecommerce-vo.poster.complete', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function completePosterUpload(string $uuid, Request $request): JsonResponse
    {
        $key = (string) ($this->payload($request)['key'] ?? '');
        return $this->wrap(fn () => $this->client->completePosterUpload($uuid, $key));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/select', name: 'api.action.scalecommerce-vo.poster.select', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function selectPoster(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->selectPoster($uuid, $this->payload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster', name: 'api.action.scalecommerce-vo.poster.delete', methods: ['DELETE'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function deletePoster(string $uuid): JsonResponse
    {
        return $this->wrap(function () use ($uuid): array {
            $this->client->deletePoster($uuid);
            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    /**
     * Compares the media type before any ";" parameter, case-insensitively, against the allowlist.
     */
    private function safeThumbnailContentType(string $contentType): string
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($mediaType, self::ALLOWED_THUMBNAIL_CONTENT_TYPES, true) ? $contentType : 'application/octet-stream';
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
        } catch (MissingApiTokenException|InvalidApiBaseUrlException $e) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => $e->getMessage()]]], 400);
        } catch (VideoOptimizerApiException $e) {
            return new JsonResponse(['errors' => [['status' => (string) $e->getStatusCode(), 'detail' => $e->getMessage()]]], $e->getStatusCode());
        } catch (\JsonException) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => 'Invalid JSON body.']]], 400);
        }
    }
}
