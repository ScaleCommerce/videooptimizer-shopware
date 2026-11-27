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
}
