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
     * @return iterable<string, array{list<array<string, mixed>>, bool}>
     */
    public static function frameworkConfigs(): iterable
    {
        yield 'proxy in default options' => [[['http_client' => ['default_options' => ['proxy' => 'http://proxy:3128']]]], true];
        yield 'proxy in a later config' => [[['secret' => 'x'], ['http_client' => ['default_options' => ['proxy' => 'http://proxy:3128']]]], true];
        yield 'empty proxy' => [[['http_client' => ['default_options' => ['proxy' => '']]]], false];
        yield 'no proxy' => [[['http_client' => ['default_options' => ['timeout' => 5]]]], false];
        yield 'no http client config' => [[['secret' => 'x']], false];
        yield 'nothing' => [[], false];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    #[DataProvider('frameworkConfigs')]
    public function testDetectsAProxyInTheFrameworkConfig(array $configs, bool $expected): void
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
        $container->setParameter(HttpProxyConfiguredPass::PARAMETER, false);
        foreach ($configs as $config) {
            $container->loadFromExtension('framework', $config);
        }

        (new HttpProxyConfiguredPass())->process($container);

        static::assertSame($expected, $container->getParameter(HttpProxyConfiguredPass::PARAMETER));
    }
}
