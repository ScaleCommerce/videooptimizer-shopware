<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\SpotlightElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\SpotlightStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class SpotlightElementResolverTest extends TestCase
{
    public function testTypeIsSpotlight(): void
    {
        $resolver = new SpotlightElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-spotlight', $resolver->getType());
    }

    public function testEnrichResolvesVideoAndFields(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->with('uuid-1')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
            'resolution' => '1920x1080',
        ]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slot([
            'video' => 'uuid-1', 'playerMode' => 'native', 'presentation' => 'facade',
            'eyebrow' => 'Neu', 'headline' => 'Titel', 'caption' => 'Kurze Bildunterschrift',
        ]);
        $resolver = new SpotlightElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(SpotlightStruct::class, $data);
        static::assertSame('uuid-1', $data->getVideoUuid());
        static::assertSame('native', $data->getPlayerMode());
        static::assertSame('facade', $data->getPresentation());
        static::assertSame('Titel', $data->getHeadline());
        static::assertSame('Kurze Bildunterschrift', $data->getCaption());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        static::assertStringStartsWith('https://videooptimizer.eu/embed/uuid-1?', $data->getEmbedUrl());
    }

    public function testPresentationDefaultsToLightbox(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [], 'poster' => 'https://cdn/p.jpg', 'resolution' => null]);

        // no presentation configured, embed player -> facade/lightbox needs the poster fetched
        $slot = $this->slot(['video' => 'uuid-2', 'playerMode' => 'embed']);
        $resolver = new SpotlightElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('lightbox', $data->getPresentation());
        static::assertSame('embed', $data->getPlayerMode());
        static::assertNotNull($data->getEmbed());
    }

    public function testInvalidPresentationFallsBackToLightbox(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [], 'poster' => null, 'resolution' => null]);
        $slot = $this->slot(['video' => 'uuid-3', 'presentation' => 'sideways', 'playerMode' => 'embed']);
        $resolver = new SpotlightElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertSame('lightbox', $slot->getData()->getPresentation());
    }

    public function testEnrichWithoutVideoRendersNothingHarmlessly(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['headline' => 'x']);
        $resolver = new SpotlightElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertNull($slot->getData()->getVideoUuid());
    }

    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-spotlight');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);
        return $slot;
    }
}
