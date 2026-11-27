<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\GuardedHttpClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GuardedHttpClientTest extends TestCase
{
    private const URL = 'https://api.videooptimizer.eu/api/v1/libraries';

    private int $requests = 0;

    /**
     * @param array<string, mixed> $server
     */
    private function client(mixed $blockPrivateNetworks = null, array $server = [], bool $proxyConfigured = false): GuardedHttpClient
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $key): mixed => $key === 'ScaleVideoOptimizer.config.blockPrivateNetworks' ? $blockPrivateNetworks : null);
        $this->requests = 0;
        $http = new MockHttpClient(function (): MockResponse {
            ++$this->requests;

            return new MockResponse('{}');
        });

        return new GuardedHttpClient($http, $config, $proxyConfigured, $server);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function reaches(GuardedHttpClient $client, string $url = self::URL, array $options = []): bool
    {
        // "resolve" pins the name to a private address, which stands in for DNS.
        $options['resolve'] ??= [(string) parse_url($url, PHP_URL_HOST) => '10.0.0.5'];
        try {
            $client->request('GET', $url, $options)->getContent();
        } catch (TransportExceptionInterface) {
            return false;
        }

        return true;
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function guardedByDefault(): iterable
    {
        yield 'switch not stored yet (update from 1.2.1)' => [null];
        yield 'on' => [true];
        yield 'on as string' => ['1'];
    }

    #[DataProvider('guardedByDefault')]
    public function testBlocksPrivateAddressesByDefault(mixed $switch): void
    {
        static::assertFalse($this->reaches($this->client($switch)));
        static::assertSame(0, $this->requests);
    }

    public function testSwitchOffAllowsPrivateAddresses(): void
    {
        static::assertTrue($this->reaches($this->client(false)));
        static::assertTrue($this->reaches($this->client('0')));
    }

    public function testPublicAddressesPass(): void
    {
        static::assertTrue($this->reaches($this->client(), self::URL, ['resolve' => ['api.videooptimizer.eu' => '93.184.216.34']]));
    }

    /**
     * @return iterable<string, array{array<string, string>, bool}>
     */
    public static function proxySettings(): iterable
    {
        yield 'https_proxy' => [['https_proxy' => 'http://10.1.1.1:3128'], false];
        yield 'HTTPS_PROXY' => [['HTTPS_PROXY' => 'http://10.1.1.1:3128'], false];
        yield 'http_proxy' => [['http_proxy' => 'http://10.1.1.1:3128'], false];
        yield 'ALL_PROXY' => [['ALL_PROXY' => 'http://10.1.1.1:3128'], false];
        yield 'all_proxy' => [['all_proxy' => 'http://10.1.1.1:3128'], false];
        yield 'empty proxy variable' => [['https_proxy' => ''], true];
        yield 'no proxy' => [[], true];
        yield 'no_proxy lists the host' => [['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => 'api.videooptimizer.eu'], true];
        yield 'NO_PROXY lists the parent domain' => [['HTTPS_PROXY' => 'http://10.1.1.1:3128', 'NO_PROXY' => 'localhost, .videooptimizer.eu'], true];
        yield 'no_proxy wildcard' => [['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => '*'], true];
        yield 'no_proxy lists another host' => [['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => 'example.com'], false];
        yield 'no_proxy lists a name that only ends alike' => [['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => 'eu'], true];
        yield 'no_proxy lists a longer name' => [['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => 'xapi.videooptimizer.eu'], false];
    }

    /**
     * Behind an outbound proxy the connection goes to the proxy (often a private address), so the proxy
     * has to filter; hosts in no_proxy are connected to directly and stay guarded.
     *
     * @param array<string, string> $server
     */
    #[DataProvider('proxySettings')]
    public function testProxySetupsAreExemptUnlessNoProxyApplies(array $server, bool $guarded): void
    {
        static::assertSame(!$guarded, $this->reaches($this->client(true, $server)));
    }

    public function testExplicitProxyOptionIsExempt(): void
    {
        static::assertTrue($this->reaches($this->client(), self::URL, ['proxy' => 'http://10.1.1.1:3128']));
    }

    public function testConfiguredDefaultProxyIsExempt(): void
    {
        static::assertTrue($this->reaches($this->client(true, [], true)));
    }

    public function testWithOptionsKeepsTheGuard(): void
    {
        $client = $this->client()->withOptions(['timeout' => 5]);

        static::assertFalse($this->reaches($client));
    }
}
