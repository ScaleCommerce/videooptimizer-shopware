<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Controller;

use ScaleCommerce\VideoOptimizer\Service\EmbedSources;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidRequestException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class VideoOptimizerAdminController
{
    /**
     * Content types the thumbnail proxy will pass through as-is; anything else is rewritten to
     * application/octet-stream so the admin SPA never renders an upstream-controlled response as
     * e.g. text/html.
     */
    private const ALLOWED_THUMBNAIL_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Content types the poster upload proxy accepts, mirroring the upstream API's own poster
     * validation - narrower than ALLOWED_THUMBNAIL_CONTENT_TYPES since posters never accept gif.
     */
    private const ALLOWED_POSTER_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const LIBRARY_PAYLOAD_KEYS = ['name', 'description', 'codec', 'resolutions', 'encoding_quality'];

    private const ENCODING_QUALITIES = ['performance', 'quality'];

    private const INGEST_PAYLOAD_KEYS = ['library_id', 'source_url', 'title'];

    /**
     * Route requirement for {id}/{uuid}: libraries and videos are UUIDs upstream. Without it, "%2E%2E"
     * reaches the client as ".." and HttpClient's dot-segment removal re-targets the upstream request.
     */
    private const UUID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    /**
     * Route requirement for a subtitle language code (BCP 47 tag, at most 35 characters).
     */
    private const LANGUAGE_PATTERN = '[A-Za-z0-9][A-Za-z0-9_-]{0,34}';

    private const MAX_TAGS = 20;

    private const MAX_TAG_LENGTH = 50;

    private const MAX_METADATA_KEYS = 10;

    private const MAX_METADATA_FILTERS = 5;

    private const MAX_METADATA_VALUE_LENGTH = 64;

    /**
     * Metadata keys: 1-40 characters of a-z 0-9 _ . - starting with a letter or digit.
     */
    private const METADATA_KEY_PATTERN = '/^[a-z0-9][a-z0-9_.-]{0,39}$/D';

    private const SUBTITLE_MAX_BYTES = 2097152;

    private const SUBTITLE_KINDS = ['subtitles', 'captions'];

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
        return $this->wrap(fn () => $this->client->createLibrary($this->libraryPayload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}', name: 'api.action.scalecommerce-vo.libraries.update', methods: ['PATCH'], requirements: ['id' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function updateLibrary(string $id, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateLibrary($id, $this->libraryPayload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}', name: 'api.action.scalecommerce-vo.libraries.delete', methods: ['DELETE'], requirements: ['id' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:delete']])]
    public function deleteLibrary(string $id): JsonResponse
    {
        return $this->wrap(function () use ($id): array {
            $this->client->deleteLibrary($id);

            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}/reprocess', name: 'api.action.scalecommerce-vo.libraries.reprocess', methods: ['POST'], requirements: ['id' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function reprocessLibrary(string $id, Request $request): JsonResponse
    {
        // Optional {"force": true}: re-encode finished renditions too (needed after changing encoding_quality).
        return $this->wrap(fn () => $this->client->reprocessLibrary($id, ($this->payload($request)['force'] ?? false) === true));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/libraries/{id}/videos', name: 'api.action.scalecommerce-vo.videos.list', methods: ['GET'], requirements: ['id' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listVideos(string $id, Request $request): JsonResponse
    {
        // With ?limit (and ?cursor) one page plus "meta" is returned; without them, the full list
        // as before, so existing callers keep working.
        if (!$request->query->has('limit') && !$request->query->has('cursor')) {
            return $this->wrap(fn () => $this->client->listVideos($id, $this->tagFilter($request), $this->metadataFilter($request)));
        }

        $limit = (string) $request->query->get('limit', '60');
        $cursor = $request->query->get('cursor');
        if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 100) {
            return $this->clientError(new InvalidRequestException('limit must be between 1 and 100.'));
        }

        try {
            $page = $this->client->listVideosPage($id, is_string($cursor) ? $cursor : null, (int) $limit, $this->tagFilter($request), $this->metadataFilter($request));
        } catch (MissingApiTokenException|InvalidApiBaseUrlException|InvalidRequestException $e) {
            return $this->clientError($e);
        } catch (VideoOptimizerApiException $e) {
            return $this->apiError($e);
        }

        return new JsonResponse(['data' => $page['items'], 'meta' => $page['pagination']]);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos', name: 'api.action.scalecommerce-vo.videos.list-all', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listAllVideos(Request $request): JsonResponse
    {
        $libraryId = $request->query->get('libraryId');

        return $this->wrap(fn () => $this->client->listAllVideos($libraryId !== null ? (string) $libraryId : null, $this->tagFilter($request), $this->metadataFilter($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/tags', name: 'api.action.scalecommerce-vo.tags.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listTags(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listTags());
    }

    #[Route(path: '/api/_action/scalecommerce-vo/savings', name: 'api.action.scalecommerce-vo.savings', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getSavings(Request $request): JsonResponse
    {
        $param = static function (string $name) use ($request): ?string {
            $value = $request->query->get($name);

            return is_string($value) && $value !== '' ? $value : null;
        };

        return $this->wrap(fn () => $this->client->getSavings($param('from'), $param('to'), $param('libraryId'), $param('videoUuid')));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/encodings', name: 'api.action.scalecommerce-vo.encodings.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listEncodings(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listEncodings());
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/upload/initiate', name: 'api.action.scalecommerce-vo.videos.upload-initiate', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function initiateUpload(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->initiateUpload($this->only($this->payload($request), ['libraryId', 'filename', 'contentType', 'fileSize'])));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/upload/complete', name: 'api.action.scalecommerce-vo.videos.upload-complete', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function completeUpload(Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->completeUpload($this->only($this->payload($request), ['libraryId', 'uuid', 'key', 'uploadId', 'title', 'parts'])));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/ingest', name: 'api.action.scalecommerce-vo.videos.ingest', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:create']])]
    public function ingestVideoUrl(Request $request): JsonResponse
    {
        // payload() must run inside the wrap() closure - see selectThumbnail() above.
        return $this->wrap(function () use ($request): array {
            $payload = $this->only($this->payload($request), self::INGEST_PAYLOAD_KEYS);
            $this->assertValidIngestPayload($payload);
            if (($payload['title'] ?? '') === '') {
                unset($payload['title']);
            }

            return $this->client->ingestVideoUrl($payload);
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.get', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getVideo(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->getVideo($uuid));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.update', methods: ['PATCH'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function updateVideo(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->updateVideo($uuid, $this->videoPayload($request)));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}', name: 'api.action.scalecommerce-vo.videos.delete', methods: ['DELETE'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:delete']])]
    public function deleteVideo(string $uuid): JsonResponse
    {
        return $this->wrap(function () use ($uuid): array {
            $this->client->deleteVideo($uuid);

            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    /**
     * Direct CDN sources for the admin preview player. The `hls_url` on the video record points at
     * the API, which redirects to the CDN without CORS headers, so browsers can't load it via XHR;
     * the embed payload carries the CDN URLs themselves.
     */
    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/playback', name: 'api.action.scalecommerce-vo.videos.playback', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getPlayback(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(function () use ($uuid, $request): array {
            if ($request->query->getBoolean('fresh')) {
                // A video that just became ready is cached without sources for a minute.
                $this->client->forgetEmbed($uuid);
            }
            $embed = $this->client->getEmbed($uuid);
            $sources = is_array($embed['sources'] ?? null) ? $embed['sources'] : [];

            return [
                'hls' => EmbedSources::hls($sources),
                'mp4' => EmbedSources::mp4($sources),
            ];
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnails', name: 'api.action.scalecommerce-vo.thumbnails.list', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listThumbnails(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listThumbnails($uuid));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnails/{index}', name: 'api.action.scalecommerce-vo.thumbnails.image', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN, 'index' => '\d+'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function getThumbnailImage(string $uuid, string $index): Response
    {
        try {
            $image = $this->client->getThumbnailImage($uuid, (int) $index);

            return new Response($image['content'], Response::HTTP_OK, [
                'Content-Type' => $this->safeThumbnailContentType($image['contentType']),
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline',
            ]);
        } catch (MissingApiTokenException|InvalidApiBaseUrlException|InvalidRequestException $e) {
            return $this->clientError($e);
        } catch (VideoOptimizerApiException $e) {
            return $this->apiError($e);
        }
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/thumbnail', name: 'api.action.scalecommerce-vo.thumbnail.select', methods: ['POST'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function selectThumbnail(string $uuid, Request $request): JsonResponse
    {
        // payload() must run inside the wrap() closure so malformed JSON is caught there instead
        // of throwing an uncaught JsonException before wrap() is even called.
        return $this->wrap(function () use ($uuid, $request): array {
            $index = (int) ($this->payload($request)['thumbnailIndex'] ?? 0);

            return $this->client->selectThumbnail($uuid, $index);
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/initiate', name: 'api.action.scalecommerce-vo.poster.initiate', methods: ['POST'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function initiatePosterUpload(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(function () use ($uuid, $request): array {
            $payload = $this->only($this->payload($request), ['contentType', 'fileSize']);
            $this->assertValidPosterContentType($payload);

            return $this->client->initiatePosterUpload($uuid, $payload);
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/complete', name: 'api.action.scalecommerce-vo.poster.complete', methods: ['POST'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function completePosterUpload(string $uuid, Request $request): JsonResponse
    {
        // payload() must run inside the wrap() closure - see selectThumbnail() above.
        return $this->wrap(function () use ($uuid, $request): array {
            $key = (string) ($this->payload($request)['key'] ?? '');

            return $this->client->completePosterUpload($uuid, $key);
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster/select', name: 'api.action.scalecommerce-vo.poster.select', methods: ['POST'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function selectPoster(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(fn () => $this->client->selectPoster($uuid, $this->only($this->payload($request), ['source', 'thumbnailIndex'])));
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/poster', name: 'api.action.scalecommerce-vo.poster.delete', methods: ['DELETE'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function deletePoster(string $uuid): JsonResponse
    {
        return $this->wrap(function () use ($uuid): array {
            $this->client->deletePoster($uuid);

            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/subtitle-languages', name: 'api.action.scalecommerce-vo.subtitle-languages.list', methods: ['GET'], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listSubtitleLanguages(): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listSubtitleLanguages());
    }

    #[Route(path: '/api/_action/scalecommerce-vo/subtitle-languages', name: 'api.action.scalecommerce-vo.subtitle-languages.create', methods: ['POST'], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function createSubtitleLanguage(Request $request): JsonResponse
    {
        return $this->wrap(function () use ($request): array {
            $payload = $this->payload($request);
            $label = $payload['label'] ?? null;

            return $this->client->createSubtitleLanguage((string) ($payload['code'] ?? ''), is_string($label) ? $label : null);
        });
    }

    /**
     * 409 `subtitle_language_in_use` (videos still carry the language) keeps its status and gets an
     * own error code with the number of videos, so the administration can say what to do.
     */
    #[Route(path: '/api/_action/scalecommerce-vo/subtitle-languages/{code}', name: 'api.action.scalecommerce-vo.subtitle-languages.delete', methods: ['DELETE'], requirements: ['code' => self::LANGUAGE_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:delete']])]
    public function deleteSubtitleLanguage(string $code): JsonResponse
    {
        return $this->wrap(function () use ($code): array {
            $this->client->deleteSubtitleLanguage($code);

            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/subtitles', name: 'api.action.scalecommerce-vo.subtitles.list', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function listSubtitles(string $uuid): JsonResponse
    {
        return $this->wrap(fn () => $this->client->listSubtitles($uuid));
    }

    /**
     * Multipart upload from the administration (field `file` plus `language`, optional `kind` and
     * `is_default`); the file is relayed to the API, the token stays on the server.
     */
    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/subtitles', name: 'api.action.scalecommerce-vo.subtitles.upload', methods: ['POST'], requirements: ['uuid' => self::UUID_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function uploadSubtitle(string $uuid, Request $request): JsonResponse
    {
        return $this->wrap(function () use ($uuid, $request): array {
            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                throw new InvalidRequestException('A subtitle file is required.');
            }
            if (!preg_match('/\.(vtt|srt)$/i', $file->getClientOriginalName())) {
                throw new InvalidRequestException('The subtitle file must be a .vtt or .srt file.');
            }
            if ($file->getSize() > self::SUBTITLE_MAX_BYTES) {
                throw new InvalidRequestException('The subtitle file must not be larger than 2 MB.');
            }
            $language = $request->request->get('language');
            if (!is_string($language) || $language === '') {
                throw new InvalidRequestException('language is required.');
            }
            $kind = $request->request->get('kind');
            if ($kind !== null && $kind !== '' && !in_array($kind, self::SUBTITLE_KINDS, true)) {
                throw new InvalidRequestException('kind must be "subtitles" or "captions".');
            }
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                throw new InvalidRequestException('The subtitle file could not be read.');
            }

            return $this->client->uploadSubtitle(
                $uuid,
                basename($file->getClientOriginalName()),
                $content,
                $language,
                is_string($kind) && $kind !== '' ? $kind : null,
                // Only when sent: the API keeps the existing value otherwise.
                $request->request->has('is_default') ? filter_var($request->request->get('is_default'), FILTER_VALIDATE_BOOLEAN) : null,
            );
        });
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/subtitles/{language}', name: 'api.action.scalecommerce-vo.subtitles.download', methods: ['GET'], requirements: ['uuid' => self::UUID_PATTERN, 'language' => self::LANGUAGE_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:read']])]
    public function downloadSubtitle(string $uuid, string $language): Response
    {
        try {
            return new Response($this->client->downloadSubtitle($uuid, $language), Response::HTTP_OK, [
                // Never rendered by the browser: always a download.
                'Content-Type' => 'text/vtt; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'attachment; filename="subtitles-' . $language . '.vtt"',
            ]);
        } catch (MissingApiTokenException|InvalidApiBaseUrlException|InvalidRequestException $e) {
            return $this->clientError($e);
        } catch (VideoOptimizerApiException $e) {
            return $this->apiError($e);
        }
    }

    #[Route(path: '/api/_action/scalecommerce-vo/videos/{uuid}/subtitles/{language}', name: 'api.action.scalecommerce-vo.subtitles.delete', methods: ['DELETE'], requirements: ['uuid' => self::UUID_PATTERN, 'language' => self::LANGUAGE_PATTERN], defaults: ['_acl' => ['scalecommerce_vo:update']])]
    public function deleteSubtitle(string $uuid, string $language): JsonResponse
    {
        return $this->wrap(function () use ($uuid, $language): array {
            $this->client->deleteSubtitle($uuid, $language);

            return [];
        }, Response::HTTP_NO_CONTENT);
    }

    /**
     * PATCH body of a video: `title`, `tags` (replaces the tag set) and `metadata` (merged; an
     * empty value or null removes a key, `"metadata": null` removes all of them).
     *
     * @return array<string, mixed>
     */
    private function videoPayload(Request $request): array
    {
        $payload = $this->only($this->payload($request), ['title', 'tags', 'metadata']);

        if (\array_key_exists('tags', $payload)) {
            $payload['tags'] = $this->assertTags($payload['tags']);
        }
        if (\array_key_exists('metadata', $payload)) {
            if ($payload['metadata'] === null) {
                return $payload;
            }
            $metadata = $this->assertMetadata($payload['metadata']);
            if ($metadata === []) {
                unset($payload['metadata']);
            } else {
                // An object even for numeric keys ("0" would turn the array into a JSON list).
                $payload['metadata'] = (object) $metadata;
            }
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function assertTags(mixed $tags): array
    {
        if (!is_array($tags) || !array_is_list($tags) || count($tags) > self::MAX_TAGS) {
            throw new InvalidRequestException(sprintf('tags must be a list of at most %d names.', self::MAX_TAGS));
        }
        $result = [];
        foreach ($tags as $tag) {
            $name = is_string($tag) ? trim($tag) : '';
            if ($name === '' || mb_strlen($name) > self::MAX_TAG_LENGTH || str_contains($name, ',')) {
                throw new InvalidRequestException(sprintf('A tag must have 1-%d characters and must not contain a comma.', self::MAX_TAG_LENGTH));
            }
            $result[] = $name;
        }

        return $result;
    }

    /**
     * @return array<string, string|null>
     */
    private function assertMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            throw new InvalidRequestException('metadata must be an object.');
        }
        // The limit counts the keys that are set; removals (null or empty) are free.
        $set = array_filter($metadata, static fn (mixed $value): bool => $value !== null && $value !== '');
        if (count($set) > self::MAX_METADATA_KEYS) {
            throw new InvalidRequestException(sprintf('metadata can set at most %d keys.', self::MAX_METADATA_KEYS));
        }
        $result = [];
        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            if (preg_match(self::METADATA_KEY_PATTERN, $key) !== 1) {
                throw new InvalidRequestException('A metadata key must have 1-40 characters of a-z, 0-9, "_", "." and "-", starting with a letter or digit.');
            }
            if ($value !== null && (!is_string($value) || mb_strlen(trim($value)) > self::MAX_METADATA_VALUE_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $value) === 1)) {
                throw new InvalidRequestException(sprintf('A metadata value must be a string of at most %d characters without control characters.', self::MAX_METADATA_VALUE_LENGTH));
            }
            $result[$key] = $value === null ? null : trim($value);
        }

        return $result;
    }

    /**
     * Video list filter `?tags=a,b`.
     *
     * @return list<string>
     */
    private function tagFilter(Request $request): array
    {
        $raw = $request->query->get('tags');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        return $this->assertTags(array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $tag): bool => $tag !== '')));
    }

    /**
     * Video list filter `?metadata[key]=value`.
     *
     * @return array<string, string>
     */
    private function metadataFilter(Request $request): array
    {
        $raw = $request->query->all()['metadata'] ?? [];
        if ($raw === []) {
            return [];
        }
        if (!is_array($raw)) {
            throw new InvalidRequestException('Invalid metadata filter.');
        }
        if (count($raw) > self::MAX_METADATA_FILTERS) {
            throw new InvalidRequestException(sprintf('At most %d metadata filters are allowed.', self::MAX_METADATA_FILTERS));
        }
        $result = [];
        foreach ($raw as $key => $value) {
            $key = (string) $key;
            if (preg_match(self::METADATA_KEY_PATTERN, $key) !== 1 || !is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > self::MAX_METADATA_VALUE_LENGTH) {
                throw new InvalidRequestException('Invalid metadata filter.');
            }
            $result[$key] = trim($value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function libraryPayload(Request $request): array
    {
        $payload = $this->only($this->payload($request), self::LIBRARY_PAYLOAD_KEYS);
        if (\array_key_exists('encoding_quality', $payload) && !\in_array($payload['encoding_quality'], self::ENCODING_QUALITIES, true)) {
            throw new InvalidRequestException('encoding_quality must be "performance" or "quality".');
        }

        return $payload;
    }

    /**
     * Compares the media type before any ";" parameter, case-insensitively, against the allowlist.
     */
    private function safeThumbnailContentType(string $contentType): string
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return in_array($mediaType, self::ALLOWED_THUMBNAIL_CONTENT_TYPES, true) ? $contentType : 'application/octet-stream';
    }

    /**
     * Drops any request-body keys the client didn't ask for before forwarding the payload upstream,
     * so an unexpected field can't reach an endpoint that never validated it.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    private function only(array $payload, array $keys): array
    {
        return array_intersect_key($payload, array_flip($keys));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertValidIngestPayload(array $payload): void
    {
        $libraryId = $payload['library_id'] ?? null;
        if (!is_string($libraryId) || $libraryId === '') {
            throw new InvalidRequestException('library_id is required.');
        }

        $sourceUrl = $payload['source_url'] ?? null;
        if (!is_string($sourceUrl) || !str_starts_with($sourceUrl, 'https://')) {
            throw new InvalidRequestException('source_url must be an absolute https URL.');
        }
        $parts = parse_url($sourceUrl);
        $host = $parts['host'] ?? null;
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || !is_string($host) || $host === '') {
            throw new InvalidRequestException('source_url must be an absolute https URL.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertValidPosterContentType(array $payload): void
    {
        $contentType = $payload['contentType'] ?? null;
        if (!is_string($contentType) || !in_array($contentType, self::ALLOWED_POSTER_CONTENT_TYPES, true)) {
            throw new InvalidRequestException('contentType must be one of: ' . implode(', ', self::ALLOWED_POSTER_CONTENT_TYPES) . '.');
        }
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
        } catch (MissingApiTokenException|InvalidApiBaseUrlException|InvalidRequestException $e) {
            return $this->clientError($e);
        } catch (VideoOptimizerApiException $e) {
            return $this->apiError($e);
        } catch (\JsonException) {
            return new JsonResponse(['errors' => [['status' => '400', 'detail' => 'Invalid JSON body.']]], 400);
        }
    }

    /**
     * Upstream auth errors are not passed through as 401/403: the administration's HTTP client
     * treats every 401 as an expired admin session (token refresh + retry), and 403 would read as
     * a missing Shopware privilege. Both become 502 with an own error code and an actionable text.
     */
    private function apiError(VideoOptimizerApiException $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $error = ['status' => (string) $status, 'detail' => $e->getMessage()];
        if ($e->getApiCode() === 'source_url_video_platform') {
            // URL import of a YouTube & co. page link; the administration shows its own hint.
            $error['code'] = 'SCALECOMMERCE_VO__SOURCE_URL_VIDEO_PLATFORM';
        }

        if ($e->getApiCode() === 'subtitle_language_in_use') {
            // Videos still carry this language; they have to lose their subtitles first.
            $error['code'] = 'SCALECOMMERCE_VO__SUBTITLE_LANGUAGE_IN_USE';
            $videos = $e->getApiDetails()['videos'] ?? null;
            if (is_int($videos)) {
                $error['meta'] = ['videos' => $videos];
            }
        }

        if ($status === Response::HTTP_UNAUTHORIZED || $status === Response::HTTP_FORBIDDEN) {
            $status = Response::HTTP_BAD_GATEWAY;
            $error = $e->getStatusCode() === Response::HTTP_UNAUTHORIZED
                ? [
                    'status' => (string) $status,
                    'code' => 'SCALECOMMERCE_VO__API_TOKEN_REJECTED',
                    'detail' => 'The VideoOptimizer API rejected the API token. Check the token in the plugin configuration.',
                ]
                : [
                    'status' => (string) $status,
                    'code' => 'SCALECOMMERCE_VO__API_TOKEN_FORBIDDEN',
                    // 403 also covers plan limits, trial restrictions and suspended organisations;
                    // their upstream message (already plain text) says more than a generic hint.
                    'detail' => $e->getMessage() !== VideoOptimizerApiException::genericMessage(Response::HTTP_FORBIDDEN)
                        ? $e->getMessage()
                        : 'The VideoOptimizer API token is not permitted to perform this action. Check the token permissions in VideoOptimizer.',
                ];
        }

        return new JsonResponse(['errors' => [$error]], $status);
    }

    /**
     * Configuration and request errors carry an own code, so the administration can tell a missing
     * token from a misconfigured base URL and point to the plugin configuration.
     */
    private function clientError(\RuntimeException $e): JsonResponse
    {
        $code = match (true) {
            $e instanceof MissingApiTokenException => 'SCALECOMMERCE_VO__API_TOKEN_MISSING',
            $e instanceof InvalidApiBaseUrlException => 'SCALECOMMERCE_VO__INVALID_BASE_URL',
            default => 'SCALECOMMERCE_VO__INVALID_REQUEST',
        };

        return new JsonResponse(['errors' => [['status' => '400', 'code' => $code, 'detail' => $e->getMessage()]]], 400);
    }
}
