<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-scoped flag: set when an embed lookup failed or returned a video without playable
 * sources while rendering. The affected element is hidden then, so the page must not stay in the
 * HTTP cache for the full page TTL (see DegradedEmbedCacheSubscriber).
 */
class EmbedAvailability implements ResetInterface
{
    private bool $degraded = false;

    private bool $routeCacheTagged = false;

    public function markDegraded(): void
    {
        $this->degraded = true;
    }

    public function isDegraded(): bool
    {
        return $this->degraded;
    }

    public function markRouteCacheTagged(): void
    {
        $this->routeCacheTagged = true;
    }

    public function isRouteCacheTagged(): bool
    {
        return $this->routeCacheTagged;
    }

    public function reset(): void
    {
        $this->degraded = false;
        $this->routeCacheTagged = false;
    }
}
