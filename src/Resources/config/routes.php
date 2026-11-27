<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Attribute routes of the admin controller (XML configuration is deprecated for Shopware 6.8).
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Controller/', 'attribute');
};
