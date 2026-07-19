<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\MediaSplitElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\MediaSplitStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class MediaSplitElementResolverTest extends TestCase
{
    public function testTypeIsMediaSplit(): void
    {
        $resolver = new MediaSplitElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-media-split', $resolver->getType());
    }

    public function testEnrichResolvesVideoAndLayout(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->with('uuid-1')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
            'resolution' => '1920x1080',
        ]);

        $slot = $this->slot([
            'video' => 'uuid-1', 'playerMode' => 'native', 'presentation' => 'facade',
            'side' => 'right', 'eyebrow' => 'Neu', 'headline' => 'Titel', 'text' => '<p>Hi</p>',
            'ctaLabel' => 'Mehr', 'ctaUrl' => '/x',
        ]);
        $resolver = new MediaSplitElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(MediaSplitStruct::class, $data);
        static::assertSame('uuid-1', $data->getVideoUuid());
        static::assertSame('native', $data->getPlayerMode());
        static::assertSame('facade', $data->getPresentation());
        static::assertSame('right', $data->getSide());
        static::assertSame('Titel', $data->getHeadline());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        static::assertStringContainsString('/embed/uuid-1?', $data->getEmbedUrl());
    }

    public function testEmbedModeWithFacadeStillResolvesPosterForPreview(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('uuid-2')->willReturn([
            'sources' => [],
            'poster' => 'https://cdn/poster.jpg',
            'resolution' => '1920x1080',
        ]);

        $slot = $this->slot(['video' => 'uuid-2', 'playerMode' => 'embed', 'presentation' => 'facade']);
        $resolver = new MediaSplitElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('embed', $data->getPlayerMode());
        static::assertNotNull($data->getEmbed());
        static::assertSame('https://cdn/poster.jpg', $data->getEmbed()['poster']);
        static::assertFalse($data->hasError());
    }

    public function testEmbedModeWithDirectSkipsUpstreamCall(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');

        $slot = $this->slot(['video' => 'uuid-3', 'playerMode' => 'embed', 'presentation' => 'direct']);
        $resolver = new MediaSplitElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('embed', $data->getPlayerMode());
        static::assertNull($data->getEmbed());
        static::assertFalse($data->hasError());
    }

    public function testEmbedModeWithFacadeToleratesEmbedFetchFailure(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new \RuntimeException('upstream down'));

        $slot = $this->slot(['video' => 'uuid-4', 'playerMode' => 'embed', 'presentation' => 'lightbox']);
        $resolver = new MediaSplitElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('embed', $data->getPlayerMode());
        static::assertNull($data->getEmbed());
        // The hosted iframe still plays on click, so a missing poster is not a hard error.
        static::assertFalse($data->hasError());
    }

    public function testEnrichWithoutVideoRendersNothingHarmlessly(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['headline' => 'x']);
        $resolver = new MediaSplitElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertNull($slot->getData()->getVideoUuid());
    }

    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-media-split');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);
        return $slot;
    }
}
