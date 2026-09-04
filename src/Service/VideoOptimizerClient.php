<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class VideoOptimizerClient
{
    private const DEFAULT_BASE_URL = 'https://api.videooptimizer.eu/api/v1';
    private const DEFAULT_EMBED_BASE_URL = 'https://videooptimizer.eu';
    private const PAGE_LIMIT = 100;
    private const MAX_PAGES = 100;
    private const MAX_RETRY_AFTER = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public function listLibraries(): array
    {
        return $this->requestAllPages('/libraries');
    }

    public function createLibrary(array $payload): array
    {
        return $this->requestData('POST', '/libraries', ['json' => $payload]);
    }

    public function updateLibrary(string $id, array $payload): array
    {
        return $this->requestData('PATCH', '/libraries/' . rawurlencode($id), ['json' => $payload]);
    }

    public function deleteLibrary(string $id): void
    {
        $this->request('DELETE', '/libraries/' . rawurlencode($id));
    }

    public function listVideos(string $libraryId): array
    {
        return $this->requestAllPages('/libraries/' . rawurlencode($libraryId) . '/videos');
    }

    /**
     * Lists videos across every library (newest first), optionally filtered to one library.
     * Requires the `library:view` permission on the token.
     */
    public function listAllVideos(?string $libraryId = null): array
    {
        $query = ($libraryId !== null && $libraryId !== '') ? ['library_id' => $libraryId] : [];

        return $this->requestAllPages('/videos', $query);
    }

    /**
     * Lists the codecs and resolutions the organization may enable on a library. Returned as-is
     * ({codecs, resolutions}), NOT wrapped in { data } — so use the low-level request() directly.
     */
    public function listEncodings(): array
    {
        return $this->request('GET', '/encodings');
    }

    public function getVideo(string $uuid): array
    {
        return $this->requestData('GET', '/videos/' . rawurlencode($uuid));
    }

    public function updateVideo(string $uuid, array $payload): array
    {
        return $this->requestData('PATCH', '/videos/' . rawurlencode($uuid), ['json' => $payload]);
    }

    public function deleteVideo(string $uuid): void
    {
        $this->request('DELETE', '/videos/' . rawurlencode($uuid));
    }

    /**
     * Starts a presigned multipart upload. Returns the upload id plus per-part PUT URLs the browser
     * uploads directly to; the token never leaves the server.
     *
     * @param array<string, mixed> $payload expects libraryId, filename, contentType, fileSize
     */
    public function initiateUpload(array $payload): array
    {
        return $this->requestData('POST', '/videos/upload/initiate', ['json' => $payload]);
    }

    /**
     * Finalizes a presigned multipart upload once every part has been PUT to its presigned URL.
     *
     * @param array<string, mixed> $payload expects libraryId, uuid, key, uploadId, parts[] and optional title
     */
    public function completeUpload(array $payload): array
    {
        return $this->requestData('POST', '/videos/upload/complete', ['json' => $payload]);
    }

    /**
     * Public embed payload (theme, sources, poster). No token required. Capped so a slow upstream
     * falls back fast during storefront rendering instead of blocking the response.
     */
    public function getEmbed(string $uuid): array
    {
        return $this->requestData('GET', '/embed/' . rawurlencode($uuid), ['max_duration' => 3.0], false);
    }

    public function listThumbnails(string $uuid): array
    {
        return $this->requestData('GET', '/videos/' . rawurlencode($uuid) . '/thumbnails');
    }

    /**
     * Fetches a single thumbnail frame's raw image bytes. Per the current OpenAPI spec the frame
     * URLs are public (no token required), but the admin SPA still proxies through here because
     * it should not fetch cross-origin images with unknown CORS.
     *
     * @return array{content: string, contentType: string}
     */
    public function getThumbnailImage(string $uuid, int $index): array
    {
        $url = $this->baseUrl() . '/videos/' . rawurlencode($uuid) . '/thumbnails/' . $index;
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->buildHeaders(true, 'image/*'),
        ]);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw VideoOptimizerApiException::fromResponse($response->getStatusCode(), $response->getContent(false));
        }

        return [
            'content' => $response->getContent(),
            'contentType' => $response->getHeaders()['content-type'][0] ?? 'image/jpeg',
        ];
    }

    public function selectThumbnail(string $uuid, int $index): array
    {
        return $this->requestData('POST', '/videos/' . rawurlencode($uuid) . '/thumbnail', ['json' => ['thumbnailIndex' => $index]]);
    }

    /**
     * @param array<string, mixed> $payload expects contentType (image/jpeg|png|webp) and fileSize
     *
     * @return array<string, mixed> {key, uploadUrl}
     */
    public function initiatePosterUpload(string $uuid, array $payload): array
    {
        return $this->requestData('POST', '/videos/' . rawurlencode($uuid) . '/poster/initiate', ['json' => $payload]);
    }

    public function completePosterUpload(string $uuid, string $key): array
    {
        return $this->requestData('POST', '/videos/' . rawurlencode($uuid) . '/poster/complete', ['json' => ['key' => $key]]);
    }

    /**
     * @param array<string, mixed> $payload expects source (custom|thumbnail) and optional thumbnailIndex
     */
    public function selectPoster(string $uuid, array $payload): array
    {
        return $this->requestData('POST', '/videos/' . rawurlencode($uuid) . '/poster/select', ['json' => $payload]);
    }

    public function deletePoster(string $uuid): void
    {
        $this->request('DELETE', '/videos/' . rawurlencode($uuid) . '/poster');
    }

    /**
     * Fetches every page of a cursor-paginated list endpoint and returns the merged items.
     */
    private function requestAllPages(string $path, array $extraQuery = []): array
    {
        $items = [];
        $cursor = null;
        $previousCursor = null;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $query = $extraQuery + ['limit' => self::PAGE_LIMIT];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $response = $this->request('GET', $path, ['query' => $query]);

            $data = $response['data'] ?? null;
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
            }

            $pagination = $response['pagination'] ?? null;
            if (!is_array($pagination) || ($pagination['has_more'] ?? false) !== true) {
                break;
            }

            $cursor = is_string($pagination['next_cursor'] ?? null) ? $pagination['next_cursor'] : null;
            // Stop if the cursor is empty or stuck, so a misbehaving upstream cannot loop forever.
            if ($cursor === null || $cursor === '' || $cursor === $previousCursor) {
                break;
            }
            $previousCursor = $cursor;
        }

        return $items;
    }

    private function requestData(string $method, string $path, array $options = [], bool $withToken = true): array
    {
        $response = $this->request($method, $path, $options, $withToken);
        $payload = $response['data'] ?? null;

        return is_array($payload) ? $payload : [];
    }

    private function request(string $method, string $path, array $options = [], bool $withToken = true): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], $this->buildHeaders($withToken, 'application/json'));

        $url = $this->baseUrl() . $path;

        for ($attempt = 0; ; ++$attempt) {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();

            // Respect Retry-After once; paginated loops can trip the rate limit.
            if ($status === 429 && $attempt === 0) {
                sleep($this->retryAfterSeconds($response));
                continue;
            }
            break;
        }

        if ($status < 200 || $status >= 300) {
            throw VideoOptimizerApiException::fromResponse($status, $response->getContent(false));
        }

        $content = $response->getContent();
        if ($content === '') {
            return [];
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string> "Name: Value" header lines, the shape Symfony's HttpClient and our
     *                       own request()/getThumbnailImage() calls agree on.
     */
    private function buildHeaders(bool $withToken, string $accept): array
    {
        $headers = [];
        if ($withToken) {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        $headers[] = 'Accept: ' . $accept;

        return $headers;
    }

    private function retryAfterSeconds(ResponseInterface $response): int
    {
        $value = $response->getHeaders(false)['retry-after'][0] ?? null;
        if (!is_string($value) || !ctype_digit($value)) {
            return 1;
        }

        return min((int) $value, self::MAX_RETRY_AFTER);
    }

    private function token(): string
    {
        $token = $this->systemConfig->getString('ScaleVideoOptimizer.config.apiToken');
        if ($token === '') {
            throw new MissingApiTokenException();
        }

        return $token;
    }

    private function baseUrl(): string
    {
        $base = $this->systemConfig->getString('ScaleVideoOptimizer.config.apiBaseUrl');
        $url = rtrim($base !== '' ? $base : self::DEFAULT_BASE_URL, '/');
        $this->assertHttpsUrl($url, 'The VideoOptimizer API base URL must be an absolute https URL.');

        return $url;
    }

    /**
     * Public host that serves the hosted embed player, used to build storefront iframe/link URLs.
     */
    public function embedBaseUrl(): string
    {
        $base = $this->systemConfig->getString('ScaleVideoOptimizer.config.embedBaseUrl');
        $url = rtrim($base !== '' ? $base : self::DEFAULT_EMBED_BASE_URL, '/');
        $this->assertHttpsUrl($url, 'The VideoOptimizer embed base URL must be an absolute https URL.');

        return $url;
    }

    private function assertHttpsUrl(string $url, string $message): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        if ($scheme !== 'https' || !is_string($host) || $host === '') {
            throw new InvalidApiBaseUrlException($message);
        }
    }
}
