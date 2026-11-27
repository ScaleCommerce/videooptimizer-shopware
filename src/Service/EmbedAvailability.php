<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-scoped state of the embed lookups made while rendering:
 * - "degraded": a lookup failed or returned a video without playable sources. The element is
 *   hidden then, so the page must not stay cached for the full page TTL.
 * - rendered videos: their cache tags let admin changes invalidate the cached pages.
 * See DegradedEmbedCacheSubscriber.
 */
class EmbedAvailability implements ResetInterface
{
    private bool $degraded = false;

    private bool $routeCacheTagged = false;

    /**
     * @var array<string, true>
     */
    private array $rendered = [];

    public static function cacheTag(string $uuid): string
    {
        return 'scalecommerce-vo-video-' . $uuid;
    }

    public function markRendered(string $uuid): void
    {
        $this->rendered[$uuid] = true;
    }

    /**
     * @return list<string>
     */
    public function cacheTags(): array
    {
        return array_map(self::cacheTag(...), array_keys($this->rendered));
    }

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
        $this->rendered = [];
    }
}
