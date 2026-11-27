<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\Service\EmbedSources;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\VideoJsonLd;
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
    protected function buildVideoSurface(FieldConfigCollection $config, string $uuid, VideoOptimizerClient $client, LoggerInterface $logger): array
    {
        $playerMode = $config->get('playerMode')?->getValue() === 'embed' ? 'embed' : 'native';
        $this->markLibraryRendered($config, $client, $uuid);

        try {
            $embedUrl = $this->buildEmbedUrl($uuid, $config, $client);
        } catch (InvalidApiBaseUrlException $e) {
            // A misconfigured embed base URL must not take down the whole CMS page - surface it
            // as this element's error state instead, and skip the (equally misconfigured) upstream call.
            $logger->warning('VideoOptimizer embed base URL is misconfigured; hiding video element.', [
                'uuid' => $uuid,
                'element' => $this->getType(),
                'error' => $e->getMessage(),
            ]);

            return ['playerMode' => $playerMode, 'embedUrl' => '', 'embed' => null, 'error' => true];
        }

        // Always verify the video still exists upstream, regardless of player mode or
        // presentation - a hosted iframe for a deleted video would otherwise render a bare 404.
        try {
            $embed = $this->normalizeEmbed($client->getEmbed($uuid));
        } catch (\Throwable $e) {
            $logger->warning('VideoOptimizer embed lookup failed; hiding video element.', [
                'uuid' => $uuid,
                'element' => $this->getType(),
                'error' => $e->getMessage(),
            ]);

            return ['playerMode' => $playerMode, 'embedUrl' => $embedUrl, 'embed' => null, 'error' => true];
        }

        if (!$this->isPlayable($embed)) {
            $this->logUnplayable($logger, $uuid);

            return ['playerMode' => $playerMode, 'embedUrl' => $embedUrl, 'embed' => null, 'error' => true];
        }

        $embed['jsonLd'] = $this->buildJsonLd($client, $uuid, $embed);

        return ['playerMode' => $playerMode, 'embedUrl' => $embedUrl, 'embed' => $embed, 'error' => false];
    }

    /**
     * The schema.org VideoObject JSON of a playable video; null when switched off in the plugin
     * config or when the video's structured data was already output on this page.
     *
     * @param array<string, mixed> $embed result of normalizeEmbed()
     */
    protected function buildJsonLd(VideoOptimizerClient $client, string $uuid, array $embed): ?string
    {
        if (!$client->jsonLdEnabled() || !$client->claimVideoJsonLd($uuid)) {
            return null;
        }
        try {
            $embedUrl = $client->embedBaseUrl() . '/embed/' . rawurlencode($uuid);
        } catch (InvalidApiBaseUrlException) {
            return null;
        }

        return VideoJsonLd::build($embed, $embedUrl, $client->getVideoCreatedAt($uuid));
    }

    /**
     * Tags the page with the element's library (if configured), see VideoOptimizerClient::forgetLibrary().
     */
    protected function markLibraryRendered(FieldConfigCollection $config, VideoOptimizerClient $client, ?string $uuid = null): void
    {
        $libraryId = $config->get('libraryId')?->getValue();
        if (is_string($libraryId) && $libraryId !== '') {
            $client->markLibraryRendered($libraryId, $uuid);
        }
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
        $posterSrcset = EmbedSources::posterSrcset($embed['posterSrcset'] ?? null);

        return [
            'hls' => EmbedSources::hls($sources),
            'mp4' => EmbedSources::mp4($sources),
            'poster' => $embed['poster'] ?? null,
            'posterSrcset' => $posterSrcset,
            'posterSrcsetAttr' => EmbedSources::srcsetAttribute($posterSrcset),
            'title' => $embed['title'] ?? null,
            'theme' => EmbedSources::theme($embed['theme'] ?? null),
            'tracks' => EmbedSources::tracks($embed['tracks'] ?? null),
            'duration' => $embed['duration'] ?? null,
            'aspectRatio' => $this->deriveAspectRatio($embed['resolution'] ?? null),
        ];
    }

    /**
     * A video without HLS and MP4 (processing failed or not finished yet) cannot be played by the
     * native nor by the hosted player, so it is treated like a failed lookup.
     *
     * @param array<string, mixed> $normalizedEmbed result of normalizeEmbed()
     */
    protected function isPlayable(array $normalizedEmbed): bool
    {
        return ($normalizedEmbed['hls'] ?? null) !== null || ($normalizedEmbed['mp4'] ?? null) !== null;
    }

    protected function logUnplayable(LoggerInterface $logger, string $uuid): void
    {
        $logger->warning('VideoOptimizer video has no playable sources; hiding video element.', [
            'uuid' => $uuid,
            'element' => $this->getType(),
        ]);
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
}
