<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

use ScaleCommerce\VideoOptimizer\Service\EmbedAvailability;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Caps the HTTP cache lifetime of a response that was rendered while a video embed lookup failed
 * or returned no playable sources, so the hidden video comes back once the API recovers or the
 * video is ready, instead of after the full page TTL. Matches the 60 s negative cache of
 * VideoOptimizerClient::getEmbed().
 *
 * Runs after Shopware's CacheResponseSubscriber::setResponseCache (-1500), which sets the cache
 * headers, and only shortens lifetimes that are already set; uncacheable responses stay as they are.
 */
class DegradedEmbedCacheSubscriber implements EventSubscriberInterface
{
    public const MAX_TTL = 60;

    public function __construct(private readonly EmbedAvailability $availability)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -1600]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->availability->isDegraded()) {
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
}
