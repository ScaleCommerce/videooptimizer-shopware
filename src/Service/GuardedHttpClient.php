<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
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
     * @param bool $proxyConfigured a proxy is set in the framework's http_client default options
     * @param array<string, mixed>|null $server the environment proxy variables are read from, `$_SERVER` like Symfony's HttpClient does
     */
    public function __construct(
        private HttpClientInterface $inner,
        private readonly SystemConfigService $systemConfig,
        private readonly bool $proxyConfigured = false,
        private readonly ?array $server = null,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->clientFor($url, $options)->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        // The guard's responses are async wrappers that only its own stream() understands; it passes
        // plain responses through to the decorated client.
        return $this->guarded()->stream($responses, $timeout);
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
            $proxy = $this->proxyConfigured ? 'configured' : $this->environmentProxy($url, $server);
        }
        if ($proxy === null || $proxy === '') {
            return false;
        }

        $noProxy = $options['no_proxy'] ?? $server['no_proxy'] ?? $server['NO_PROXY'] ?? '';

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
