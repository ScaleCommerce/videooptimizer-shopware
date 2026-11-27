<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\EmbedSources;

class EmbedSourcesTest extends TestCase
{
    public function testThemeKeepsKnownKeysWithValidValues(): void
    {
        $theme = EmbedSources::theme([
            'accentColor' => '#C72876',
            'controlBarBg' => 'rgba(0,0,0,0.7)',
            'playButtonShape' => 'rectangle',
            'playButtonSize' => 'large',
            'controls' => false,
            'bigPlayButton' => true,
            'loopButton' => true,
            'autoplay' => true,
            'mutedStart' => true,
            'interactive' => false,
            'preload' => 'none',
            'unknown' => 'x',
        ]);

        static::assertSame([
            'controls' => false,
            'bigPlayButton' => true,
            'loopButton' => true,
            'autoplay' => true,
            'mutedStart' => true,
            'interactive' => false,
            'accentColor' => '#C72876',
            'controlBarBg' => 'rgba(0,0,0,0.7)',
            'playButtonShape' => 'rectangle',
            'playButtonSize' => 'large',
            'preload' => 'none',
        ], $theme);
    }

    public function testThemeDropsInvalidValues(): void
    {
        $theme = EmbedSources::theme([
            'accentColor' => 'red;} body{display:none',
            'controlBarBg' => 'url(https://evil/x)',
            'playButtonShape' => 'star',
            'preload' => 'always',
            'controls' => 'yes',
        ]);

        static::assertSame([], $theme);
        static::assertSame([], EmbedSources::theme(null));
        static::assertSame([], EmbedSources::theme('dark'));
    }

    public function testTracksKeepRenderableEntries(): void
    {
        $tracks = EmbedSources::tracks([
            ['src' => 'https://cdn/s/de.vtt?v=2', 'srclang' => 'de', 'label' => 'Deutsch', 'kind' => 'subtitles', 'default' => true],
            ['src' => 'https://cdn/s/en-US.vtt', 'srclang' => 'en-US', 'label' => '', 'kind' => 'captions', 'default' => true],
            ['src' => 'http://cdn/insecure.vtt', 'srclang' => 'fr'],
            ['src' => 'https://cdn/x.vtt" onerror="x', 'srclang' => 'es'],
            ['src' => 'https://cdn/y.vtt', 'srclang' => 'de"><script>'],
            'junk',
        ]);

        static::assertSame([
            ['src' => 'https://cdn/s/de.vtt?v=2', 'srclang' => 'de', 'label' => 'Deutsch', 'kind' => 'subtitles', 'default' => true],
            // Label falls back to the language; only the first track stays default.
            ['src' => 'https://cdn/s/en-US.vtt', 'srclang' => 'en-US', 'label' => 'en-US', 'kind' => 'captions', 'default' => false],
        ], $tracks);
        static::assertSame([], EmbedSources::tracks(null));
    }

    public function testPosterSrcsetIsSortedAndFiltered(): void
    {
        $posters = EmbedSources::posterSrcset([
            ['width' => 1280, 'height' => 720, 'url' => 'https://poster/poster_1280.jpg'],
            ['width' => 480, 'height' => 270, 'url' => 'https://poster/poster_480.jpg'],
            ['width' => 640, 'height' => 360, 'url' => 'http://poster/insecure.jpg'],
            ['width' => 0, 'height' => 360, 'url' => 'https://poster/zero.jpg'],
            ['width' => 800, 'height' => 450, 'url' => 'https://poster/a.jpg, https://evil/b.jpg'],
            ['width' => '1920', 'height' => 1080, 'url' => 'https://poster/string.jpg'],
        ]);

        static::assertSame([
            ['width' => 480, 'height' => 270, 'url' => 'https://poster/poster_480.jpg'],
            ['width' => 1280, 'height' => 720, 'url' => 'https://poster/poster_1280.jpg'],
        ], $posters);
        static::assertSame('https://poster/poster_480.jpg 480w, https://poster/poster_1280.jpg 1280w', EmbedSources::srcsetAttribute($posters));
        static::assertSame([], EmbedSources::posterSrcset(null));
        static::assertSame('', EmbedSources::srcsetAttribute([]));
    }
}
