<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-scoped state of the embed lookups made while rendering:
 * - "degraded": a lookup failed or returned a video without playable sources. The element is
 *   hidden then, so the page must not stay cached for the full page TTL.
 * - rendered videos and libraries: their cache tags let admin changes invalidate the cached pages
 *   (a library tag also covers videos that a truncated or failed video listing misses).
 * See EmbedPageCacheSubscriber.
 */
class EmbedRenderState implements ResetInterface
{
    private bool $degraded = false;

    private bool $routeCacheTagged = false;

    /**
     * @var array<string, true>
     */
    private array $rendered = [];

    /**
     * @var array<string, true>
     */
    private array $renderedLibraries = [];

    /**
     * @var array<string, string>
     */
    private array $videoLibraries = [];

    public static function cacheTag(string $uuid): string
    {
        return 'scalecommerce-vo-video-' . $uuid;
    }

    public static function libraryCacheTag(string $libraryId): string
    {
        return 'scalecommerce-vo-library-' . $libraryId;
    }

    /**
     * @param string|null $uuid a video of the library that is rendered, remembered for libraryOf()
     */
    public function markLibraryRendered(string $libraryId, ?string $uuid = null): void
    {
        $this->renderedLibraries[$libraryId] = true;
        if ($uuid !== null) {
            $this->videoLibraries[$uuid] = $libraryId;
        }
    }

    /**
     * The library a rendered video was announced with (the embed payload carries none).
     */
    public function libraryOf(string $uuid): ?string
    {
        return $this->videoLibraries[$uuid] ?? null;
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
        return [
            ...array_map(self::cacheTag(...), array_keys($this->rendered)),
            ...array_map(self::libraryCacheTag(...), array_keys($this->renderedLibraries)),
        ];
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
        $this->renderedLibraries = [];
        $this->videoLibraries = [];
    }
}

// Former name (until 1.2.0): an alias, so instances satisfy type hints and instanceof checks on it.
class_alias(EmbedRenderState::class, 'ScaleCommerce\\VideoOptimizer\\Service\\EmbedAvailability');
