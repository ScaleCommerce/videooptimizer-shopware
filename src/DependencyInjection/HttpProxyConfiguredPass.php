<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets the parameter that tells GuardedHttpClient the shop's HTTP client is configured with a proxy
 * (`framework.http_client.default_options.proxy`), which cannot be read from the client itself.
 */
class HttpProxyConfiguredPass implements CompilerPassInterface
{
    public const PARAMETER = 'scalecommerce_vo.http_proxy_configured';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getExtensionConfig('framework') as $config) {
            $proxy = $config['http_client']['default_options']['proxy'] ?? null;
            if (\is_string($proxy) && $proxy !== '') {
                $container->setParameter(self::PARAMETER, true);

                return;
            }
        }
    }
}
