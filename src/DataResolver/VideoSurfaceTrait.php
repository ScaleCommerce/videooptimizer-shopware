<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;

/**
 * Shared logic that turns a video UUID + element config into a "surface" the storefront can render:
 * player mode, a deterministic embed URL, and (for the native player) normalized HLS/MP4 sources.
 */
trait VideoSurfaceTrait
{
    /**
     * @return array{playerMode: string, embedUrl: string, embed: array<string, mixed>|null, error: bool}
     */
    protected function buildVideoSurface(FieldConfigCollection $config, string $uuid, VideoOptimizerClient $client, string $presentation = 'direct'): array
    {
        $playerMode = $config->get('playerMode')?->getValue() === 'embed' ? 'embed' : 'native';

        try {
            $embedUrl = $this->buildEmbedUrl($uuid, $config, $client);
        } catch (InvalidApiBaseUrlException) {
            // A misconfigured embed base URL must not take down the whole CMS page - surface it
            // as this element's error state instead, and skip the (equally misconfigured) upstream call.
            return ['playerMode' => $playerMode, 'embedUrl' => '', 'embed' => null, 'error' => true];
        }

        // Always verify the video still exists upstream, regardless of player mode or
        // presentation - a hosted iframe for a deleted video would otherwise render a bare 404.
        try {
            $embed = $this->normalizeEmbed($client->getEmbed($uuid));
        } catch (\Throwable) {
            return ['playerMode' => $playerMode, 'embedUrl' => $embedUrl, 'embed' => null, 'error' => true];
        }

        return ['playerMode' => $playerMode, 'embedUrl' => $embedUrl, 'embed' => $embed, 'error' => false];
    }

    protected function buildEmbedUrl(string $uuid, FieldConfigCollection $config, VideoOptimizerClient $client): string
    {
        $query = http_build_query([
            'autoplay' => $this->boolOption($config, 'autoplay', false) ? '1' : '0',
            'muted' => $this->boolOption($config, 'muted', false) ? '1' : '0',
            'loop' => $this->boolOption($config, 'loop', false) ? '1' : '0',
            'controls' => $this->boolOption($config, 'showControls', true) ? '1' : '0',
        ]);

        return $client->embedBaseUrl() . '/embed/' . rawurlencode($uuid) . '?' . $query;
    }

    protected function boolOption(FieldConfigCollection $config, string $key, bool $default): bool
    {
        $value = $config->get($key)?->getValue();

        return is_bool($value) ? $value : $default;
    }

    /**
     * Allowlists CTA URL schemes to prevent stored XSS via `javascript:`/`data:`/etc. URLs.
     * Relative URLs (path, hash, query, or schemeless) always pass; absolute URLs pass only
     * for http(s)/mailto/tel.
     */
    protected function safeUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Strip control characters and whitespace before the scheme check so an obfuscated
        // scheme like "java\nscript:" cannot slip past the allowlist below.
        $withoutControlChars = preg_replace('/[\s\x00-\x1F\x7F]/', '', $trimmed);

        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', (string) $withoutControlChars, $matches)) {
            // No scheme at all, or starts with /, #, ? - a relative URL.
            return $trimmed;
        }

        return in_array(strtolower($matches[1]), ['http', 'https', 'mailto', 'tel'], true) ? $trimmed : null;
    }

    protected function normalizeEmbed(array $embed): array
    {
        $sources = $embed['sources'] ?? [];

        return [
            'hls' => $this->pickHlsSource($sources),
            'mp4' => $this->pickMp4Source($sources),
            'poster' => $embed['poster'] ?? null,
            'title' => $embed['title'] ?? null,
            'duration' => $embed['duration'] ?? null,
            'aspectRatio' => $this->deriveAspectRatio($embed['resolution'] ?? null),
        ];
    }

    protected function deriveAspectRatio(?string $resolution): ?string
    {
        if (!is_string($resolution) || !preg_match('/^(\d+)\s*x\s*(\d+)$/i', trim($resolution), $m)) {
            return null;
        }
        if ((int) $m[1] <= 0 || (int) $m[2] <= 0) {
            return null;
        }

        return $m[1] . ' / ' . $m[2];
    }

    protected function pickHlsSource(array $sources): ?string
    {
        foreach ($sources as $source) {
            $isHls = ($source['codec'] ?? null) === 'hls' || ($source['type'] ?? null) === 'application/vnd.apple.mpegurl';
            if ($isHls && $this->isPlayableSource($source)) {
                return $source['src'];
            }
        }

        return null;
    }

    protected function pickMp4Source(array $sources): ?string
    {
        $best = null;
        $bestSize = -1;
        foreach ($sources as $source) {
            if (($source['type'] ?? null) !== 'video/mp4' || !$this->isPlayableSource($source)) {
                continue;
            }
            if (!str_ends_with((string) $source['src'], '.mp4')) {
                continue;
            }
            $size = (int) ($source['size'] ?? 0);
            if ($size > $bestSize) {
                $bestSize = $size;
                $best = $source['src'];
            }
        }

        return $best;
    }

    protected function isPlayableSource(array $source): bool
    {
        return isset($source['src']) && is_string($source['src']) && $source['src'] !== '';
    }
}
