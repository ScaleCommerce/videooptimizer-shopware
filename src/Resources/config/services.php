<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\Controller\WebhookController;
use ScaleCommerce\VideoOptimizer\DataResolver\BackgroundHeroElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\MediaSplitElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\SpotlightElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoGridElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoOptimizerElementResolver;
use ScaleCommerce\VideoOptimizer\DependencyInjection\HttpProxyConfiguredPass;
use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use ScaleCommerce\VideoOptimizer\Service\GuardedHttpClient;
use ScaleCommerce\VideoOptimizer\Service\PluginUserAgent;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use ScaleCommerce\VideoOptimizer\Subscriber\EmbedPageCacheSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Same service IDs and arguments as the former services.xml (XML configuration is deprecated for Shopware 6.8).
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The proxy options of the shop's HTTP client, set by HttpProxyConfiguredPass (null: not configured).
    $container->parameters()
        ->set(HttpProxyConfiguredPass::PROXY, null)
        ->set(HttpProxyConfiguredPass::NO_PROXY, null);

    // The shared HTTP client behind a guard that checks the resolved IP of every connection, redirect
    // targets included, so the configurable base URLs cannot reach internal services (issue #33).
    $services->set('scalecommerce_vo.http_client', GuardedHttpClient::class)
        ->args([
            service(HttpClientInterface::class),
            service(SystemConfigService::class),
            '%' . HttpProxyConfiguredPass::PROXY . '%',
            '%' . HttpProxyConfiguredPass::NO_PROXY . '%',
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(VideoOptimizerClient::class)
        ->args([
            service('scalecommerce_vo.http_client'),
            service(SystemConfigService::class),
            service('cache.object'),
            service('logger'),
            service(EmbedRenderState::class),
            service(CacheInvalidator::class),
            service(PluginUserAgent::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // User-Agent for all API requests: plugin, Shopware and PHP version plus the shop URL.
    $services->set(PluginUserAgent::class)
        ->args(['%kernel.shopware_version%', '%env(default::APP_URL)%']);

    $services->set(EmbedRenderState::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(EmbedPageCacheSubscriber::class)
        ->args([
            service(EmbedRenderState::class),
            service(CacheInvalidator::class),
            service('event_dispatcher'),
            service(VideoOptimizerClient::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(VideoOptimizerAdminController::class)
        ->public()
        ->args([service(VideoOptimizerClient::class)]);

    $services->set(WebhookController::class)
        ->public()
        ->args([
            service(VideoOptimizerClient::class),
            service(SystemConfigService::class),
            service('cache.object'),
            service('router'),
            service('logger'),
        ]);

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

    // Former service IDs (renamed in 1.2.0), kept as aliases for decorators and references.
    $services->alias('ScaleCommerce\\VideoOptimizer\\Service\\EmbedAvailability', EmbedRenderState::class);
    $services->alias('ScaleCommerce\\VideoOptimizer\\Subscriber\\DegradedEmbedCacheSubscriber', EmbedPageCacheSubscriber::class);
};
