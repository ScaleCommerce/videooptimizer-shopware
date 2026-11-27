<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands the proxy options of the shop's HTTP client (`framework.http_client.default_options.proxy`
 * and `.no_proxy`) to GuardedHttpClient, which cannot read them from the client itself. The raw
 * values are passed on (the last configuration wins); environment placeholders are resolved at runtime.
 */
class HttpProxyConfiguredPass implements CompilerPassInterface
{
    public const PROXY = 'scalecommerce_vo.http_proxy';

    public const NO_PROXY = 'scalecommerce_vo.http_no_proxy';

    public function process(ContainerBuilder $container): void
    {
        foreach ([self::PROXY => 'proxy', self::NO_PROXY => 'no_proxy'] as $parameter => $option) {
            $value = null;
            foreach ($container->getExtensionConfig('framework') as $config) {
                $configured = $config['http_client']['default_options'][$option] ?? null;
                if (\is_string($configured)) {
                    $value = $configured;
                }
            }
            $container->setParameter($parameter, $value);
        }
    }
}
