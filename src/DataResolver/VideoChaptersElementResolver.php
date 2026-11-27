<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoChaptersStruct;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Scenes;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * "Video with chapters": the player shown directly plus a row of scene previews (the ten thumbnails
 * the encoder samples) that jump to the scene's start time.
 */
class VideoChaptersElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-chapters';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $uuid = $slot->getFieldConfig()->get('videoUuid')?->getValue();
        if (is_string($uuid) && $uuid !== '') {
            $this->client->queueEmbed($uuid);
        }

        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoChaptersStruct();
        $slot->setData($struct);

        $config = $slot->getFieldConfig();
        $headline = $config->get('headline')?->getValue();
        $struct->setHeadline(is_string($headline) && trim($headline) !== '' ? trim($headline) : null);

        $uuid = $config->get('videoUuid')?->getValue();
        if (!is_string($uuid) || $uuid === '') {
            return;
        }
        $struct->setVideoUuid($uuid);

        $surface = $this->buildVideoSurface($config, $uuid, $this->client, $this->logger);
        $struct->setPlayerMode($surface['playerMode']);
        $struct->setEmbedUrl($surface['embedUrl']);
        $struct->setLayout($surface['layout']);
        if ($surface['error']) {
            $struct->setError(true);

            return;
        }
        $struct->setEmbed($surface['embed']);

        // Without a known duration the start times cannot be derived: the element shows the player only.
        $duration = is_numeric($surface['embed']['duration'] ?? null) ? (float) $surface['embed']['duration'] : 0.0;
        try {
            $struct->setScenes(Scenes::build(
                $duration,
                Scenes::count($config->get('sceneCount')?->getValue()),
                Scenes::titles($config->get('sceneTitles')?->getValue()),
                fn (int $index): string => $this->client->thumbnailUrl($uuid, $index),
            ));
        } catch (InvalidApiBaseUrlException $e) {
            $this->logger->warning('VideoOptimizer API base URL is misconfigured; hiding the scene previews.', ['uuid' => $uuid, 'error' => $e->getMessage()]);
        }
    }
}
