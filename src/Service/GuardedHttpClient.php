<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClientTrait;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The shared HTTP client behind a guard against private networks (issue #33): every connection,
 * redirect targets included, is checked against its resolved IP, so the configurable base URLs
 * cannot reach internal services.
 *
 * The guard stands aside where it cannot work: behind an outbound proxy the connection goes to the
 * proxy, which is usually a private address, and the proxy resolves the host names itself, so the
 * proxy has to filter. Hosts listed in no_proxy are connected to directly and stay guarded. It can
 * also be switched off in the plugin configuration (`blockPrivateNetworks`), e.g. for a
 * self-hosted VideoOptimizer on a private network.
 */
final class GuardedHttpClient implements HttpClientInterface, ResetInterface
{
    private const SWITCH = 'ScaleVideoOptimizer.config.blockPrivateNetworks';

    private ?NoPrivateNetworkHttpClient $guarded = null;

    /**
     * @param string|null $configuredProxy `framework.http_client.default_options.proxy`, null if not configured; an
     *                                     empty string is configured but empty, which is no proxy (the environment is ignored then, like Symfony does)
     * @param string|null $configuredNoProxy `framework.http_client.default_options.no_proxy`
     * @param array<string, mixed>|null $server the environment proxy variables are read from, `$_SERVER` like Symfony's HttpClient does
     */
    public function __construct(
        private HttpClientInterface $inner,
        private readonly SystemConfigService $systemConfig,
        private readonly ?string $configuredProxy = null,
        private readonly ?string $configuredNoProxy = null,
        private readonly ?array $server = null,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $maxRedirects = $options['max_redirects'] ?? 20;
        if (!\is_int($maxRedirects) || $maxRedirects <= 0 || !\in_array($method, ['GET', 'HEAD'], true)) {
            return $this->clientFor($url, $options)->request($method, $url, $options);
        }

        return $this->followRedirects($method, $url, $options, $maxRedirects);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $list = $responses instanceof ResponseInterface ? [$responses] : $responses;
        foreach ($list as $response) {
            // The guard's responses are async wrappers that only its own stream() can read (it also
            // passes plain ones through); without any, the decorated client reads them itself.
            if ($response instanceof AsyncResponse) {
                return $this->guarded()->stream($responses, $timeout);
            }
        }

        return $this->inner->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withOptions($options);
        $clone->guarded = null;

        return $clone;
    }

    public function reset(): void
    {
        $this->guarded?->reset();
        if ($this->inner instanceof ResetInterface) {
            $this->inner->reset();
        }
    }

    /**
     * Follows redirects hop by hop, so the guard decision (proxy or not, see usesProxy()) is made for
     * every target: the decorated client would otherwise decide about the proxy again for each hop
     * (curl: no_proxy, scheme) and send a redirect to e.g. a no_proxy host out unchecked. Only GET and
     * HEAD are followed; Authorization and Cookie are dropped on a cross-origin hop like Symfony does.
     *
     * @param array<string, mixed> $options
     */
    private function followRedirects(string $method, string $url, array $options, int $maxRedirects): ResponseInterface
    {
        $options['max_redirects'] = 0;
        $origin = $this->origin($url);

        for ($hop = 0;; ++$hop) {
            $response = $this->clientFor($url, $options)->request($method, $url, $options);
            $status = $response->getStatusCode();
            if ($hop >= $maxRedirects || $status < 300 || $status >= 400) {
                return $response;
            }
            // From the Location header: not every transport reports redirect_url when it is told not to follow.
            $next = $this->resolveLocation($url, $response->getHeaders(false)['location'][0] ?? null);
            if ($next === null) {
                return $response;
            }

            $url = $next;
            if ($this->origin($url) !== $origin) {
                $options['headers'] = $this->withoutCredentials($options['headers'] ?? []);
            }
        }
    }

    private function resolveLocation(string $base, ?string $location): ?string
    {
        if ($location === null || $location === '') {
            return null;
        }
        $resolver = new class {
            use HttpClientTrait {
                parseUrl as public;
                resolveUrl as public;
            }
        };

        try {
            return implode('', $resolver::resolveUrl($resolver::parseUrl($location), $resolver::parseUrl($base)));
        } catch (InvalidArgumentException) {
            // Like the transports, ignore locations that are not valid URLs.
            return null;
        }
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return $scheme . '://' . strtolower((string) ($parts['host'] ?? '')) . ':' . ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }

    /**
     * @param array<array-key, mixed> $headers "Name: value" lines or name => value(s)
     *
     * @return array<array-key, mixed>
     */
    private function withoutCredentials(array $headers): array
    {
        return array_filter($headers, static function (mixed $value, int|string $key): bool {
            $name = \is_string($key) ? $key : (\is_string($value) ? explode(':', $value, 2)[0] : '');

            return !\in_array(strtolower(trim($name)), ['authorization', 'cookie'], true);
        }, \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function clientFor(string $url, array $options): HttpClientInterface
    {
        return $this->isGuarded($url, $options) ? $this->guarded() : $this->inner;
    }

    private function guarded(): NoPrivateNetworkHttpClient
    {
        return $this->guarded ??= new NoPrivateNetworkHttpClient($this->inner);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function isGuarded(string $url, array $options): bool
    {
        // Not stored yet after an update from a version without the switch: guarded.
        $switch = $this->systemConfig->get(self::SWITCH);
        if ($switch !== null && filter_var($switch, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE) === false) {
            return false;
        }

        return !$this->usesProxy($url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function usesProxy(string $url, array $options): bool
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $server = $this->server ?? $_SERVER;

        $proxy = $options['proxy'] ?? null;
        if (!\is_string($proxy) || $proxy === '') {
            $proxy = $this->configuredProxy ?? $this->environmentProxy($url, $server);
        }
        if ($proxy === null || $proxy === '') {
            return false;
        }

        $noProxy = $options['no_proxy'] ?? $this->configuredNoProxy ?? $server['no_proxy'] ?? $server['NO_PROXY'] ?? '';

        return !$this->excludedFromProxy($host, \is_string($noProxy) ? $noProxy : '');
    }

    /**
     * The variables Symfony's HttpClient reads (like curl); HTTP_PROXY is ignored outside the CLI.
     *
     * @param array<string, mixed> $server
     */
    private function environmentProxy(string $url, array $server): ?string
    {
        $proxy = $server['http_proxy'] ?? $server['all_proxy'] ?? $server['ALL_PROXY'] ?? null;
        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $proxy = $server['http_proxy'] ?? $_ENV['HTTP_PROXY'] ?? (getenv('HTTP_PROXY', true) ?: null) ?? $server['all_proxy'] ?? $server['ALL_PROXY'] ?? null;
        }
        if (str_starts_with(strtolower($url), 'https:')) {
            $proxy = $server['https_proxy'] ?? $server['HTTPS_PROXY'] ?? $proxy;
        }

        return \is_string($proxy) ? $proxy : null;
    }

    /**
     * no_proxy entries match the host itself and its subdomains ("example.com", ".example.com",
     * "*.example.com"), "*" matches everything, like curl does.
     */
    private function excludedFromProxy(string $host, string $noProxy): bool
    {
        foreach (preg_split('/[\s,]+/', strtolower($noProxy), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if ($entry === '*') {
                return true;
            }
            $entry = ltrim($entry, '*.');
            if ($entry !== '' && ($host === $entry || str_ends_with($host, '.' . $entry))) {
                return true;
            }
        }

        return false;
    }
}
