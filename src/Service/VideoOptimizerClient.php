<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use ScaleCommerce\VideoOptimizer\Service\Exception\MissingApiTokenException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VideoOptimizerClient
{
    private const DEFAULT_BASE_URL = 'https://api.videooptimizer.eu/api/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public function listLibraries(): array
    {
        return $this->request('GET', '/libraries');
    }

    public function createLibrary(array $payload): array
    {
        return $this->request('POST', '/libraries', ['json' => $payload]);
    }

    public function updateLibrary(string $id, array $payload): array
    {
        return $this->request('PATCH', '/libraries/' . rawurlencode($id), ['json' => $payload]);
    }

    public function deleteLibrary(string $id): void
    {
        $this->request('DELETE', '/libraries/' . rawurlencode($id));
    }

    public function listVideos(string $libraryId): array
    {
        return $this->request('GET', '/libraries/' . rawurlencode($libraryId) . '/videos');
    }

    public function getVideo(string $uuid): array
    {
        return $this->request('GET', '/videos/' . rawurlencode($uuid));
    }

    public function updateVideo(string $uuid, array $payload): array
    {
        return $this->request('PATCH', '/videos/' . rawurlencode($uuid), ['json' => $payload]);
    }

    public function deleteVideo(string $uuid): void
    {
        $this->request('DELETE', '/videos/' . rawurlencode($uuid));
    }

    public function uploadVideo(string $libraryId, UploadedFile $file, ?string $title): array
    {
        $formData = new FormDataPart([
            'file' => DataPart::fromPath($file->getPathname(), $file->getClientOriginalName(), $file->getMimeType()),
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'x-library-id: ' . $libraryId;
        if ($title !== null && $title !== '') {
            $headers[] = 'x-title: ' . $title;
        }

        return $this->request('POST', '/videos', [
            'headers' => $headers,
            'body' => $formData->bodyToIterable(),
        ]);
    }

    public function getEmbed(string $uuid): array
    {
        return $this->request('GET', '/embed/' . rawurlencode($uuid), [], false);
    }

    private function request(string $method, string $path, array $options = [], bool $withToken = true): array
    {
        $headers = $options['headers'] ?? [];
        if ($withToken) {
            $headers[] = 'Authorization: Bearer ' . $this->token();
        }
        $options['headers'] = $headers;

        $response = $this->httpClient->request($method, $this->baseUrl() . $path, $options);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw VideoOptimizerApiException::fromResponse($status, $response->getContent(false));
        }

        $content = $response->getContent();
        if ($content === '') {
            return [];
        }
        $decoded = json_decode($content, true);

        return $decoded['data'] ?? $decoded ?? [];
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

        return rtrim($base !== '' ? $base : self::DEFAULT_BASE_URL, '/');
    }
}
