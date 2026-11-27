<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service\Exception;

class VideoOptimizerApiException extends \RuntimeException
{
    /**
     * Upstream messages are shown in admin notifications, which render (sanitized) HTML, so they
     * are reduced to short plain text.
     */
    private const MAX_MESSAGE_LENGTH = 200;

    public function __construct(private readonly int $statusCode, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function fromResponse(int $statusCode, string $body): self
    {
        $decoded = json_decode($body, true);
        $message = '';
        if (is_array($decoded)) {
            foreach (['message', 'statusMessage'] as $key) {
                if (isset($decoded[$key]) && is_string($decoded[$key])) {
                    $message = self::plainText($decoded[$key]);
                    if ($message !== '') {
                        break;
                    }
                }
            }
        }

        return new self($statusCode, $message !== '' ? $message : self::genericMessage($statusCode));
    }

    public static function genericMessage(int $statusCode): string
    {
        return sprintf('VideoOptimizer API request failed with status %d.', $statusCode);
    }

    /**
     * The API could not be reached, timed out, or answered with something that is not the
     * expected JSON. The message is neutral on purpose: the transport error text contains the full
     * upstream URL, so it is logged instead of being sent to the browser.
     */
    public static function badGateway(string $message, ?\Throwable $previous = null): self
    {
        return new self(502, $message, $previous);
    }

    private static function plainText(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 1)) . '…';
        }

        return $text;
    }
}
