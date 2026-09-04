<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service\Exception;

class InvalidApiBaseUrlException extends \RuntimeException
{
    public function __construct(string $message = 'The VideoOptimizer API base URL must be an absolute https URL.')
    {
        parent::__construct($message);
    }
}
