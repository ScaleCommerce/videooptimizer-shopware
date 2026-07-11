<?php declare(strict_types=1);

$loader = require __DIR__ . '/../../../../vendor/autoload.php';
$loader->addPsr4('ScaleCommerce\\VideoOptimizer\\', __DIR__ . '/../src/');
$loader->addPsr4('ScaleCommerce\\VideoOptimizer\\Tests\\', __DIR__ . '/');
