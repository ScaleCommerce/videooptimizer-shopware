<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoGridElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoGridStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoGridElementResolverTest extends TestCase
{
    public function testTypeIsVideoGrid(): void
    {
        $resolver = new VideoGridElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-video-grid', $resolver->getType());
    }

    public function testEnrichResolvesItemsAndGridFields(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturnCallback(fn (string $uuid) => [
            'sources' => [['src' => "https://cdn/$uuid.m3u8", 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => "https://cdn/$uuid.jpg",
            'resolution' => '1920x1080',
        ]);

        $slot = $this->slot([
            'headline' => 'Unsere Videos',
            'intro' => "Zeile 1\nZeile 2",
            'presentation' => 'facade',
            'playerMode' => 'native',
            'items' => [
                ['video' => 'uuid-a', 'libraryId' => 'lib-1', 'label' => 'First'],
                ['video' => 'uuid-b', 'libraryId' => 'lib-1', 'label' => 'Second'],
            ],
        ]);
        $resolver = new VideoGridElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoGridStruct::class, $data);
        static::assertSame('Unsere Videos', $data->getHeadline());
        static::assertSame("Zeile 1\nZeile 2", $data->getIntro());
        static::assertSame('facade', $data->getPresentation());
        $items = $data->getItems();
        static::assertCount(2, $items);
        static::assertSame('uuid-a', $items[0]['videoUuid']);
        static::assertSame('First', $items[0]['label']);
        static::assertSame('https://cdn/uuid-a.m3u8', $items[0]['embed']['hls']);
        static::assertSame('native', $items[0]['playerMode']);
        static::assertFalse($items[0]['error']);
        static::assertSame('uuid-b', $items[1]['videoUuid']);
    }

    public function testPresentationDefaultsToLightboxAndSkipsEmptyItems(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [], 'poster' => 'https://cdn/p.jpg', 'resolution' => null]);

        $slot = $this->slot([
            'playerMode' => 'embed',
            'items' => [
                ['video' => '', 'label' => 'skip-me'],           // empty video -> skipped
                ['video' => 'uuid-c', 'label' => 'keep'],
                ['label' => 'no-video-key'],                     // missing video -> skipped
            ],
        ]);
        $resolver = new VideoGridElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('lightbox', $data->getPresentation());
        $items = $data->getItems();
        static::assertCount(1, $items);
        static::assertSame('uuid-c', $items[0]['videoUuid']);
        static::assertNotNull($items[0]['embed']); // lightbox + embed still fetches the poster
    }

    public function testEnrichWithNoItemsRendersNothingHarmlessly(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['headline' => 'x']); // items missing entirely
        $resolver = new VideoGridElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertSame([], $slot->getData()->getItems());
    }

    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-video-grid');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);
        return $slot;
    }
}
