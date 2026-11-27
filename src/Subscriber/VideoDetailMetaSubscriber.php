<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The page title of a page with a video detail element is the title of the shown video, and a
 * "not found" or temporarily unavailable detail page is kept out of the search index (and never gets a video title).
 */
class VideoDetailMetaSubscriber implements EventSubscriberInterface
{
    public const ELEMENT = 'scalecommerce-vo-video-detail';

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
            LandingPageLoadedEvent::class => 'onLandingPageLoaded',
        ];
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $this->apply($page->getCmsPage(), $page->getMetaInformation());
    }

    public function onLandingPageLoaded(LandingPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $this->apply($page->getLandingPage()?->getCmsPage(), $page->getMetaInformation());
    }

    private function apply(?CmsPageEntity $cmsPage, ?MetaInformation $meta): void
    {
        if ($cmsPage === null || $meta === null) {
            return;
        }
        $detail = $cmsPage->getFirstElementOfType(self::ELEMENT)?->getData();
        if (!$detail instanceof VideoDetailStruct) {
            return;
        }
        if ($detail->isNotFound() || $detail->isUnavailable()) {
            $meta->setRobots('noindex,follow');

            return;
        }
        $title = $detail->getTitle();
        if ($title !== null && $title !== '') {
            $meta->setMetaTitle($title);
        }
    }
}
