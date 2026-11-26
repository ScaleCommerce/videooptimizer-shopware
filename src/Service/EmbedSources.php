<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/**
 * Picks playable sources out of the `sources` list of a `GET /embed/{uuid}` response. Shared by the
 * storefront resolvers and the admin playback endpoint, so both play the same CDN URLs.
 */
final class EmbedSources
{
    /**
     * @param array<int, mixed> $sources
     */
    public static function hls(array $sources): ?string
    {
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $isHls = ($source['codec'] ?? null) === 'hls' || ($source['type'] ?? null) === 'application/vnd.apple.mpegurl';
            $src = self::src($source);
            if ($isHls && $src !== null) {
                return $src;
            }
        }

        return null;
    }

    /**
     * The largest progressive MP4 rendition.
     *
     * @param array<int, mixed> $sources
     */
    public static function mp4(array $sources): ?string
    {
        $best = null;
        $bestSize = -1;
        foreach ($sources as $source) {
            if (!is_array($source) || ($source['type'] ?? null) !== 'video/mp4') {
                continue;
            }
            $src = self::src($source);
            if ($src === null || !str_ends_with($src, '.mp4')) {
                continue;
            }
            $size = is_numeric($source['size'] ?? null) ? (int) $source['size'] : 0;
            if ($size > $bestSize) {
                $bestSize = $size;
                $best = $src;
            }
        }

        return $best;
    }

    /**
     * @param array<mixed> $source
     */
    private static function src(array $source): ?string
    {
        $src = $source['src'] ?? null;

        return is_string($src) && $src !== '' ? $src : null;
    }
}
