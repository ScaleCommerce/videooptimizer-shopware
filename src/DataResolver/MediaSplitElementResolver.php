<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\MediaSplitStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class MediaSplitElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly HtmlSanitizer $sanitizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-media-split';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new MediaSplitStruct();
        $slot->setData($struct);

        $config = $slot->getFieldConfig();
        $string = fn (string $key): ?string => is_string($config->get($key)?->getValue()) ? $config->get($key)->getValue() : null;

        $struct->setPresentation(in_array($config->get('presentation')?->getValue(), ['facade', 'lightbox', 'direct'], true) ? $config->get('presentation')->getValue() : 'facade');
        $struct->setSide($config->get('side')?->getValue() === 'right' ? 'right' : 'left');
        $struct->setEyebrow($string('eyebrow'));
        $struct->setHeadline($string('headline'));
        $text = $string('text');
        $struct->setText($text !== null && $text !== '' ? $this->sanitizer->sanitize($text) : null);
        $struct->setCtaLabel($string('ctaLabel'));
        $struct->setCtaUrl($this->safeUrl($string('ctaUrl')));

        $uuid = $config->get('video')?->getValue();
        if (!is_string($uuid) || $uuid === '') {
            return;
        }
        $struct->setVideoUuid($uuid);

        $surface = $this->buildVideoSurface($config, $uuid, $this->client, $this->logger, $struct->getPresentation());
        $struct->setPlayerMode($surface['playerMode']);
        $struct->setEmbedUrl($surface['embedUrl']);
        $struct->setEmbed($surface['embed']);
        $struct->setError($surface['error']);
    }
}
