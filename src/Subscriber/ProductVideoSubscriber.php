<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

use ScaleCommerce\VideoOptimizer\Service\ProductVideoSlide;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Appends the video of the product (and of the selected variant, which inherits the parent's) as the
 * last slide to the image gallery of the product detail page.
 */
class ProductVideoSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ProductVideoSlide $slide)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ProductPageLoadedEvent::class => 'onProductPageLoaded'];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $galleries = [];
        try {
            $cmsPage = $page->getCmsPage();
        } catch (\Error) {
            // The page property stays uninitialised when no layout was loaded.
            return;
        }
        foreach ($cmsPage?->getElementsOfType('image-gallery') ?? [] as $slot) {
            $data = $slot->getData();
            // Only galleries fed by the product's media (mapped `sliderItems`), not a gallery whose
            // images were chosen by hand.
            $source = $slot->getFieldConfig()->get('sliderItems');
            if ($data instanceof ImageSliderStruct && $data->getSliderItems() !== null && $source?->isMapped() === true && $source->getValue() === 'product.media') {
                $galleries[] = $data;
            }
        }
        if ($galleries === []) {
            return;
        }

        $customFields = $page->getProduct()->getTranslation('customFields');
        $item = $this->slide->create(is_array($customFields) ? $customFields : null);
        if ($item === null) {
            return;
        }
        foreach ($galleries as $gallery) {
            $gallery->addSliderItem(clone $item);
        }
    }
}
