<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Twig;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoChaptersStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoListStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

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

    public function testSingleVideoMiniPlayerIsOptIn(): void
    {
        $on = $this->renderSingle('native', ['mini' => true]);
        $off = $this->renderSingle('native', ['mini' => false]);

        static::assertStringContainsString('data-scalecommerce-vo-mini="true" data-vo-label-close="scalecommerce-vo.storefront.close"', $on);
        static::assertStringNotContainsString('data-scalecommerce-vo-mini', $off);
        static::assertStringContainsString('data-scalecommerce-vo-mini', $this->renderSingle('embed', ['mini' => true]));
    }

    public function testChaptersMiniPlayerWrapsOnlyThePlayerNotTheScenes(): void
    {
        $data = new VideoChaptersStruct();
        $data->setVideoUuid('abc');
        $data->setPlayerMode('native');
        $data->setEmbed(['mp4' => 'https://cdn/v.mp4']);
        $data->setLayout(['mini' => true]);
        $data->setScenes([['index' => 0, 'start' => 5, 'time' => '0:05', 'thumb' => 'https://x/0', 'title' => '']]);
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        $html = $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-chapters.html.twig', ['element' => ['data' => $data, 'config' => []]]);

        static::assertSame(1, substr_count($html, 'data-scalecommerce-vo-mini'));
        static::assertLessThan(strpos($html, 'scalecommerce-vo-scenes'), strpos($html, 'data-scalecommerce-vo-mini'));
    }

    public function testSingleVideoWithoutLayoutKeepsTheVideoRatio(): void
    {
        $html = $this->renderSingle('native', [], ['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1260 / 750']);

        static::assertStringContainsString('--scvo-aspect: 1260 / 750', $html);
        static::assertStringNotContainsString('--scvo-pos', $html);
    }

    /**
     * @param list<array<string, mixed>> $scenes
     */
    private function renderChapters(string $mode, array $scenes, ?string $headline = 'Tour'): string
    {
        $data = new VideoChaptersStruct();
        $data->setVideoUuid('abc');
        $data->setPlayerMode($mode);
        $data->setEmbedUrl('https://player.example/embed/abc?autoplay=0');
        $data->setEmbed(['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '1920 / 1080']);
        $data->setScenes($scenes);
        $data->setHeadline($headline);

        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-chapters.html.twig', ['element' => ['data' => $data, 'config' => []]]);
    }

    public function testChaptersRendersThePlayerDirectlyAndTheSceneButtons(): void
    {
        $html = $this->renderChapters('native', [
            ['index' => 0, 'start' => 5, 'time' => '0:05', 'thumb' => 'https://api.example/videos/abc/thumbnails/0', 'title' => ''],
            ['index' => 9, 'start' => 59, 'time' => '0:59', 'thumb' => 'https://api.example/videos/abc/thumbnails/9', 'title' => 'Finale <b>'],
        ]);

        static::assertStringContainsString('data-scalecommerce-vo-chapters="true"', $html);
        static::assertStringContainsString('<h2 class="scalecommerce-vo-chapters__headline">Tour</h2>', $html);
        static::assertStringContainsString('data-scalecommerce-vo-player="true"', $html);
        static::assertSame(2, substr_count($html, 'data-vo-scene="'));
        static::assertStringContainsString('data-vo-scene="59"', $html);
        static::assertMatchesRegularExpression('#<img class="scalecommerce-vo-scene__image" src="https://api.example/videos/abc/thumbnails/9" alt="" width="320" height="180" loading="lazy" decoding="async">#', $html);
        static::assertStringContainsString('scalecommerce-vo-scene__label">Finale &lt;b&gt;<', $html);
        static::assertStringContainsString('scalecommerce-vo-scene__time">0:59<', $html);
    }

    public function testChaptersWithTheHostedPlayerUsesTheIframe(): void
    {
        $html = $this->renderChapters('embed', [['index' => 0, 'start' => 5, 'time' => '0:05', 'thumb' => 'https://x/0', 'title' => '']], null);

        static::assertStringContainsString('<iframe src="https://player.example/embed/abc?autoplay=0"', $html);
        static::assertStringNotContainsString('chapters__headline', $html);
        static::assertStringContainsString('data-vo-scene="5"', $html);
    }

    public function testChaptersWithoutScenesShowsOnlyThePlayer(): void
    {
        $html = $this->renderChapters('native', []);

        static::assertStringContainsString('scalecommerce-vo-chapters__player', $html);
        static::assertStringNotContainsString('scalecommerce-vo-scenes', $html);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function renderList(VideoListStruct $data, array $query = []): string
    {
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('seoUrl', static fn (string $route, array $params): string => '/detail/' . $params['navigationId']));

        return $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-video-list.html.twig', [
            'element' => ['data' => $data, 'config' => []],
            'app' => ['request' => ['query' => ['all' => $query]]],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(string $uuid = 'abc'): array
    {
        return [
            'videoUuid' => $uuid,
            'title' => 'Tour <1>',
            'durationLabel' => '1:05',
            'playerMode' => 'embed',
            'embedUrl' => 'https://player.example/embed/' . $uuid . '?x=1',
            'embed' => ['poster' => 'https://p/a.jpg', 'posterSrcsetAttr' => 'https://p/480.jpg 480w', 'aspectRatio' => '16 / 9', 'theme' => [], 'tracks' => []],
        ];
    }

    public function testListCardsLinkToTheDetailPageWithTheVideoInTheUrl(): void
    {
        $data = new VideoListStruct();
        $data->setPresentation('detail');
        $data->setDetailCategoryId('0123456789abcdef0123456789abcdef');
        $data->setItems([self::card()]);

        $html = $this->renderList($data);

        static::assertStringContainsString('href="/detail/0123456789abcdef0123456789abcdef?vo_video=abc"', $html);
        static::assertStringContainsString('srcset="https://p/480.jpg 480w"', $html);
        static::assertStringContainsString('Tour &lt;1&gt;', $html);
        static::assertStringContainsString('scalecommerce-vo-video-list__duration">1:05<', $html);
        static::assertStringNotContainsString('data-vo-action', $html);
    }

    public function testListCardsOpenTheHostedPlayerInTheLightboxWithoutADetailPage(): void
    {
        $data = new VideoListStruct();
        $data->setItems([self::card('a'), self::card('b')]);

        $html = $this->renderList($data);

        static::assertSame(2, substr_count($html, 'data-vo-action="lightbox"'));
        static::assertStringContainsString('data-vo-player-mode="embed"', $html);
        static::assertStringContainsString('data-vo-embed-url="https://player.example/embed/b?x=1"', $html);
    }

    public function testListPaginationKeepsTheOtherQueryParameters(): void
    {
        $data = new VideoListStruct();
        $data->setItems([self::card()]);
        $data->setCursorParam('vo_cursor_s1');
        $data->setCursor('cur_1');
        $data->setNextCursor('cur_2');

        $html = $this->renderList($data, ['color' => 'red', 'vo_cursor_s1' => 'cur_1', 'vo_cursor_other' => 'x']);

        static::assertStringContainsString('rel="next" href="?color=red&amp;vo_cursor_other=x&amp;vo_cursor_s1=cur_2"', $html);
        static::assertStringContainsString('href="?color=red&amp;vo_cursor_other=x">scalecommerce-vo.storefront.listFirst', $html);
    }

    public function testListWithoutMoreVideosHasNoPagination(): void
    {
        $data = new VideoListStruct();
        $data->setItems([self::card()]);

        static::assertStringNotContainsString('pagination', $this->renderList($data));
        static::assertSame('', trim($this->renderList(new VideoListStruct())));
    }

    private function renderDetail(VideoDetailStruct $data): string
    {
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 2) . '/src/Resources/views', 'ScaleVideoOptimizer');
        $twig = new Environment($views, ['autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('@ScaleVideoOptimizer/storefront/element/cms-element-scalecommerce-vo-video-detail.html.twig', ['element' => ['data' => $data, 'config' => []]]);
    }

    public function testDetailShowsTitleAndPlayer(): void
    {
        $data = new VideoDetailStruct();
        $data->setVideoUuid('abc');
        $data->setTitle('Product <tour>');
        $data->setDurationLabel('1:05');
        $data->setPlayerMode('native');
        $data->setEmbed(['mp4' => 'https://cdn/v.mp4', 'aspectRatio' => '16 / 9', 'jsonLd' => '{"@type":"VideoObject"}']);

        $html = $this->renderDetail($data);

        static::assertStringContainsString('<h1 class="scalecommerce-vo-video-detail__title">Product &lt;tour&gt;</h1>', $html);
        static::assertStringContainsString('data-scalecommerce-vo-player="true"', $html);
        static::assertStringContainsString('ld+json', $html);
        static::assertStringContainsString('__meta">1:05<', $html);
    }

    public function testDetailNotFoundIsNeutral(): void
    {
        $data = new VideoDetailStruct();
        $data->setNotFound(true);

        $html = $this->renderDetail($data);

        static::assertStringContainsString('role="status"', $html);
        static::assertStringContainsString('scalecommerce-vo.storefront.videoNotFound', $html);
        static::assertStringNotContainsString('<video', $html);
        static::assertStringNotContainsString('<iframe', $html);
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
