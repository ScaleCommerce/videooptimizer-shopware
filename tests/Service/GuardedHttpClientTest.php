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

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $seen = [];

    /**
     * @param array<string, mixed> $server
     */
    private function client(mixed $blockPrivateNetworks = null, array $server = [], ?string $configuredProxy = null, ?string $configuredNoProxy = null, ?callable $respond = null): GuardedHttpClient
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $key): mixed => $key === 'ScaleVideoOptimizer.config.blockPrivateNetworks' ? $blockPrivateNetworks : null);
        $this->requests = 0;
        $this->seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($respond): MockResponse {
            ++$this->requests;
            $this->seen[] = [$method, $url, $options];

            return $respond !== null ? $respond($method, $url, $options, $this->requests) : new MockResponse('{}');
        });

        return new GuardedHttpClient($http, $config, $configuredProxy, $configuredNoProxy, $server);
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

    /**
     * @return iterable<string, array{?string, ?string, array<string, string>, bool}>
     */
    public static function configuredProxies(): iterable
    {
        yield 'configured proxy' => ['http://10.1.1.1:3128', null, [], false];
        yield 'configured proxy, host in configured no_proxy' => ['http://10.1.1.1:3128', 'api.videooptimizer.eu', [], true];
        yield 'configured proxy, configured no_proxy wins over the environment' => ['http://10.1.1.1:3128', 'example.com', ['no_proxy' => 'api.videooptimizer.eu'], false];
        yield 'configured empty proxy keeps the guard on' => ['', null, [], true];
        yield 'configured empty proxy ignores the environment' => ['', null, ['https_proxy' => 'http://10.1.1.1:3128'], true];
        yield 'not configured: environment applies' => [null, null, ['https_proxy' => 'http://10.1.1.1:3128'], false];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('configuredProxies')]
    public function testFrameworkDefaultOptionsDecide(?string $proxy, ?string $noProxy, array $server, bool $guarded): void
    {
        static::assertSame(!$guarded, $this->reaches($this->client(true, $server, $proxy, $noProxy)));
    }

    /**
     * The inner client decides about the proxy again for every redirect hop (curl: no_proxy, scheme), so a
     * redirect must not leave the guard behind (#33).
     */
    public function testRedirectToAHostOutsideTheProxyIsGuarded(): void
    {
        $client = $this->client(true, ['https_proxy' => 'http://10.1.1.1:3128', 'no_proxy' => 'metadata.internal'], null, null, static fn (string $m, string $url): MockResponse => str_contains($url, 'api.videooptimizer.eu')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://metadata.internal/secret']])
            : new MockResponse('SECRET'));

        try {
            $client->request('GET', self::URL, ['max_redirects' => 3, 'resolve' => ['api.videooptimizer.eu' => '10.1.1.1', 'metadata.internal' => '169.254.169.254']])->getContent();
            static::fail('The redirect target must be refused.');
        } catch (TransportExceptionInterface) {
        }
        static::assertSame(1, $this->requests);
    }

    public function testPlainHttpRedirectIsGuardedWhenOnlyAHttpsProxyIsSet(): void
    {
        $client = $this->client(true, ['HTTPS_PROXY' => 'http://10.1.1.1:3128'], null, null, static fn (string $m, string $url): MockResponse => str_contains($url, 'api.videooptimizer.eu')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://cdn.example.com/t.jpg']])
            : new MockResponse('SECRET'));

        try {
            $client->request('GET', self::URL, ['max_redirects' => 3, 'resolve' => ['cdn.example.com' => '127.0.0.1']])->getContent();
            static::fail('The redirect target must be refused.');
        } catch (TransportExceptionInterface) {
        }
        static::assertSame(1, $this->requests);
    }

    public function testRedirectsStayUnguardedBehindTheProxy(): void
    {
        $client = $this->client(true, ['https_proxy' => 'http://10.1.1.1:3128'], null, null, static fn (string $m, string $url): MockResponse => str_contains($url, 'api.videooptimizer.eu')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://cdn.example.com/t.jpg']])
            : new MockResponse('IMG'));

        static::assertSame('IMG', $client->request('GET', self::URL, ['max_redirects' => 3])->getContent());
        static::assertSame(2, $this->requests);
    }

    public function testRedirectHopsAreSentWithoutAutomaticFollowing(): void
    {
        $client = $this->client(false, [], null, null, static fn (string $m, string $url): MockResponse => str_contains($url, '/api/v1/')
            ? new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/other']])
            : new MockResponse('OK'));

        $client->request('GET', self::URL, ['max_redirects' => 3])->getContent();

        static::assertSame(['https://api.videooptimizer.eu/api/v1/libraries', 'https://api.videooptimizer.eu/other'], array_column($this->seen, 1));
        static::assertSame([0, 0], array_map(static fn (array $call): mixed => $call[2]['max_redirects'], $this->seen));
    }

    public function testAtMostMaxRedirectsAreFollowed(): void
    {
        $client = $this->client(false, [], null, null, static fn (string $m, string $url, array $o, int $n): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://api.videooptimizer.eu/hop' . $n]]));

        $response = $client->request('GET', self::URL, ['max_redirects' => 3]);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame(4, $this->requests);
    }

    public function testNoRedirectsAreFollowedWithoutPermission(): void
    {
        $client = $this->client(false, [], null, null, static fn (): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://cdn.example.com/x']]));

        static::assertSame(302, $client->request('GET', self::URL, ['max_redirects' => 0])->getStatusCode());
        static::assertSame(1, $this->requests);
    }

    public function testAuthorizationIsDroppedOnACrossOriginHop(): void
    {
        $client = $this->client(false, [], null, null, static fn (string $m, string $url): MockResponse => str_contains($url, 'api.videooptimizer.eu/api')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://cdn.example.com/t.jpg']])
            : new MockResponse('IMG'));

        $client->request('GET', self::URL, ['max_redirects' => 3, 'headers' => ['Authorization: Bearer t', 'Accept: image/*']])->getContent();

        $names = static fn (array $call): array => array_map(static fn (string $h): string => strtolower(explode(':', $h)[0]), $call[2]['headers'] ?? []);
        static::assertContains('authorization', $names($this->seen[0]));
        static::assertNotContains('authorization', $names($this->seen[1]));
        static::assertContains('accept', $names($this->seen[1]));
    }

    public function testAuthorizationIsKeptOnASameOriginHop(): void
    {
        $client = $this->client(false, [], null, null, static fn (string $m, string $url): MockResponse => str_ends_with($url, '/libraries')
            ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/api/v1/other']])
            : new MockResponse('OK'));

        $client->request('GET', self::URL, ['max_redirects' => 3, 'headers' => ['Authorization: Bearer t']])->getContent();

        static::assertContains('Authorization: Bearer t', $this->seen[1][2]['headers']);
    }

    public function testRedirectsOfOtherMethodsAreNotFollowed(): void
    {
        $client = $this->client(false, [], null, null, static fn (): MockResponse => new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => 'https://cdn.example.com/x']]));

        static::assertSame(307, $client->request('POST', self::URL, ['max_redirects' => 3])->getStatusCode());
        static::assertSame(1, $this->requests);
    }

    public function testStreamsUnguardedResponses(): void
    {
        $client = $this->client(false);
        $response = $client->request('GET', self::URL);

        $content = '';
        foreach ($client->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }

        static::assertSame('{}', $content);
    }

    public function testStreamsGuardedResponses(): void
    {
        $client = $this->client(true);
        $response = $client->request('GET', self::URL, ['resolve' => ['api.videooptimizer.eu' => '93.184.216.34']]);

        $content = '';
        foreach ($client->stream([$response]) as $chunk) {
            $content .= $chunk->getContent();
        }

        static::assertSame('{}', $content);
    }

    public function testWithOptionsKeepsTheGuard(): void
    {
        $client = $this->client()->withOptions(['timeout' => 5]);

        static::assertFalse($this->reaches($client));
    }
}
