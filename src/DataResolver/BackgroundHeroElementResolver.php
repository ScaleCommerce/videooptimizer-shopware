<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use ScaleCommerce\VideoOptimizer\DataResolver\Struct\BackgroundHeroStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class BackgroundHeroElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public function __construct(private readonly VideoOptimizerClient $client)
    {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-background-hero';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new BackgroundHeroStruct();
        $slot->setData($struct);

        $config = $slot->getFieldConfig();
        $string = fn (string $key): ?string => is_string($config->get($key)?->getValue()) ? $config->get($key)->getValue() : null;

        $struct->setEyebrow($string('eyebrow'));
        $struct->setHeadline($string('headline'));
        $struct->setSubline($string('subline'));
        $struct->setCtaLabel($string('ctaLabel'));
        $struct->setCtaUrl($string('ctaUrl'));
        $struct->setOverlay(in_array($config->get('overlay')?->getValue(), ['gradient', 'dark', 'none'], true) ? $config->get('overlay')->getValue() : 'gradient');
        $struct->setHeight(in_array($config->get('height')?->getValue(), ['full', 'large', 'medium'], true) ? $config->get('height')->getValue() : 'large');
        $struct->setHeadlineColor($this->hexColor($string('headlineColor')));
        $struct->setTextColor($this->hexColor($string('textColor')));
        $struct->setPriority($this->boolOption($config, 'priority', false));

        $uuid = $config->get('video')?->getValue();
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

    private function hexColor(?string $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : null;
    }
}
