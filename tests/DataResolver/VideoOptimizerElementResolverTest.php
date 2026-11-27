<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
        $resolver = $this->resolver($this->createStub(VideoOptimizerClient::class));
        static::assertSame('scalecommerce-vo-video', $resolver->getType());
    }

    public function testEnrichNormalizesEmbedSources(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        // Real /embed shape: `sources` is a list; some mp4 renditions carry a
        // truncated (not-yet-encoded) src and must be ignored.
        $client->expects(static::once())->method('getEmbed')->with('uuid-1')->willReturn([
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
            'theme' => ['accentColor' => '#C72876', 'loopButton' => true, 'preload' => 'bogus', 'x' => 1],
            'posterSrcset' => [['width' => 640, 'height' => 360, 'url' => 'https://cdn/p640.jpg'], ['width' => 480, 'height' => 270, 'url' => 'https://cdn/p480.jpg']],
            'tracks' => [['src' => 'https://cdn/de.vtt', 'srclang' => 'de', 'label' => 'Deutsch', 'kind' => 'captions', 'default' => true]],
        ]);

        $slot = $this->slotWithUuid('uuid-1');
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

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
        // The theme is reduced to valid keys and values (#41).
        static::assertSame(['loopButton' => true, 'accentColor' => '#C72876'], $embed['theme']);
        // Responsive posters for srcset (#43).
        static::assertSame('https://cdn/p480.jpg 480w, https://cdn/p640.jpg 640w', $embed['posterSrcsetAttr']);
        static::assertCount(2, $embed['posterSrcset']);
        // Subtitle tracks for the <track> elements (#42).
        static::assertSame([['src' => 'https://cdn/de.vtt', 'srclang' => 'de', 'label' => 'Deutsch', 'kind' => 'captions', 'default' => true]], $embed['tracks']);
    }

    /**
     * A video without HLS and MP4 (processing failed or not finished) cannot be played; it is
     * hidden like a failed lookup instead of rendering an empty player (issue #9).
     */
    public function testEmbedWithoutPlayableSourcesIsAnError(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['poster' => 'https://cdn/poster.jpg', 'sources' => []]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(static::stringContains('no playable sources'));

        $slot = $this->slotWithUuid('uuid-2');
        $this->resolver($client, $logger)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        static::assertTrue($slot->getData()->hasError());
    }

    /**
     * The page is tagged with the element's library, so library changes invalidate it (#29).
     */
    public function testEnrichMarksTheConfiguredLibrary(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']]]);
        $client->expects(static::once())->method('markLibraryRendered')->with('lib-1', 'uuid-1');

        $this->resolver($client)->enrich($this->slotWithUuid('uuid-1', ['libraryId' => 'lib-1']), $this->createStub(ResolverContext::class), new ElementDataCollection());
    }

    public function testEnrichWithoutLibraryMarksNone(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn([]);
        $client->expects(static::never())->method('markLibraryRendered');

        $this->resolver($client)->enrich($this->slotWithUuid('uuid-1', ['libraryId' => null]), $this->createStub(ResolverContext::class), new ElementDataCollection());
    }

    public function testCollectQueuesTheVideo(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('queueEmbed')->with('uuid-1');

        static::assertNull($this->resolver($client)->collect($this->slotWithUuid('uuid-1'), $this->createStub(ResolverContext::class)));
    }

    public function testEnrichHandlesApiErrorGracefully(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new VideoOptimizerApiException(404, 'Not found'));

        $slot = $this->slotWithUuid('missing');
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

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
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

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

    public function testEmbedCarriesTheVideoObjectJsonLdOncePerVideo(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn([
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/p.jpg',
            'title' => 'Demo',
            'duration' => 65,
            'uploadDate' => '2026-03-01T10:00:00Z',
        ]);
        $client->method('jsonLdEnabled')->willReturn(true);
        $client->method('embedBaseUrl')->willReturn('https://player.example');
        $client->method('claimVideoJsonLd')->willReturnOnConsecutiveCalls(true, false);
        $resolver = $this->resolver($client);

        $first = $this->slotWithUuid('uuid-1');
        $second = $this->slotWithUuid('uuid-1');
        $resolver->enrich($first, $this->createStub(ResolverContext::class), new ElementDataCollection());
        $resolver->enrich($second, $this->createStub(ResolverContext::class), new ElementDataCollection());

        $json = json_decode((string) $first->getData()->getEmbed()['jsonLd'], true);
        static::assertSame('VideoObject', $json['@type']);
        static::assertSame('https://player.example/embed/uuid-1', $json['embedUrl']);
        static::assertSame('2026-03-01T10:00:00+00:00', $json['uploadDate']);
        static::assertNull($second->getData()->getEmbed()['jsonLd']);
    }

    public function testNoJsonLdWhenTheEmbedHasNoUploadDate(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/master.m3u8', 'codec' => 'hls']], 'title' => 'Demo', 'poster' => 'https://cdn/p.jpg', 'uploadDate' => null]);
        $client->method('jsonLdEnabled')->willReturn(true);
        $client->method('claimVideoJsonLd')->willReturn(true);
        $client->method('embedBaseUrl')->willReturn('https://player.example');

        $slot = $this->slotWithUuid('uuid-1');
        $this->resolver($client)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        // No upload date, no structured data (search engines flag the missing field).
        static::assertNull($slot->getData()->getEmbed()['jsonLd']);
    }

    public function testNoJsonLdWhenSwitchedOff(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/master.m3u8', 'codec' => 'hls']], 'title' => 'Demo']);
        $client->method('jsonLdEnabled')->willReturn(false);
        $client->expects(static::never())->method('claimVideoJsonLd');

        $slot = $this->slotWithUuid('uuid-1');
        $this->resolver($client)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        static::assertNull($slot->getData()->getEmbed()['jsonLd']);
    }

    public function testEmbedModeFlagsErrorWhenUpstreamVideoIsGone(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('getEmbed')->with('uuid-deleted')
            ->willThrowException(new VideoOptimizerApiException(404, 'Not found'));
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-deleted', ['playerMode' => 'embed']);
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

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
        $resolver = $this->resolver($client);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

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
        $resolver = $this->resolver($client);
        // A misconfigured embedBaseUrl must become a per-element error, not an exception that
        // takes down the whole CMS page render.
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertTrue($data->hasError());
        static::assertSame('', $data->getEmbedUrl());
        static::assertNull($data->getEmbed());
    }

    public function testInvalidEmbedBaseUrlLogsAWarning(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('embedBaseUrl')->willThrowException(new InvalidApiBaseUrlException('bad url'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(
            static::isString(),
            ['uuid' => 'uuid-bad-embed-base', 'element' => 'scalecommerce-vo-video', 'error' => 'bad url']
        );

        $slot = $this->slotWithUuid('uuid-bad-embed-base');
        $resolver = $this->resolver($client, $logger);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());
    }

    public function testEmbedLookupFailureLogsAWarning(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new VideoOptimizerApiException(404, 'Not found'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('warning')->with(
            static::isString(),
            ['uuid' => 'missing', 'element' => 'scalecommerce-vo-video', 'error' => 'Not found']
        );

        $slot = $this->slotWithUuid('missing');
        $resolver = $this->resolver($client, $logger);
        $resolver->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());
    }

    private function resolver(VideoOptimizerClient $client, ?LoggerInterface $logger = null): VideoOptimizerElementResolver
    {
        return new VideoOptimizerElementResolver($client, $logger ?? new NullLogger());
    }

    /**
     * The format option fixes the surface ratio, the video covers it, the focal point moves it (#47).
     */
    public function testFormatAndFocalPointBecomeTheLayoutAndTheHostedPlayerCovers(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/v.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '720p']], 'resolution' => '1920x1080']);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-1', ['format' => '9-16', 'focalX' => 20, 'focalY' => '80', 'miniPlayer' => true, 'playerMode' => 'embed']);
        $this->resolver($client)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame(['format' => '9-16', 'aspect' => '9 / 16', 'portrait' => true, 'cover' => true, 'position' => '20% 80%', 'mini' => true], $data->getLayout());
        static::assertStringContainsString('&fit=cover', $data->getEmbedUrl());
    }

    public function testWithoutFormatTheOriginalRatioStaysAndNothingCovers(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(['sources' => [['src' => 'https://cdn/v.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '720p']]]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slotWithUuid('uuid-1', ['format' => 'bogus', 'focalX' => 150, 'focalY' => 'x']);
        $this->resolver($client)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertSame(['format' => 'original', 'aspect' => null, 'portrait' => false, 'cover' => false, 'position' => '100% 50%', 'mini' => false], $data->getLayout());
        static::assertStringNotContainsString('fit=', $data->getEmbedUrl());
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
