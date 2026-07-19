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
    use VideoSurfaceTrait;

    public function __construct(private readonly VideoOptimizerClient $client)
    {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-video';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
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

        $playerMode = $fieldConfig->get('playerMode')?->getValue() === 'embed' ? 'embed' : 'native';
        $struct->setPlayerMode($playerMode);
        $struct->setEmbedUrl($this->buildEmbedUrl($uuid, $fieldConfig));

        // Embed mode: the hosted iframe loads poster + sources itself, no upstream call needed.
        if ($playerMode === 'embed') {
            return;
        }

        try {
            $struct->setEmbed($this->normalizeEmbed($this->client->getEmbed($uuid)));
        } catch (\Throwable) {
            $struct->setError(true);
        }
    }
}
