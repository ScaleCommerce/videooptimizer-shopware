<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\EmbedRenderState;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use ScaleCommerce\VideoOptimizer\Subscriber\EmbedPageCacheSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class EmbedPageCacheSubscriberTest extends TestCase
{
    public function testRunsAfterShopwaresCacheHeaders(): void
    {
        $events = EmbedPageCacheSubscriber::getSubscribedEvents();

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
        $availability = new EmbedRenderState();
        $availability->markDegraded();
        $response = new Response('', 200, ['Cache-Control' => $cacheControl]);

        ($this->subscriber($availability))->onResponse($this->event($response));

        static::assertStringContainsString($expected, (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesResponseAloneWithoutDegradedEmbed(): void
    {
        $response = new Response('', 200, ['Cache-Control' => 'public, s-maxage=7200']);

        ($this->subscriber(new EmbedRenderState()))->onResponse($this->event($response));

        static::assertStringContainsString('s-maxage=7200', (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesUncacheableResponseAlone(): void
    {
        $availability = new EmbedRenderState();
        $availability->markDegraded();
        $response = new Response('', 200, ['Cache-Control' => 'no-cache, private']);

        ($this->subscriber($availability))->onResponse($this->event($response));

        static::assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
        static::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function testResetClearsTheState(): void
    {
        $availability = new EmbedRenderState();
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
        $availability = new EmbedRenderState();
        $availability->markDegraded();
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects(static::once())->method('invalidate')->with([EmbedPageCacheSubscriber::ROUTE_CACHE_TAG], true);
        $tagsEvent = new class {
            /** @var list<string> */
            public array $tags = [];

            /** @param list<string> $tags */
            public function addTags(array $tags): void
            {
                $this->tags = [...$this->tags, ...$tags];
            }
        };

        $subscriber = new EmbedPageCacheSubscriber($availability, $invalidator, new EventDispatcher());
        $subscriber->onRouteCacheTags($tagsEvent);
        $subscriber->onTerminate();

        static::assertSame([EmbedPageCacheSubscriber::ROUTE_CACHE_TAG], $tagsEvent->tags);
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

        $subscriber = new EmbedPageCacheSubscriber(new EmbedRenderState(), $invalidator, new EventDispatcher());
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
        $availability = new EmbedRenderState();
        $availability->markRendered('v1');
        $availability->markRendered('v2');
        $availability->markRendered('v1');
        $dispatcher = new EventDispatcher();
        $collected = [];
        $dispatcher->addListener(AddCacheTagEvent::class, function (AddCacheTagEvent $event) use (&$collected): void {
            $collected = [...$collected, ...$event->tags];
        });

        $subscriber = new EmbedPageCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), $dispatcher);
        $subscriber->onResponse($this->event(new Response('', 200, ['Cache-Control' => 'public, s-maxage=7200'])));

        static::assertSame(['scalecommerce-vo-video-v1', 'scalecommerce-vo-video-v2'], $collected);
    }

    public function testAddsVideoTagsToTheRouteCache(): void
    {
        $availability = new EmbedRenderState();
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

        (new EmbedPageCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), new EventDispatcher()))->onRouteCacheTags($tagsEvent);

        static::assertSame(['scalecommerce-vo-video-v1'], $tagsEvent->tags);
    }

    /**
     * The names before 1.2.0 stay usable (deprecated subclasses; the service IDs are aliases).
     */
    public function testFormerClassNamesStillWork(): void
    {
        $state = new \ScaleCommerce\VideoOptimizer\Service\EmbedAvailability();
        $state->markRendered('v1');

        $subscriber = new \ScaleCommerce\VideoOptimizer\Subscriber\DegradedEmbedCacheSubscriber($state, $this->createStub(CacheInvalidator::class), new EventDispatcher());

        static::assertInstanceOf(EmbedRenderState::class, $state);
        static::assertInstanceOf(EmbedPageCacheSubscriber::class, $subscriber);
        // New instances satisfy type hints on the old names as well.
        static::assertInstanceOf(\ScaleCommerce\VideoOptimizer\Service\EmbedAvailability::class, new EmbedRenderState());
        static::assertInstanceOf(
            \ScaleCommerce\VideoOptimizer\Subscriber\DegradedEmbedCacheSubscriber::class,
            new EmbedPageCacheSubscriber(new EmbedRenderState(), $this->createStub(CacheInvalidator::class), new EventDispatcher())
        );
        static::assertSame(['scalecommerce-vo-video-v1'], $state->cacheTags());
    }

    /**
     * Lookups started for elements that were never read must not outlive the request (#25).
     */
    public function testCancelsPendingLookupsWhenTheMainResponseIsReady(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('reset');
        $subscriber = new EmbedPageCacheSubscriber(new EmbedRenderState(), $this->createStub(CacheInvalidator::class), new EventDispatcher(), $client);

        $subscriber->onResponse($this->event(new Response()));
        $subscriber->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::SUB_REQUEST, new Response()));
    }

    public function testCancelsPendingLookupsOnTerminate(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('reset');

        (new EmbedPageCacheSubscriber(new EmbedRenderState(), $this->createStub(CacheInvalidator::class), new EventDispatcher(), $client))->onTerminate();
    }

    private function subscriber(EmbedRenderState $availability): EmbedPageCacheSubscriber
    {
        return new EmbedPageCacheSubscriber($availability, $this->createStub(CacheInvalidator::class), new EventDispatcher());
    }

    private function event(Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
