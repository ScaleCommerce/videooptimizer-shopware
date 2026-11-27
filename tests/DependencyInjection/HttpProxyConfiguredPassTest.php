<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DependencyInjection\HttpProxyConfiguredPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class HttpProxyConfiguredPassTest extends TestCase
{
    /**
     * @return iterable<string, array{list<array<string, mixed>>, ?string, ?string}>
     */
    public static function frameworkConfigs(): iterable
    {
        $options = static fn (array $defaults): array => ['http_client' => ['default_options' => $defaults]];

        yield 'proxy in default options' => [[$options(['proxy' => 'http://proxy:3128'])], 'http://proxy:3128', null];
        yield 'proxy and no_proxy' => [[$options(['proxy' => 'http://proxy:3128', 'no_proxy' => '.internal'])], 'http://proxy:3128', '.internal'];
        yield 'env placeholder is passed on as it is' => [[$options(['proxy' => '%env(HTTPS_PROXY)%'])], '%env(HTTPS_PROXY)%', null];
        yield 'last config wins' => [[$options(['proxy' => 'http://one:1']), ['secret' => 'x'], $options(['proxy' => 'http://two:2'])], 'http://two:2', null];
        yield 'a later empty proxy wins' => [[$options(['proxy' => 'http://one:1']), $options(['proxy' => ''])], '', null];
        yield 'empty proxy' => [[$options(['proxy' => ''])], '', null];
        yield 'no proxy option' => [[$options(['timeout' => 5])], null, null];
        yield 'no http client config' => [[['secret' => 'x']], null, null];
        yield 'nothing' => [[], null, null];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    #[DataProvider('frameworkConfigs')]
    public function testCarriesTheProxyOptionsOfTheFrameworkConfig(array $configs, ?string $proxy, ?string $noProxy): void
    {
        $container = new ContainerBuilder();
        // The real extension validates its config when loading; only the raw config matters here.
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        });
        $container->setParameter(HttpProxyConfiguredPass::PROXY, null);
        $container->setParameter(HttpProxyConfiguredPass::NO_PROXY, null);
        foreach ($configs as $config) {
            $container->loadFromExtension('framework', $config);
        }

        (new HttpProxyConfiguredPass())->process($container);

        static::assertSame($proxy, $container->getParameter(HttpProxyConfiguredPass::PROXY));
        static::assertSame($noProxy, $container->getParameter(HttpProxyConfiguredPass::NO_PROXY));
    }
}
