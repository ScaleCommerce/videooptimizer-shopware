<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;

/**
 * Shared logic that turns a video UUID + element config into a "surface" the storefront can render:
 * player mode, a deterministic embed URL, and (for the native player) normalized HLS/MP4 sources.
 */
trait VideoSurfaceTrait
{
    private const EMBED_BASE_URL = 'https://videooptimizer.eu';

    /**
     * @return array{playerMode: string, embedUrl: string, embed: array<string, mixed>|null, error: bool}
     */
    protected function buildVideoSurface(FieldConfigCollection $config, string $uuid, VideoOptimizerClient $client, string $presentation = 'direct'): array
    {
        $playerMode = $config->get('playerMode')?->getValue() === 'embed' ? 'embed' : 'native';
        $embedUrl = $this->buildEmbedUrl($uuid, $config);

        if ($playerMode === 'embed') {
            // The hosted iframe is self-contained, but facade/lightbox show a poster before the
            // click, so those still need the upstream embed data. Direct embed needs nothing.
            if ($presentation === 'direct') {
                return ['playerMode' => 'embed', 'embedUrl' => $embedUrl, 'embed' => null, 'error' => false];
            }

            try {
                return ['playerMode' => 'embed', 'embedUrl' => $embedUrl, 'embed' => $this->normalizeEmbed($client->getEmbed($uuid)), 'error' => false];
            } catch (\Throwable) {
                // No poster available, but the iframe still plays on click - not a hard error.
                return ['playerMode' => 'embed', 'embedUrl' => $embedUrl, 'embed' => null, 'error' => false];
            }
        }

        try {
            return ['playerMode' => 'native', 'embedUrl' => $embedUrl, 'embed' => $this->normalizeEmbed($client->getEmbed($uuid)), 'error' => false];
        } catch (\Throwable) {
            return ['playerMode' => 'native', 'embedUrl' => $embedUrl, 'embed' => null, 'error' => true];
        }
    }

    protected function buildEmbedUrl(string $uuid, FieldConfigCollection $config): string
    {
        $query = http_build_query([
            'autoplay' => $this->boolOption($config, 'autoplay', false) ? '1' : '0',
            'muted' => $this->boolOption($config, 'muted', false) ? '1' : '0',
            'loop' => $this->boolOption($config, 'loop', false) ? '1' : '0',
            'controls' => $this->boolOption($config, 'showControls', true) ? '1' : '0',
        ]);

        return self::EMBED_BASE_URL . '/embed/' . rawurlencode($uuid) . '?' . $query;
    }

    protected function boolOption(FieldConfigCollection $config, string $key, bool $default): bool
    {
        $value = $config->get($key)?->getValue();

        return is_bool($value) ? $value : $default;
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
