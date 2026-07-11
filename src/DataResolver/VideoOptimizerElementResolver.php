<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoOptimizerElementResolver extends AbstractCmsElementResolver
{
    public function __construct(private readonly VideoOptimizerClient $client)
    {
    }

    public function getType(): string
    {
        return 'scale-video-optimizer-video';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoOptimizerVideoStruct();
        $slot->setData($struct);

        $fieldConfig = $slot->getFieldConfig()->get('videoUuid');
        $uuid = $fieldConfig?->getValue();
        if (!is_string($uuid) || $uuid === '') {
            return;
        }

        $struct->setVideoUuid($uuid);

        try {
            $struct->setEmbed($this->normalizeEmbed($this->client->getEmbed($uuid)));
        } catch (\Throwable) {
            $struct->setError(true);
        }
    }

    /**
     * The VideoOptimizer embed endpoint returns `sources` as a list of
     * {src, type, codec, label, size} entries. Flatten it into the shape the
     * storefront template/player expects: a single HLS master URL and one MP4
     * fallback URL, plus poster and basic metadata.
     */
    private function normalizeEmbed(array $embed): array
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

    /**
     * Turn a "WIDTHxHEIGHT" resolution (e.g. "1260x750") into a CSS aspect-ratio
     * value ("1260 / 750"). Falls back to null when it cannot be parsed, so the
     * template can apply its own default.
     */
    private function deriveAspectRatio(?string $resolution): ?string
    {
        if (!is_string($resolution) || !preg_match('/^(\d+)\s*x\s*(\d+)$/i', trim($resolution), $m)) {
            return null;
        }
        if ((int) $m[1] <= 0 || (int) $m[2] <= 0) {
            return null;
        }

        return $m[1] . ' / ' . $m[2];
    }

    private function pickHlsSource(array $sources): ?string
    {
        foreach ($sources as $source) {
            $isHls = ($source['codec'] ?? null) === 'hls'
                || ($source['type'] ?? null) === 'application/vnd.apple.mpegurl';
            if ($isHls && $this->isPlayableSource($source)) {
                return $source['src'];
            }
        }

        return null;
    }

    private function pickMp4Source(array $sources): ?string
    {
        $best = null;
        $bestSize = -1;
        foreach ($sources as $source) {
            if (($source['type'] ?? null) !== 'video/mp4' || !$this->isPlayableSource($source)) {
                continue;
            }
            // Some renditions are reported before encoding finishes and carry a
            // truncated CDN-root src; only accept a real .mp4 URL.
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

    private function isPlayableSource(array $source): bool
    {
        return isset($source['src']) && is_string($source['src']) && $source['src'] !== '';
    }
}
