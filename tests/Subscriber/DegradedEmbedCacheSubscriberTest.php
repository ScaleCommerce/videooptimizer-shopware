<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\EmbedAvailability;
use ScaleCommerce\VideoOptimizer\Subscriber\DegradedEmbedCacheSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class DegradedEmbedCacheSubscriberTest extends TestCase
{
    public function testRunsAfterShopwaresCacheHeaders(): void
    {
        $events = DegradedEmbedCacheSubscriber::getSubscribedEvents();

        // Shopware's CacheResponseSubscriber::setResponseCache runs at -1500.
        static::assertLessThan(-1500, $events[KernelEvents::RESPONSE][1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cacheControls(): iterable
    {
        yield 'shared max age' => ['public, s-maxage=7200', 's-maxage=60'];
        yield 'max age only' => ['public, max-age=7200', 'max-age=60'];
        yield 'already shorter' => ['public, s-maxage=30', 's-maxage=30'];
    }

    #[DataProvider('cacheControls')]
    public function testCapsCacheLifetimeWhenAnEmbedWasDegraded(string $cacheControl, string $expected): void
    {
        $availability = new EmbedAvailability();
        $availability->markDegraded();
        $response = new Response('', 200, ['Cache-Control' => $cacheControl]);

        ($this->subscriber($availability))->onResponse($this->event($response));

        static::assertStringContainsString($expected, (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesResponseAloneWithoutDegradedEmbed(): void
    {
        $response = new Response('', 200, ['Cache-Control' => 'public, s-maxage=7200']);

        ($this->subscriber(new EmbedAvailability()))->onResponse($this->event($response));

        static::assertStringContainsString('s-maxage=7200', (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesUncacheableResponseAlone(): void
    {
        $availability = new EmbedAvailability();
        $availability->markDegraded();
        $response = new Response('', 200, ['Cache-Control' => 'no-cache, private']);

        ($this->subscriber($availability))->onResponse($this->event($response));

        static::assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
        static::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function testResetClearsTheState(): void
    {
        $availability = new EmbedAvailability();
        $availability->markDegraded();
        $availability->reset();

        static::assertFalse($availability->isDegraded());
    }

    /**
     * Shopware 6.6 keeps the resolved CMS page in the Store API route cache without TTL; a degraded
     * render must be tagged and that tag invalidated once the response is sent.
     */
    public function testTagsAndInvalidatesTheRouteCacheWhenDegraded(): void
    {
        $availability = new EmbedAvailability();
        $availability->markDegraded();
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::once())->method('invalidate')->with([DegradedEmbedCacheSubscriber::ROUTE_CACHE_TAG], true);
        $tagsEvent = new class {
            /** @var list<string> */
            public array $tags = [];

            /** @param list<string> $tags */
            public function addTags(array $tags): void
            {
                $this->tags = [...$this->tags, ...$tags];
            }
        };

        $subscriber = new DegradedEmbedCacheSubscriber($availability, $invalidator, new EventDispatcher());
        $subscriber->onRouteCacheTags($tagsEvent);
        $subscriber->onTerminate();

        static::assertSame([DegradedEmbedCacheSubscriber::ROUTE_CACHE_TAG], $tagsEvent->tags);
        static::assertFalse($availability->isRouteCacheTagged());
    }

    public function testLeavesTheRouteCacheAloneWithoutDegradedEmbed(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::never())->method('invalidate');
        $tagsEvent = new class {
            /** @var list<string> */
            public array $tags = [];

            /** @param list<string> $tags */
            public function addTags(array $tags): void
            {
                $this->tags = [...$this->tags, ...$tags];
            }
        };

        $subscriber = new DegradedEmbedCacheSubscriber(new EmbedAvailability(), $invalidator, new EventDispatcher());
        $subscriber->onRouteCacheTags($tagsEvent);
        $subscriber->onTerminate();

        static::assertSame([], $tagsEvent->tags);
    }

    /**
     * Pages are tagged per video so admin changes can invalidate them (HTTP cache via
     * AddCacheTagEvent; Shopware 6.6 route cache via the route cache tags events).
     */
    public function testTagsTheResponseWithTheRenderedVideos(): void
    {
        $availability = new EmbedAvailability();
        $availability->markRendered('v1');
        $availability->markRendered('v2');
        $availability->markRendered('v1');
        $dispatcher = new EventDispatcher();
        $collected = [];
        $dispatcher->addListener(AddCacheTagEvent::class, function (AddCacheTagEvent $event) use (&$collected): void {
            $collected = [...$collected, ...$event->tags];
        });

        $subscriber = new DegradedEmbedCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), $dispatcher);
        $subscriber->onResponse($this->event(new Response('', 200, ['Cache-Control' => 'public, s-maxage=7200'])));

        static::assertSame(['scalecommerce-vo-video-v1', 'scalecommerce-vo-video-v2'], $collected);
    }

    public function testAddsVideoTagsToTheRouteCache(): void
    {
        $availability = new EmbedAvailability();
        $availability->markRendered('v1');
        $tagsEvent = new class {
            /** @var list<string> */
            public array $tags = [];

            /** @param list<string> $tags */
            public function addTags(array $tags): void
            {
                $this->tags = [...$this->tags, ...$tags];
            }
        };

        (new DegradedEmbedCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), new EventDispatcher()))->onRouteCacheTags($tagsEvent);

        static::assertSame(['scalecommerce-vo-video-v1'], $tagsEvent->tags);
    }

    private function subscriber(EmbedAvailability $availability): DegradedEmbedCacheSubscriber
    {
        return new DegradedEmbedCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), new EventDispatcher());
    }

    private function event(Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
