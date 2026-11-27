<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\EmbedAvailability;
use ScaleCommerce\VideoOptimizer\Subscriber\DegradedEmbedCacheSubscriber;
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

        (new DegradedEmbedCacheSubscriber($availability))->onResponse($this->event($response));

        static::assertStringContainsString($expected, (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesResponseAloneWithoutDegradedEmbed(): void
    {
        $response = new Response('', 200, ['Cache-Control' => 'public, s-maxage=7200']);

        (new DegradedEmbedCacheSubscriber(new EmbedAvailability()))->onResponse($this->event($response));

        static::assertStringContainsString('s-maxage=7200', (string) $response->headers->get('Cache-Control'));
    }

    public function testLeavesUncacheableResponseAlone(): void
    {
        $availability = new EmbedAvailability();
        $availability->markDegraded();
        $response = new Response('', 200, ['Cache-Control' => 'no-cache, private']);

        (new DegradedEmbedCacheSubscriber($availability))->onResponse($this->event($response));

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

    private function event(Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
