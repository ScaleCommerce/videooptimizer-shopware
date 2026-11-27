<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use ScaleCommerce\VideoOptimizer\Subscriber\VideoDetailMetaSubscriber;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedEvent;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class VideoDetailMetaSubscriberTest extends TestCase
{
    public function testSubscribesToNavigationAndLandingPages(): void
    {
        static::assertSame(
            [NavigationPageLoadedEvent::class, LandingPageLoadedEvent::class],
            array_keys(VideoDetailMetaSubscriber::getSubscribedEvents())
        );
    }

    public function testTheVideoTitleBecomesThePageTitleOfACategoryPage(): void
    {
        $meta = $this->meta();
        $page = new NavigationPage();
        $page->setMetaInformation($meta);
        $page->setCmsPage($this->cmsPage($this->detail('Product tour')));

        (new VideoDetailMetaSubscriber())->onNavigationPageLoaded(new NavigationPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), new Request()));

        static::assertSame('Product tour', $meta->getMetaTitle());
        static::assertSame('index,follow', $meta->getRobots());
    }

    public function testTheVideoTitleBecomesThePageTitleOfALandingPage(): void
    {
        $meta = $this->meta();
        $landing = new LandingPageEntity();
        $landing->setUniqueIdentifier('lp');
        $landing->setCmsPage($this->cmsPage($this->detail('Landing video')));
        $page = new LandingPage();
        $page->setMetaInformation($meta);
        $page->setLandingPage($landing);

        (new VideoDetailMetaSubscriber())->onLandingPageLoaded(new LandingPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), new Request()));

        static::assertSame('Landing video', $meta->getMetaTitle());
    }

    public function testANotFoundVideoIsKeptOutOfTheSearchIndexAndKeepsTheTitle(): void
    {
        $meta = $this->meta();
        $detail = $this->detail(null);
        $detail->setNotFound(true);
        $page = new NavigationPage();
        $page->setMetaInformation($meta);
        $page->setCmsPage($this->cmsPage($detail));

        (new VideoDetailMetaSubscriber())->onNavigationPageLoaded(new NavigationPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), new Request()));

        static::assertSame('noindex,follow', $meta->getRobots());
        static::assertSame('Page title', $meta->getMetaTitle());
    }

    public function testPagesWithoutADetailElementAreLeftAlone(): void
    {
        $meta = $this->meta();
        $page = new NavigationPage();
        $page->setMetaInformation($meta);
        $page->setCmsPage($this->cmsPage(new class() extends Struct {
        }, 'other-element'));

        (new VideoDetailMetaSubscriber())->onNavigationPageLoaded(new NavigationPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), new Request()));

        static::assertSame('Page title', $meta->getMetaTitle());

        // No CMS page at all.
        $bare = new NavigationPage();
        $bare->setMetaInformation($meta);
        (new VideoDetailMetaSubscriber())->onNavigationPageLoaded(new NavigationPageLoadedEvent($bare, $this->createStub(SalesChannelContext::class), new Request()));
        static::assertSame('Page title', $meta->getMetaTitle());
    }

    private function meta(): MetaInformation
    {
        $meta = new MetaInformation();
        $meta->setMetaTitle('Page title');
        $meta->setRobots('index,follow');

        return $meta;
    }

    private function detail(?string $title): VideoDetailStruct
    {
        $detail = new VideoDetailStruct();
        $detail->setTitle($title);

        return $detail;
    }

    private function cmsPage(Struct $data, string $type = VideoDetailMetaSubscriber::ELEMENT): CmsPageEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot');
        $slot->setType($type);
        $slot->setSlot('main');
        $slot->setData($data);
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier('block');
        $block->setSlots(new CmsSlotCollection([$slot]));
        $section = new CmsSectionEntity();
        $section->setUniqueIdentifier('section');
        $section->setBlocks(new CmsBlockCollection([$block]));
        $page = new CmsPageEntity();
        $page->setUniqueIdentifier('page');
        $page->setSections(new CmsSectionCollection([$section]));

        return $page;
    }
}
