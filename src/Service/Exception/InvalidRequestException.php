<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service\Exception;

/**
 * Thrown by the admin controller for a request that fails validation before it ever reaches the
 * VideoOptimizer API (e.g. a non-https source URL). Mapped to a 400 error response by wrap(),
 * alongside MissingApiTokenException/InvalidApiBaseUrlException.
 */
class InvalidRequestException extends \RuntimeException
{
}
