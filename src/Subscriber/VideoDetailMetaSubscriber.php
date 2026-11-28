<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

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
        $this->apply($page->getCmsPage(), $page->getMetaInformation(), $event->getRequest());
    }

    public function onLandingPageLoaded(LandingPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $this->apply($page->getLandingPage()?->getCmsPage(), $page->getMetaInformation(), $event->getRequest());
    }

    private function apply(?CmsPageEntity $cmsPage, ?MetaInformation $meta, Request $request): void
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
        // A verified video: its own title, description and canonical (one URL per video).
        $title = $detail->getTitle();
        if ($title !== null && $title !== '') {
            $meta->setMetaTitle($title);
        }
        $description = $detail->getDescription() ?? $title;
        if ($description !== null && $description !== '') {
            $meta->setMetaDescription($description);
        }
        $uuid = $detail->getVideoUuid();
        if ($uuid !== null && $uuid !== '' && $request->query->has('vo_video')) {
            $base = $meta->getCanonical();
            if ($base === null || $base === '') {
                $base = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
            }
            $meta->setCanonical(explode('?', $base, 2)[0] . '?vo_video=' . rawurlencode($uuid));
        }
    }
}
