<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/**
 * Picks playable sources out of the `sources` list of a `GET /embed/{uuid}` response. Shared by the
 * storefront resolvers and the admin playback endpoint, so both play the same CDN URLs.
 */
final class EmbedSources
{
    private const THEME_BOOLEANS = [
        'controls', 'bigPlayButton', 'fullscreenButton', 'pictureInPicture', 'playbackSpeed', 'qualitySelector',
        'volumeControl', 'timeDisplay', 'loopButton', 'autoplay', 'mutedStart', 'interactive',
    ];

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
     * The `theme` of a `GET /embed/{uuid}` response reduced to the keys and values the storefront
     * uses; colours are limited to plain CSS colour syntax, anything unknown is dropped.
     *
     * @return array<string, bool|string>
     */
    public static function theme(mixed $theme): array
    {
        if (!is_array($theme)) {
            return [];
        }
        $result = [];
        foreach (self::THEME_BOOLEANS as $key) {
            if (is_bool($theme[$key] ?? null)) {
                $result[$key] = $theme[$key];
            }
        }
        foreach (['accentColor', 'controlBarBg'] as $key) {
            $color = $theme[$key] ?? null;
            if (is_string($color) && strlen($color) <= 64
                && preg_match('/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\(\s*[0-9.%\s,\/-]+\))$/iD', trim($color)) === 1) {
                $result[$key] = trim($color);
            }
        }
        $enums = ['playButtonShape' => ['round', 'rectangle'], 'playButtonSize' => ['small', 'medium', 'large'], 'preload' => ['auto', 'metadata', 'none']];
        foreach ($enums as $key => $allowed) {
            if (in_array($theme[$key] ?? null, $allowed, true)) {
                $result[$key] = $theme[$key];
            }
        }

        return $result;
    }

    /**
     * The subtitle `tracks` of a `GET /embed/{uuid}` response as `<track>` data: https WebVTT URL,
     * BCP 47 language, label (defaults to the language), kind `subtitles` or `captions`, `default`.
     * Entries that cannot be rendered safely are dropped.
     *
     * @return list<array{src: string, srclang: string, label: string, kind: string, default: bool}>
     */
    public static function tracks(mixed $tracks): array
    {
        if (!is_array($tracks)) {
            return [];
        }
        $result = [];
        $hasDefault = false;
        foreach ($tracks as $track) {
            if (!is_array($track)) {
                continue;
            }
            $src = $track['src'] ?? null;
            $lang = $track['srclang'] ?? null;
            if (!is_string($src) || preg_match('#^https://[^\s"\'<>]+$#iD', $src) !== 1
                || !is_string($lang) || preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/D', $lang) !== 1) {
                continue;
            }
            $label = is_string($track['label'] ?? null) ? trim($track['label']) : '';
            $default = ($track['default'] ?? false) === true && !$hasDefault;
            $hasDefault = $hasDefault || $default;
            $result[] = [
                'src' => $src,
                'srclang' => $lang,
                'label' => $label !== '' ? mb_substr($label, 0, 100) : $lang,
                'kind' => ($track['kind'] ?? null) === 'captions' ? 'captions' : 'subtitles',
                'default' => $default,
            ];
        }

        return $result;
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
