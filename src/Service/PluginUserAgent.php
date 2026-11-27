<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Composer\InstalledVersions;

/**
 * The User-Agent the plugin sends with every request to the VideoOptimizer API, so VideoOptimizer
 * can tell which plugin, Shopware and PHP versions a shop runs:
 *
 *     VideoOptimizer-Shopware/1.0.3 (Shopware 6.6.10.4; PHP 8.3.14; +https://shop.example.com)
 *
 * VideoOptimizer parses this with a regex, so keep the format stable. Computed once per process.
 */
class PluginUserAgent
{
    public const PACKAGE = 'scalecommerce/videooptimizer-shopware';

    private const PRODUCT = 'VideoOptimizer-Shopware';
    private const MAX_PART_LENGTH = 100;

    private ?string $value = null;

    /**
     * @param string|null $shopwareVersion the kernel's `kernel.shopware_version`
     * @param string|null $siteUrl the shop's public base URL (`APP_URL`)
     * @param string|null $pluginVersion overrides the resolved plugin version (tests)
     */
    public function __construct(
        private readonly ?string $shopwareVersion = null,
        private readonly ?string $siteUrl = null,
        private readonly ?string $pluginVersion = null,
    ) {
    }

    public function value(): string
    {
        return $this->value ??= self::build(
            $this->pluginVersion ?? self::resolvePluginVersion(),
            $this->shopwareVersion,
            \PHP_VERSION,
            $this->siteUrl,
        );
    }

    /**
     * Comment parts with an empty value are left out; a missing plugin version becomes "unknown".
     */
    public static function build(?string $pluginVersion, ?string $shopwareVersion, ?string $phpVersion, ?string $siteUrl): string
    {
        // The version is part of the product token: no spaces or slashes there, and no tag prefix.
        $version = preg_replace('#[\s/]+#', '', self::sanitize((string) $pluginVersion)) ?? '';
        $version = preg_replace('/^v(?=\d)/i', '', $version) ?? '';

        $parts = [];
        $shopware = self::sanitize((string) $shopwareVersion);
        if ($shopware !== '') {
            $parts[] = 'Shopware ' . $shopware;
        }
        $php = self::sanitize((string) $phpVersion);
        if ($php !== '') {
            $parts[] = 'PHP ' . $php;
        }
        $site = self::normalizeSiteUrl((string) $siteUrl);
        if ($site !== '') {
            $parts[] = '+' . $site;
        }

        $userAgent = self::PRODUCT . '/' . ($version !== '' ? $version : 'unknown');

        return $parts === [] ? $userAgent : $userAgent . ' (' . implode('; ', $parts) . ')';
    }

    /**
     * The installed package's version if Composer installed the plugin, otherwise the version in
     * the plugin's own composer.json (plugins uploaded as ZIP to custom/plugins are not known to
     * Composer). Empty if neither is available.
     *
     * @param (callable(): ?string)|null $installedVersion the Composer-installed version, null if not
     *                                                     installed; replaceable so tests do not depend
     *                                                     on how the plugin is installed
     */
    public static function resolvePluginVersion(?string $composerJsonPath = null, ?callable $installedVersion = null): string
    {
        $version = $installedVersion !== null ? $installedVersion() : self::composerInstalledVersion();
        if (\is_string($version) && $version !== '') {
            return $version;
        }

        $path = $composerJsonPath ?? \dirname(__DIR__, 2) . '/composer.json';
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $content = file_get_contents($path);
        $composer = \is_string($content) ? json_decode($content, true) : null;
        $version = \is_array($composer) ? ($composer['version'] ?? null) : null;

        return \is_string($version) ? $version : '';
    }

    private static function composerInstalledVersion(): ?string
    {
        return InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getPrettyVersion(self::PACKAGE) : null;
    }

    /**
     * Scheme, host, port and path only: userinfo, query and fragment never leave the shop.
     */
    private static function normalizeSiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return '';
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return self::sanitize($scheme . '://' . $host . $port . $path);
    }

    /**
     * Printable ASCII only, without the characters that delimit the User-Agent comment
     * (";", "(", ")") or escape inside it ("\"), at most MAX_PART_LENGTH characters.
     */
    private static function sanitize(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]+/', '', $value) ?? '';
        $value = str_replace([';', '(', ')', '\\'], '', $value);
        $value = trim(preg_replace('/ {2,}/', ' ', $value) ?? '');

        return trim(substr($value, 0, self::MAX_PART_LENGTH));
    }
}
