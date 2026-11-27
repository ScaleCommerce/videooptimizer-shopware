<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer;

use ScaleCommerce\VideoOptimizer\DependencyInjection\HttpProxyConfiguredPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ScaleVideoOptimizer extends Plugin
{
    // No uninstall() override needed: system config (including the API token) is
    // removed by Shopware core on uninstall unless "keep user data" is chosen.

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new HttpProxyConfiguredPass());
    }
}
