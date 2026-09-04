<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer;

use Shopware\Core\Framework\Plugin;

class ScaleVideoOptimizer extends Plugin
{
    // No uninstall() override needed: system config (including the API token) is
    // removed by Shopware core on uninstall unless "keep user data" is chosen.
}
