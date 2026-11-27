<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\PluginUserAgent;

class PluginUserAgentTest extends TestCase
{
    /**
     * The format VideoOptimizer parses with a regex (admin overview of plugin versions per organization).
     */
    private const FORMAT = '#^VideoOptimizer-Shopware/[^\s/()]+(?: \((?:[^;()]+)(?:; [^;()]+)*\))?$#';

    public function testBuildsTheDocumentedFormat(): void
    {
        $userAgent = PluginUserAgent::build('1.0.3', '6.6.10.4', '8.3.14', 'https://shop.example.com');

        static::assertSame('VideoOptimizer-Shopware/1.0.3 (Shopware 6.6.10.4; PHP 8.3.14; +https://shop.example.com)', $userAgent);
        static::assertMatchesRegularExpression(self::FORMAT, $userAgent);
    }

    public function testStripsTheTagPrefixFromThePluginVersion(): void
    {
        static::assertStringStartsWith('VideoOptimizer-Shopware/1.0.3 (', PluginUserAgent::build('v1.0.3', '6.7.16.2', '8.5.0', null));
        static::assertStringStartsWith('VideoOptimizer-Shopware/dev-main (', PluginUserAgent::build('dev-main', '6.7.16.2', '8.5.0', null));
    }

    public function testOmitsTheSiteUrlWhenUnavailable(): void
    {
        static::assertSame('VideoOptimizer-Shopware/1.0.3 (Shopware 6.7.16.2; PHP 8.5.0)', PluginUserAgent::build('1.0.3', '6.7.16.2', '8.5.0', null));
        static::assertSame('VideoOptimizer-Shopware/1.0.3 (Shopware 6.7.16.2; PHP 8.5.0)', PluginUserAgent::build('1.0.3', '6.7.16.2', '8.5.0', ''));
    }

    public function testOmitsEmptyPartsAndFallsBackToUnknownVersion(): void
    {
        static::assertSame('VideoOptimizer-Shopware/unknown (PHP 8.5.0)', PluginUserAgent::build(null, '', '8.5.0', null));
        static::assertSame('VideoOptimizer-Shopware/unknown (PHP 8.5.0)', PluginUserAgent::build(' ;() ', ';', '8.5.0', null));
        static::assertSame('VideoOptimizer-Shopware/unknown', PluginUserAgent::build('', null, null, null));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function siteUrls(): iterable
    {
        yield 'path kept, trailing slash dropped' => ['https://shop.example.com/de/', '+https://shop.example.com/de'];
        yield 'port kept, case normalized' => ['HTTP://Shop.Example.com:8000', '+http://shop.example.com:8000'];
        yield 'userinfo, query and fragment dropped' => ['https://user:secret@shop.example.com/?a=1#x', '+https://shop.example.com'];
        yield 'delimiters removed' => ['https://shop.example.com/a;b(c)', '+https://shop.example.com/abc'];
    }

    #[DataProvider('siteUrls')]
    public function testNormalizesTheSiteUrl(string $appUrl, string $expectedPart): void
    {
        $userAgent = PluginUserAgent::build('1.0.3', '6.7.16.2', '8.5.0', $appUrl);

        static::assertSame('VideoOptimizer-Shopware/1.0.3 (Shopware 6.7.16.2; PHP 8.5.0; ' . $expectedPart . ')', $userAgent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableSiteUrls(): iterable
    {
        yield 'no scheme' => ['shop.example.com'];
        yield 'other scheme' => ['ftp://shop.example.com'];
        yield 'no host' => ['https://'];
        yield 'garbage' => ['http:///:::'];
    }

    #[DataProvider('unusableSiteUrls')]
    public function testDropsUnusableSiteUrls(string $appUrl): void
    {
        static::assertSame('VideoOptimizer-Shopware/1.0.3 (Shopware 6.7.16.2; PHP 8.5.0)', PluginUserAgent::build('1.0.3', '6.7.16.2', '8.5.0', $appUrl));
    }

    public function testSanitizesValues(): void
    {
        $userAgent = PluginUserAgent::build("1.0/3 beta\r\n", "6.7(1);\x00x\\", "8.5\tü", 'https://shop.example.com');

        static::assertSame('VideoOptimizer-Shopware/1.03beta (Shopware 6.71x; PHP 8.5; +https://shop.example.com)', $userAgent);
        static::assertMatchesRegularExpression(self::FORMAT, $userAgent);
    }

    public function testCapsEachPartAt100Characters(): void
    {
        $userAgent = PluginUserAgent::build(str_repeat('1', 150), str_repeat('6', 150), '8.5.0', 'https://shop.example.com/' . str_repeat('a', 150));

        static::assertSame(
            'VideoOptimizer-Shopware/' . str_repeat('1', 100)
            . ' (Shopware ' . str_repeat('6', 100)
            . '; PHP 8.5.0; +https://shop.example.com/' . str_repeat('a', 100 - \strlen('https://shop.example.com/')) . ')',
            $userAgent,
        );
    }

    public function testValueUsesTheRunningPhpVersionAndIsStable(): void
    {
        $agent = new PluginUserAgent('6.7.16.2', 'https://shop.example.com', 'v1.2.0');

        static::assertSame('VideoOptimizer-Shopware/1.2.0 (Shopware 6.7.16.2; PHP ' . \PHP_VERSION . '; +https://shop.example.com)', $agent->value());
        static::assertSame($agent->value(), $agent->value());
    }

    public function testResolvesThePluginVersionFromTheComposerJson(): void
    {
        // In the test setup the plugin is not installed through Composer, like a ZIP upload.
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true);
        static::assertIsArray($composer);

        static::assertSame($composer['version'], PluginUserAgent::resolvePluginVersion());
        static::assertStringStartsWith('VideoOptimizer-Shopware/' . $composer['version'] . ' (', (new PluginUserAgent())->value());
    }

    public function testResolvesAnEmptyVersionWithoutComposerJson(): void
    {
        static::assertSame('', PluginUserAgent::resolvePluginVersion(__DIR__ . '/does-not-exist.json'));
    }
}
