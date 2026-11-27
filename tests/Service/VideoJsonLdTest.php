<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use ScaleCommerce\VideoOptimizer\Service\VideoJsonLd;

class VideoJsonLdTest extends TestCase
{
    public function testBuildsAVideoObject(): void
    {
        $json = VideoJsonLd::build(
            ['title' => 'Imagevideo', 'hls' => 'https://cdn/master.m3u8', 'mp4' => 'https://cdn/1080p.mp4', 'poster' => 'https://p/t.jpg', 'duration' => 65],
            'https://player.example/embed/abc',
            '2026-03-01T10:00:00+01:00',
        );

        static::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => 'Imagevideo',
            'description' => 'Imagevideo',
            'thumbnailUrl' => ['https://p/t.jpg'],
            'uploadDate' => '2026-03-01T09:00:00+00:00',
            'duration' => 'PT1M5S',
            'contentUrl' => 'https://cdn/master.m3u8',
            'embedUrl' => 'https://player.example/embed/abc',
        ], json_decode((string) $json, true));
    }

    public function testFallsBackToMp4AndOmitsWhatIsUnknown(): void
    {
        $data = json_decode((string) VideoJsonLd::build(['title' => 'T', 'mp4' => 'https://cdn/v.mp4'], 'https://player/embed/x', null), true);

        static::assertSame('https://cdn/v.mp4', $data['contentUrl']);
        static::assertArrayNotHasKey('uploadDate', $data);
        static::assertArrayNotHasKey('thumbnailUrl', $data);
        static::assertArrayNotHasKey('duration', $data);
    }

    public function testIsSafeInsideAScriptElement(): void
    {
        $json = (string) VideoJsonLd::build(['title' => '</script><script>alert(1)</script> & "x"', 'mp4' => 'https://cdn/v.mp4'], 'https://player/embed/x', null);

        static::assertStringNotContainsString('</script>', $json);
        static::assertStringNotContainsString('<', $json);
        static::assertSame('</script><script>alert(1)</script> & "x"', json_decode($json, true)['name']);
    }

    public function testNothingWithoutTitleOrMedia(): void
    {
        static::assertNull(VideoJsonLd::build(['title' => '', 'mp4' => 'https://cdn/v.mp4'], 'u', null));
        static::assertNull(VideoJsonLd::build(['title' => 'T'], 'u', null));
    }

    #[DataProvider('durations')]
    public function testDuration(mixed $seconds, ?string $expected): void
    {
        static::assertSame($expected, VideoJsonLd::duration($seconds));
    }

    public static function durations(): iterable
    {
        yield [65, 'PT1M5S'];
        yield [3600, 'PT1H'];
        yield [3725.4, 'PT1H2M5S'];
        yield [0.2, 'PT0S'];
        yield ['90', 'PT1M30S'];
        yield [0, null];
        yield [null, null];
        yield ['x', null];
    }

    public function testClaimIsGrantedOncePerVideo(): void
    {
        $state = new EmbedRenderState();

        static::assertTrue($state->claimJsonLd('a'));
        static::assertFalse($state->claimJsonLd('a'));
        static::assertTrue($state->claimJsonLd('b'));
        $state->reset();
        static::assertTrue($state->claimJsonLd('a'));
    }
}
