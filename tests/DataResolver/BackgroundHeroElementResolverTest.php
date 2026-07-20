<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\BackgroundHeroElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\BackgroundHeroStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class BackgroundHeroElementResolverTest extends TestCase
{
    public function testTypeIsBackgroundHero(): void
    {
        $resolver = new BackgroundHeroElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-background-hero', $resolver->getType());
    }

    public function testEnrichResolvesVideoAndFields(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->with('uuid-1')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
            'resolution' => '1920x1080',
        ]);

        $slot = $this->slot([
            'video' => 'uuid-1', 'eyebrow' => 'Neu', 'headline' => 'Titel',
            'subline' => "Zeile 1\nZeile 2", 'ctaLabel' => 'Mehr', 'ctaUrl' => '/x',
            'overlay' => 'dark', 'height' => 'full',
            'headlineColor' => '#ff0000', 'textColor' => '#abc', 'priority' => true,
        ]);
        $resolver = new BackgroundHeroElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(BackgroundHeroStruct::class, $data);
        static::assertSame('uuid-1', $data->getVideoUuid());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        static::assertSame('https://cdn/p.jpg', $data->getEmbed()['poster']);
        static::assertSame('Titel', $data->getHeadline());
        static::assertSame("Zeile 1\nZeile 2", $data->getSubline());
        static::assertSame('dark', $data->getOverlay());
        static::assertSame('full', $data->getHeight());
        static::assertSame('#ff0000', $data->getHeadlineColor());
        static::assertSame('#abc', $data->getTextColor());
        static::assertTrue($data->getPriority());
        static::assertFalse($data->hasError());
    }

    public function testInvalidOverlayHeightAndColorsFallBack(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [], 'poster' => null, 'resolution' => null]);

        $slot = $this->slot([
            'video' => 'uuid-9', 'overlay' => 'rainbow', 'height' => 'gigantic',
            'headlineColor' => 'red', 'textColor' => 'javascript:alert(1)',
        ]);
        $resolver = new BackgroundHeroElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('gradient', $data->getOverlay());
        static::assertSame('large', $data->getHeight());
        static::assertNull($data->getHeadlineColor());
        static::assertNull($data->getTextColor());
        static::assertFalse($data->getPriority());
    }

    public function testAlphaHexColorsAreAccepted(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [], 'poster' => null, 'resolution' => null]);

        $slot = $this->slot([
            'video' => 'uuid-a', 'headlineColor' => '#ff000080', 'textColor' => '#abcd',
        ]);
        $resolver = new BackgroundHeroElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('#ff000080', $data->getHeadlineColor());
        static::assertSame('#abcd', $data->getTextColor());
    }

    public function testEnrichWithoutVideoRendersNothingHarmlessly(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['headline' => 'x']);
        $resolver = new BackgroundHeroElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertNull($slot->getData()->getVideoUuid());
    }

    public function testEmbedFetchFailureSetsError(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new \RuntimeException('down'));
        $slot = $this->slot(['video' => 'uuid-2']);
        $resolver = new BackgroundHeroElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('uuid-2', $data->getVideoUuid());
        static::assertNull($data->getEmbed());
        static::assertTrue($data->hasError());
    }

    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-background-hero');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);
        return $slot;
    }
}
