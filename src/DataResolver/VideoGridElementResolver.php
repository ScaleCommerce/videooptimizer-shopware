<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoGridStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoGridElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-video-grid';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoGridStruct();
        $slot->setData($struct);

        $config = $slot->getFieldConfig();
        $string = fn (string $key): ?string => is_string($config->get($key)?->getValue()) ? $config->get($key)->getValue() : null;

        $presentation = in_array($config->get('presentation')?->getValue(), ['facade', 'lightbox', 'direct'], true)
            ? $config->get('presentation')->getValue()
            : 'lightbox';
        $struct->setPresentation($presentation);
        $struct->setHeadline($string('headline'));
        $struct->setIntro($string('intro'));

        $rawItems = $config->get('items')?->getValue();
        $items = [];
        foreach (is_array($rawItems) ? $rawItems : [] as $entry) {
            $uuid = is_array($entry) ? ($entry['video'] ?? null) : null;
            if (!is_string($uuid) || $uuid === '') {
                continue;
            }
            $surface = $this->buildVideoSurface($config, $uuid, $this->client, $this->logger, $presentation);
            if ($surface['error']) {
                // A video that no longer resolves upstream (e.g. deleted) is dropped rather than
                // appended with an error flag - otherwise the grid renders a headline/intro with
                // zero renderable tiles.
                continue;
            }
            $items[] = [
                'videoUuid' => $uuid,
                'label' => is_string($entry['label'] ?? null) ? $entry['label'] : null,
                'playerMode' => $surface['playerMode'],
                'embedUrl' => $surface['embedUrl'],
                'embed' => $surface['embed'],
            ];
        }
        $struct->setItems($items);
    }
}
