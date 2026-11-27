<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tags every page with the videos it shows ("scalecommerce-vo-video-<uuid>"), so admin changes to
 * a video invalidate exactly those pages (VideoOptimizerClient::forgetEmbed()); in the HTTP cache
 * via AddCacheTagEvent, on Shopware 6.6 also in the Store API route cache.
 *
 * Keeps a page that was rendered while a video embed lookup failed or returned no playable sources
 * (the element is hidden then) from being cached for long, so the video comes back once the API
 * recovers or the video is ready. Matches the 60 s negative cache of VideoOptimizerClient::getEmbed().
 *
 * - HTTP cache / reverse proxy: caps s-maxage/max-age at 60 s. Runs after Shopware's
 *   CacheResponseSubscriber::setResponseCache (-1500) and only shortens lifetimes that are set.
 * - Store API route cache (Shopware 6.6: cached category, landing page and product detail routes
 *   keep the resolved CMS page without TTL): tags the entry and invalidates that tag once the
 *   response is sent. These events are not dispatched on 6.7 and are removed in 6.8, so they are
 *   referenced by name only.
 */
class EmbedPageCacheSubscriber implements EventSubscriberInterface
{
    public const MAX_TTL = 60;

    public const ROUTE_CACHE_TAG = 'scalecommerce-vo-degraded-embed';

    public function __construct(
        private readonly EmbedRenderState $availability,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -1600],
            KernelEvents::TERMINATE => 'onTerminate',
            'Shopware\\Core\\Content\\Category\\Event\\CategoryRouteCacheTagsEvent' => 'onRouteCacheTags',
            'Shopware\\Core\\Content\\LandingPage\\Event\\LandingPageRouteCacheTagsEvent' => 'onRouteCacheTags',
            'Shopware\\Core\\Content\\Product\\Events\\ProductDetailRouteCacheTagsEvent' => 'onRouteCacheTags',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // The HTTP cache reads the collected tags when it stores the response, after this listener.
        // AddCacheTagEvent exists from a later 6.6 patch release on; before that, the HTTP cache
        // takes the tags of the Store API route cache (see onRouteCacheTags()).
        $tags = $this->availability->cacheTags();
        if ($tags !== [] && class_exists(AddCacheTagEvent::class)) {
            $this->dispatcher->dispatch(new AddCacheTagEvent(...$tags));
        }

        if (!$this->availability->isDegraded()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (['s-maxage', 'max-age'] as $directive) {
            $value = $headers->getCacheControlDirective($directive);
            if (is_numeric($value) && (int) $value > self::MAX_TTL) {
                $headers->addCacheControlDirective($directive, (string) self::MAX_TTL);
            }
        }
    }

    /**
     * @param object $event a StoreApiRouteCacheTagsEvent (Shopware 6.6), untyped on purpose
     */
    public function onRouteCacheTags(object $event): void
    {
        if (!method_exists($event, 'addTags')) {
            return;
        }

        $tags = $this->availability->cacheTags();
        if ($tags !== []) {
            $event->addTags($tags);
        }

        if ($this->availability->isDegraded()) {
            $event->addTags([self::ROUTE_CACHE_TAG]);
            $this->availability->markRouteCacheTagged();
        }
    }

    public function onTerminate(): void
    {
        if (!$this->availability->isRouteCacheTagged()) {
            return;
        }

        $this->cacheInvalidator->invalidate([self::ROUTE_CACHE_TAG], true);
        $this->availability->reset();
    }
}

// Former name (until 1.2.0): an alias, so instances satisfy type hints and instanceof checks on it.
class_alias(EmbedPageCacheSubscriber::class, 'ScaleCommerce\\VideoOptimizer\\Subscriber\\DegradedEmbedCacheSubscriber');
