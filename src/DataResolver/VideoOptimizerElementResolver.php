<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoOptimizerElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-video';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        // Announce the lookup so all videos of the page are fetched concurrently (see enrich()).
        $uuid = $slot->getFieldConfig()->get('videoUuid')?->getValue();
        if (is_string($uuid) && $uuid !== '') {
            $this->client->queueEmbed($uuid);
        }

        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoOptimizerVideoStruct();
        $slot->setData($struct);

        $fieldConfig = $slot->getFieldConfig();
        $uuid = $fieldConfig->get('videoUuid')?->getValue();
        if (!is_string($uuid) || $uuid === '') {
            return;
        }

        $struct->setVideoUuid($uuid);

        $surface = $this->buildVideoSurface($fieldConfig, $uuid, $this->client, $this->logger);
        $struct->setPlayerMode($surface['playerMode']);
        $struct->setEmbedUrl($surface['embedUrl']);
        if ($surface['error']) {
            $struct->setError(true);

            return;
        }
        $struct->setEmbed($surface['embed']);
    }
}
