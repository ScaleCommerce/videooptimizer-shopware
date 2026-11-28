<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use ScaleCommerce\VideoOptimizer\Controller\VideoOptimizerAdminController;
use ScaleCommerce\VideoOptimizer\Controller\WebhookController;
use ScaleCommerce\VideoOptimizer\DataResolver\BackgroundHeroElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\MediaSplitElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\SpotlightElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoChaptersElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoDetailElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoGridElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoListElementResolver;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoOptimizerElementResolver;
use ScaleCommerce\VideoOptimizer\DependencyInjection\HttpProxyConfiguredPass;
use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use ScaleCommerce\VideoOptimizer\Service\GuardedHttpClient;
use ScaleCommerce\VideoOptimizer\Service\PluginUserAgent;
use ScaleCommerce\VideoOptimizer\Service\ProductVideoSlide;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use ScaleCommerce\VideoOptimizer\Subscriber\EmbedPageCacheSubscriber;
use ScaleCommerce\VideoOptimizer\Subscriber\ProductVideoSubscriber;
use ScaleCommerce\VideoOptimizer\Subscriber\VideoDetailMetaSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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

    $services->set(ProductVideoSlide::class)
        ->args([service(VideoOptimizerClient::class), service(SystemConfigService::class), service('logger')]);

    $services->set(ProductVideoSubscriber::class)
        ->args([service(ProductVideoSlide::class)])
        ->tag('kernel.event_subscriber');

    $services->set(VideoDetailMetaSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(VideoOptimizerAdminController::class)
        ->public()
        ->args([service(VideoOptimizerClient::class)]);

    // Claims of processed webhook deliveries. Not cache.object: that pool is request-scoped (array)
    // in some setups, e.g. the dev environment, where no delivery would ever be recognised again.
    // Cleared with the cache; a repeated delivery only repeats an idempotent invalidation.
    $services->set('scalecommerce_vo.webhook_claims', FilesystemAdapter::class)
        ->args(['scalecommerce_vo_webhook', 86400, '%kernel.cache_dir%/scalecommerce_vo']);

    $services->set(WebhookController::class)
        ->public()
        ->args([
            service(VideoOptimizerClient::class),
            service(SystemConfigService::class),
            service('scalecommerce_vo.webhook_claims'),
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

    $services->set(VideoChaptersElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(VideoListElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    $services->set(VideoDetailElementResolver::class)
        ->args([service(VideoOptimizerClient::class), service('logger')])
        ->tag('shopware.cms.data_resolver');

    // Former service IDs (renamed in 1.2.0), kept as aliases for decorators and references.
    $services->alias('ScaleCommerce\\VideoOptimizer\\Service\\EmbedAvailability', EmbedRenderState::class);
    $services->alias('ScaleCommerce\\VideoOptimizer\\Subscriber\\DegradedEmbedCacheSubscriber', EmbedPageCacheSubscriber::class);
};
