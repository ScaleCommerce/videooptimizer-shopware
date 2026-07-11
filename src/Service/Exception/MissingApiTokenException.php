<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service\Exception;

class MissingApiTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('VideoOptimizer API token is not configured.');
    }
}
