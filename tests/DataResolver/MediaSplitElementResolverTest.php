<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\MediaSplitElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\MediaSplitStruct;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Framework\Util\HtmlSanitizer;

class MediaSplitElementResolverTest extends TestCase
{
    public function testTypeIsMediaSplit(): void
    {
        $resolver = $this->resolver($this->createMock(VideoOptimizerClient::class));
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
        $resolver = $this->resolver($client);
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
        $resolver = $this->resolver($client);
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
        $resolver = $this->resolver($client);
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
        $resolver = $this->resolver($client);
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
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());
        static::assertNull($slot->getData()->getVideoUuid());
    }

    public function testTextIsSanitizedAgainstScriptAndEventHandlerInjection(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $slot = $this->slot(['text' => '<p>ok</p><img src=x onerror=alert(1)><script>x</script>']);
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        // The resolver test uses a real HtmlSanitizer constructed with no configured element sets,
        // so it strips all tags (not just dangerous ones) - there is no field-set config in a unit
        // test. That's fine here: the point is that onerror/<script> cannot survive either way.
        $text = $slot->getData()->getText();
        static::assertStringNotContainsString('onerror', $text);
        static::assertStringNotContainsString('<script>', $text);
        static::assertStringContainsString('ok', $text);
    }

    public function testNullTextStaysNull(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $slot = $this->slot(['headline' => 'x']);
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        static::assertNull($slot->getData()->getText());
    }

    #[DataProvider('ctaUrlProvider')]
    public function testCtaUrlSchemeAllowlist(?string $input, ?string $expected): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $slot = $this->slot(['ctaUrl' => $input]);
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        static::assertSame($expected, $slot->getData()->getCtaUrl());
    }

    public static function ctaUrlProvider(): iterable
    {
        yield 'javascript scheme is rejected' => ['javascript:alert(1)', null];
        yield 'data scheme is rejected' => ['data:text/html,x', null];
        yield 'uppercase javascript scheme is rejected' => ['JAVASCRIPT:alert(1)', null];
        yield 'newline-obfuscated javascript scheme is rejected' => ["java\nscript:alert(1)", null];
        yield 'https url passes' => ['https://example.com/x?y=1', 'https://example.com/x?y=1'];
        yield 'relative path passes' => ['/checkout', '/checkout'];
        yield 'mailto passes' => ['mailto:a@b.de', 'mailto:a@b.de'];
    }

    private function resolver(VideoOptimizerClient $client): MediaSplitElementResolver
    {
        return new MediaSplitElementResolver($client, new HtmlSanitizer());
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
