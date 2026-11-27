<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\DataResolver\BackgroundHeroElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\MediaSplitElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\SpotlightElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoGridElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoOptimizerElementResolver;
use ScaleCommerce\VideoOptimizer\Service\EmbedAvailability;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use ScaleCommerce\VideoOptimizer\Subscriber\DegradedEmbedCacheSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Same service IDs and arguments as the former services.xml (XML configuration is deprecated for Shopware 6.8).
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(VideoOptimizerClient::class)
        ->args([
            service(HttpClientInterface::class),
            service(SystemConfigService::class),
            service('cache.object'),
            service('logger'),
            service(EmbedAvailability::class),
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(EmbedAvailability::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(DegradedEmbedCacheSubscriber::class)
        ->args([
            service(EmbedAvailability::class),
            service(CacheInvalidator::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(VideoOptimizerAdminController::class)
        ->public()
        ->args([service(VideoOptimizerClient::class)]);

    $services->set(VideoOptimizerElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(MediaSplitElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service(HtmlSanitizer::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(BackgroundHeroElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(SpotlightElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(VideoGridElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');
};
