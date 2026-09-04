<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoGridElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoGridStruct;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
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
        $resolver = $this->resolver($this->createMock(VideoOptimizerClient::class));
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
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

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
        $resolver = $this->resolver($client);
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
        static::assertStringStartsWith('https://videooptimizer.eu/embed/uuid-a?', $items[0]['embedUrl']);
        static::assertSame('uuid-b', $items[1]['videoUuid']);
    }

    public function testItemsThatFailToResolveAreDropped(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturnCallback(function (string $uuid) {
            if ($uuid === 'uuid-gone') {
                throw new \RuntimeException('video gone');
            }

            return [
                'sources' => [['src' => "https://cdn/$uuid.m3u8", 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
                'poster' => "https://cdn/$uuid.jpg",
                'resolution' => '1920x1080',
            ];
        });
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slot([
            'presentation' => 'facade',
            'playerMode' => 'native',
            'items' => [
                ['video' => 'uuid-ok', 'label' => 'Kept'],
                ['video' => 'uuid-gone', 'label' => 'Dropped'],
            ],
        ]);
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $items = $slot->getData()->getItems();
        // The grid must not keep a slot with headline/intro but zero renderable tiles - an item
        // whose upstream video no longer resolves is dropped instead of appended with an error flag.
        static::assertCount(1, $items);
        static::assertSame('uuid-ok', $items[0]['videoUuid']);
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
        $resolver = $this->resolver($client);
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
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertSame([], $slot->getData()->getItems());
    }

    public function testItemThatFailsToResolveLogsAWarning(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new \RuntimeException('video gone'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(
            static::isType('string'),
            ['uuid' => 'uuid-gone', 'element' => 'scalecommerce-vo-video-grid', 'error' => 'video gone']
        );

        $slot = $this->slot(['items' => [['video' => 'uuid-gone', 'label' => 'Dropped']]]);
        $resolver = $this->resolver($client, $logger);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
    }

    public function testInvalidEmbedBaseUrlLogsAWarning(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('embedBaseUrl')->willThrowException(new InvalidApiBaseUrlException('bad url'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(
            static::isType('string'),
            ['uuid' => 'uuid-x', 'element' => 'scalecommerce-vo-video-grid', 'error' => 'bad url']
        );

        $slot = $this->slot(['items' => [['video' => 'uuid-x', 'label' => 'x']]]);
        $resolver = $this->resolver($client, $logger);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
    }

    private function resolver(VideoOptimizerClient $client, ?LoggerInterface $logger = null): VideoGridElementResolver
    {
        return new VideoGridElementResolver($client, $logger ?? new NullLogger());
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
