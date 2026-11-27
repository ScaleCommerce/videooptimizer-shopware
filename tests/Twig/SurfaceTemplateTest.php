<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Renders the shared surface macro with the real template file (without Shopware's Twig extensions;
 * `trans` returns the snippet key).
 */
class SurfaceTemplateTest extends TestCase
{
    /**
     * @param array<string, mixed> $embed
     * @param array<string, mixed> $options
     */
    private function render(array $embed, string $presentation = 'direct', string $mode = 'native', array $options = []): string
    {
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['page' => '{% import "@ScaleVideoOptimizer/storefront/element/_scalecommerce-vo-surface.html.twig" as vo %}{{ vo.surface(embed, presentation, mode, "https://player.example/embed/abc?x=1", "Demo", options) }}']),
            $views,
        ]), ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('page', ['embed' => $embed, 'presentation' => $presentation, 'mode' => $mode, 'options' => $options]);
    }

    public function testThemeIsPassedToThePlayerOptionsAndAccentIsACustomProperty(): void
    {
        $html = $this->render([
            'mp4' => 'https://cdn/v.mp4',
            'poster' => 'https://cdn/p.jpg',
            'theme' => ['accentColor' => '#C72876', 'loopButton' => true],
        ]);

        static::assertStringContainsString('--vo-accent: #C72876', $html);
        static::assertStringContainsString('&quot;theme&quot;:{&quot;accentColor&quot;:&quot;#C72876&quot;,&quot;loopButton&quot;:true}', $html);
    }

    public function testFacadePlayButtonFollowsTheTheme(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'theme' => ['playButtonShape' => 'rectangle', 'playButtonSize' => 'small', 'bigPlayButton' => false]], 'facade');

        static::assertStringContainsString('scalecommerce-vo-surface__play--rectangle', $html);
        static::assertStringContainsString('scalecommerce-vo-surface__play--small', $html);
        static::assertStringContainsString('scalecommerce-vo-surface__play--hidden', $html);
    }

    public function testWithoutThemeTheDefaultsApply(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4'], 'facade');

        static::assertStringNotContainsString('--vo-accent', $html);
        static::assertStringContainsString('scalecommerce-vo-surface__play--round scalecommerce-vo-surface__play--medium"', $html);
    }

    public function testDirectPlayerRendersTracksAndCrossorigin(): void
    {
        $html = $this->render([
            'mp4' => 'https://cdn/v.mp4',
            'tracks' => [
                ['src' => 'https://cdn/de.vtt?v=2', 'srclang' => 'de', 'label' => 'Deutsch', 'kind' => 'subtitles', 'default' => true],
                ['src' => 'https://cdn/en.vtt', 'srclang' => 'en', 'label' => 'English', 'kind' => 'captions', 'default' => false],
            ],
        ]);

        static::assertStringContainsString('crossorigin="anonymous"', $html);
        static::assertStringContainsString('<track kind="subtitles" srclang="de" label="Deutsch" src="https://cdn/de.vtt?v=2" default>', $html);
        static::assertStringContainsString('<track kind="captions" srclang="en" label="English" src="https://cdn/en.vtt">', $html);
    }

    public function testNoTracksNoCrossorigin(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4']);

        static::assertStringNotContainsString('crossorigin', $html);
        static::assertStringNotContainsString('<track', $html);
    }
}
