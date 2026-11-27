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
}
