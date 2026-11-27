<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Twig;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
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
     * @param array<string, mixed> $data
     */
    private function renderHero(array $data): string
    {
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-background-hero.html.twig', ['element' => ['data' => $data]]);
    }

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

    public function testFormatSetsTheSurfaceRatioFocalPointAndFit(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1920 / 1080'], 'facade', 'native', [
            'layout' => ['aspect' => '9 / 16', 'portrait' => true, 'cover' => true, 'position' => '20% 80%'],
        ]);

        static::assertStringContainsString('--scvo-aspect: 9 / 16', $html);
        static::assertStringContainsString('--scvo-pos: 20% 80%', $html);
        static::assertStringContainsString('scalecommerce-vo-surface--portrait', $html);
        static::assertStringContainsString('data-vo-fit="cover"', $html);
    }

    public function testWithoutFormatTheVideoRatioStays(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1260 / 750'], 'facade');

        static::assertStringContainsString('--scvo-aspect: 1260 / 750', $html);
        static::assertStringNotContainsString('data-vo-fit', $html);
        static::assertStringNotContainsString('--portrait', $html);
    }

    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $embed
     */
    private function renderSingle(string $mode, array $layout, array $embed = ['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1920 / 1080']): string
    {
        $data = new VideoOptimizerVideoStruct();
        $data->setVideoUuid('abc');
        $data->setPlayerMode($mode);
        $data->setEmbedUrl('https://player.example/embed/abc?fit=cover');
        $data->setEmbed($embed);
        $data->setLayout($layout);

        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-video.html.twig', ['element' => ['data' => $data, 'config' => []]]);
    }

    public function testSingleVideoAppliesFormatAndFocalPointToTheNativePlayer(): void
    {
        $html = $this->renderSingle('native', ['aspect' => '4 / 5', 'portrait' => true, 'cover' => true, 'position' => '10% 30%']);

        static::assertStringContainsString('scalecommerce-vo-video--portrait', $html);
        static::assertStringContainsString('--scvo-aspect: 4 / 5; --scvo-pos: 10% 30%', $html);
    }

    public function testSingleVideoAppliesTheFormatToTheHostedPlayerToo(): void
    {
        $html = $this->renderSingle('embed', ['aspect' => '1 / 1', 'portrait' => false, 'cover' => true, 'position' => '50% 50%']);

        static::assertStringContainsString('--scvo-aspect: 1 / 1', $html);
        static::assertStringContainsString('fit=cover', $html);
        static::assertStringNotContainsString('--portrait', $html);
    }

    public function testSingleVideoWithoutLayoutKeepsTheVideoRatio(): void
    {
        $html = $this->renderSingle('native', [], ['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1260 / 750']);

        static::assertStringContainsString('--scvo-aspect: 1260 / 750', $html);
        static::assertStringNotContainsString('--scvo-pos', $html);
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

    public function testFacadePosterIsResponsive(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'poster' => 'https://p/t.jpg', 'posterSrcsetAttr' => 'https://p/480.jpg 480w, https://p/1280.jpg 1280w'], 'facade', 'native', ['posterSizes' => '50vw']);

        static::assertStringContainsString('srcset="https://p/480.jpg 480w, https://p/1280.jpg 1280w"', $html);
        static::assertStringContainsString('sizes="50vw"', $html);
        static::assertStringContainsString('loading="lazy"', $html);
    }

    public function testFacadePosterWithoutSrcsetStaysASingleImage(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'poster' => 'https://p/t.jpg'], 'facade');

        static::assertStringContainsString('src="https://p/t.jpg"', $html);
        static::assertStringNotContainsString('srcset', $html);
    }

    private const HERO_EMBED = ['mp4' => 'https://cdn/v.mp4', 'poster' => 'https://p/t.jpg', 'posterSrcsetAttr' => 'https://p/480.jpg 480w, https://p/1280.jpg 1280w'];

    public function testPriorityHeroPreloadsItsResponsivePoster(): void
    {
        $html = $this->renderHero(['videoUuid' => 'u', 'embed' => self::HERO_EMBED, 'priority' => true, 'height' => 'large', 'overlay' => 'none']);

        static::assertStringNotContainsString('rel="preload"', $html);
        static::assertMatchesRegularExpression('/<img class="scalecommerce-vo-hero__poster"[^>]*srcset="[^"]+"[^>]*fetchpriority="high"/', $html);
        static::assertStringNotContainsString('loading="lazy"', $html);
        // The video carries the same poster URL (cache hit) for blocked autoplay.
        static::assertStringContainsString('poster="https://p/t.jpg"', explode('<video', $html)[1]);
    }

    public function testRegularHeroLoadsItsPosterLazily(): void
    {
        $html = $this->renderHero(['videoUuid' => 'u', 'embed' => self::HERO_EMBED, 'priority' => false, 'height' => 'large', 'overlay' => 'none']);

        static::assertStringNotContainsString('rel="preload"', $html);
        static::assertStringNotContainsString('fetchpriority', $html);
        static::assertStringContainsString('<img class="scalecommerce-vo-hero__poster"', $html);
        static::assertStringNotContainsString('loading="lazy"', $html);
        static::assertStringContainsString('decoding="async"', $html);
        static::assertStringNotContainsString('poster="', explode('<video', $html)[1]);
    }

    public function testHeroWithoutSrcsetKeepsThePosterAttribute(): void
    {
        $html = $this->renderHero(['videoUuid' => 'u', 'embed' => ['mp4' => 'https://cdn/v.mp4', 'poster' => 'https://p/t.jpg'], 'priority' => true, 'height' => 'large', 'overlay' => 'none']);

        static::assertStringContainsString('poster="https://p/t.jpg"', $html);
        static::assertStringNotContainsString('<img', $html);
    }

    public function testJsonLdIsOutputInAScriptElement(): void
    {
        $html = $this->render(['mp4' => 'https://cdn/v.mp4', 'jsonLd' => '{"@type":"VideoObject"}'], 'facade');

        static::assertStringContainsString('<script type="application/ld+json">{"@type":"VideoObject"}</script>', $html);
        static::assertStringNotContainsString('ld+json', $this->render(['mp4' => 'https://cdn/v.mp4', 'jsonLd' => null], 'facade'));
    }
}
