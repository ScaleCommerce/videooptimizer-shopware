<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoOptimizerElementResolver;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoOptimizerElementResolverTest extends TestCase
{
    public function testGetTypeIsScaleVideoOptimizerVideo(): void
    {
        $resolver = new VideoOptimizerElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-video', $resolver->getType());
    }

    public function testEnrichNormalizesEmbedSources(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        // Real /embed shape: `sources` is a list; some mp4 renditions carry a
        // truncated (not-yet-encoded) src and must be ignored.
        $client->method('getEmbed')->with('uuid-1')->willReturn([
            'sources' => [
                ['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls', 'size' => null],
                ['src' => 'https://cdn/', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '1080p', 'size' => null],
                ['src' => 'https://cdn/480p.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '480p', 'size' => 1000],
                ['src' => 'https://cdn/720p.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '720p', 'size' => 2000],
            ],
            'poster' => 'https://cdn/poster.jpg',
            'title' => 'Demo',
            'duration' => '10',
            'resolution' => '1260x750',
        ]);

        $slot = $this->slotWithUuid('uuid-1');
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoOptimizerVideoStruct::class, $data);
        static::assertFalse($data->hasError());
        $embed = $data->getEmbed();
        static::assertSame('https://cdn/master.m3u8', $embed['hls']);
        // Largest valid .mp4 rendition wins; the truncated CDN-root src is skipped.
        static::assertSame('https://cdn/720p.mp4', $embed['mp4']);
        static::assertSame('https://cdn/poster.jpg', $embed['poster']);
        static::assertSame('Demo', $embed['title']);
        static::assertSame('1260 / 750', $embed['aspectRatio']);
    }

    public function testEnrichHandlesMissingSourcesWithoutError(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['poster' => null]);

        $slot = $this->slotWithUuid('uuid-2');
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertFalse($data->hasError());
        static::assertNull($data->getEmbed()['hls']);
        static::assertNull($data->getEmbed()['mp4']);
    }

    public function testEnrichHandlesApiErrorGracefully(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new VideoOptimizerApiException(404, 'Not found'));

        $slot = $this->slotWithUuid('missing');
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        static::assertTrue($slot->getData()->hasError());
    }

    public function testEmbedModeBuildsEmbedUrlAndCallsGetEmbedToVerifyTheVideoExists(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('uuid-embed')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
        ]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-embed', [
            'playerMode' => 'embed',
            'autoplay' => true,
            'muted' => true,
            'loop' => false,
            'showControls' => false,
        ]);
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('embed', $data->getPlayerMode());
        static::assertFalse($data->hasError());
        $url = $data->getEmbedUrl();
        static::assertStringStartsWith('https://videooptimizer.eu/embed/uuid-embed?', $url);
        static::assertStringContainsString('autoplay=1', $url);
        static::assertStringContainsString('muted=1', $url);
        static::assertStringContainsString('loop=0', $url);
        static::assertStringContainsString('controls=0', $url);
    }

    public function testEmbedModeFlagsErrorWhenUpstreamVideoIsGone(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('uuid-deleted')
            ->willThrowException(new VideoOptimizerApiException(404, 'Not found'));
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-deleted', ['playerMode' => 'embed']);
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('embed', $data->getPlayerMode());
        static::assertTrue($data->hasError());
        static::assertNull($data->getEmbed());
    }

    public function testNativeModeFetchesEmbedAndSetsMode(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('uuid-native')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
        ]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-native', ['playerMode' => 'native']);
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame('native', $data->getPlayerMode());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        static::assertStringStartsWith('https://videooptimizer.eu/embed/uuid-native?', $data->getEmbedUrl());
    }

    public function testInvalidEmbedBaseUrlSetsErrorWithoutCrashingThePage(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('embedBaseUrl')->willThrowException(new InvalidApiBaseUrlException());
        $client->expects(static::never())->method('getEmbed');

        $slot = $this->slotWithUuid('uuid-bad-embed-base');
        $resolver = new VideoOptimizerElementResolver($client);
        // A misconfigured embedBaseUrl must become a per-element error, not an exception that
        // takes down the whole CMS page render.
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertTrue($data->hasError());
        static::assertSame('', $data->getEmbedUrl());
        static::assertNull($data->getEmbed());
    }

    private function slotWithUuid(string $uuid, array $extraConfig = []): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-video');
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('videoUuid', FieldConfig::SOURCE_STATIC, $uuid));
        foreach ($extraConfig as $name => $value) {
            $config->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($config);
        return $slot;
    }
}
