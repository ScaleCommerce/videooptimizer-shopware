<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Subscriber;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\Service\ProductVideoSlide;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use ScaleCommerce\VideoOptimizer\Subscriber\ProductVideoSubscriber;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class ProductVideoSubscriberTest extends TestCase
{
    public function testSubscribesToTheProductPage(): void
    {
        static::assertSame([ProductPageLoadedEvent::class], array_keys(ProductVideoSubscriber::getSubscribedEvents()));
    }

    public function testAppendsTheVideoAsTheLastSlideOfTheProductGallery(): void
    {
        $gallery = $this->gallery();
        $this->handle($this->event([$gallery], [ProductVideoSlide::FIELD => 'vid-1']));

        $items = $gallery->getSliderItems();
        static::assertCount(2, $items);
        static::assertNull($items[0]->getMedia()->getExtension(ProductVideoSlide::EXTENSION));
        static::assertNotNull($items[1]->getMedia()->getExtension(ProductVideoSlide::EXTENSION));
    }

    public function testGalleriesWithoutProductMediaAreLeftAlone(): void
    {
        $manual = new ImageSliderStruct();
        $manual->setSliderItems(null);
        $this->handle($this->event([$manual], [ProductVideoSlide::FIELD => 'vid-1']));

        static::assertNull($manual->getSliderItems());
    }

    public function testNothingChangesWithoutAProductVideo(): void
    {
        $gallery = $this->gallery();
        $this->handle($this->event([$gallery], ['other' => 'x']));
        $this->handle($this->event([$gallery], null));

        static::assertCount(1, $gallery->getSliderItems());
    }

    public function testAPageWithoutAnyCmsPageIsFine(): void
    {
        $this->handle($this->event([], [ProductVideoSlide::FIELD => 'vid-1'], false));

        $this->addToAssertionCount(1);
    }

    private function gallery(): ImageSliderStruct
    {
        $media = new MediaEntity();
        $media->setId('m1');
        $media->setUniqueIdentifier('m1');
        $media->setMediaType(new ImageType());
        $item = new ImageSliderItemStruct();
        $item->setMedia($media);
        $gallery = new ImageSliderStruct();
        $gallery->setSliderItems([$item]);

        return $gallery;
    }

    private function handle(ProductPageLoadedEvent $event): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/v.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '720p']], 'poster' => 'https://cdn/p.jpg']);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $config = $this->createStub(SystemConfigService::class);
        (new ProductVideoSubscriber(new ProductVideoSlide($client, $config, new NullLogger())))->onProductPageLoaded($event);
    }

    /**
     * @param list<ImageSliderStruct> $galleries
     * @param array<string, mixed>|null $customFields
     */
    private function event(array $galleries, ?array $customFields, bool $withCms = true): ProductPageLoadedEvent
    {
        $slots = [];
        foreach ($galleries as $index => $data) {
            $slot = new CmsSlotEntity();
            $slot->setUniqueIdentifier('slot' . $index);
            $slot->setType('image-gallery');
            $slot->setSlot('left');
            $slot->setData($data);
            $slots[] = $slot;
        }
        $block = new CmsBlockEntity();
        $block->setUniqueIdentifier('block');
        $block->setSlots(new CmsSlotCollection($slots));
        $section = new CmsSectionEntity();
        $section->setUniqueIdentifier('section');
        $section->setBlocks(new CmsBlockCollection([$block]));
        $cms = new CmsPageEntity();
        $cms->setUniqueIdentifier('page');
        $cms->setSections(new CmsSectionCollection([$section]));

        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier('p1');
        $product->setTranslated(['customFields' => $customFields]);
        $page = new ProductPage();
        $page->setProduct($product);
        if ($withCms) {
            $page->setCmsPage($cms);
        }

        return new ProductPageLoadedEvent($page, $this->createStub(SalesChannelContext::class), new Request());
    }
}
