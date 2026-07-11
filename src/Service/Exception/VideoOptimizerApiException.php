<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service\Exception;

class VideoOptimizerApiException extends \RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message)
    {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) && isset($decoded['message'])
            ? (string) $decoded['message']
            : sprintf('VideoOptimizer API request failed with status %d.', $statusCode);

        return new self($statusCode, $message);
    }
}
