<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoListStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoListElementResolver;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class VideoListElementResolverTest extends TestCase
{
    private const CATEGORY = '0123456789abcdef0123456789abcdef';

    public function testTypeIsVideoList(): void
    {
        static::assertSame('scalecommerce-vo-video-list', (new VideoListElementResolver($this->createStub(VideoOptimizerClient::class), new NullLogger()))->getType());
    }

    public function testCollectFetchesThePageOnceAndEnrichReusesIt(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('storefrontVideoPage')
            ->with('lib-1', null, 6, ['promo', 'new'])
            ->willReturn(['items' => [['uuid' => 'a'], ['uuid' => 'b']], 'nextCursor' => 'cur_2', 'failed' => false]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $slot = $this->slot(['libraryId' => 'lib-1', 'pageSize' => '6', 'tags' => ['promo', ' ', 'new', 'promo', 5]]);
        $resolver = new VideoListElementResolver($client, new NullLogger());
        $resolver->collect($slot, $this->context());
        $resolver->enrich($slot, $this->context(), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoListStruct::class, $data);
        static::assertSame('cur_2', $data->getNextCursor());
        static::assertCount(2, $data->getItems());
    }

    /**
     * A page of cards must not mean one embed request per card: everything comes from the list, the
     * lightbox shows the hosted embed (needs only the UUID).
     */
    public function testCardsComeFromTheListWithoutAnyEmbedLookup(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('storefrontVideoPage')->willReturn(['items' => [
            ['uuid' => 'a', 'title' => 'List title', 'duration' => 65, 'poster_url' => 'https://p/a.jpg', 'resolution' => '1080x1920'],
            ['uuid' => 'b', 'title' => '', 'poster_url' => null, 'thumbnail_url' => 'https://t/b.jpg', 'duration' => null],
        ], 'nextCursor' => null, 'failed' => false]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $client->expects(static::never())->method('getEmbed');
        $client->expects(static::never())->method('queueEmbed');
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot);

        $data = $slot->getData();
        static::assertSame('lightbox', $data->getPresentation());
        [$first, $second] = $data->getItems();
        static::assertSame('a', $first['videoUuid']);
        static::assertSame('List title', $first['title']);
        static::assertSame('1:05', $first['durationLabel']);
        static::assertSame('https://p/a.jpg', $first['embed']['poster']);
        static::assertNull($first['embed']['aspectRatio']);
        static::assertSame('embed', $first['playerMode']);
        static::assertStringStartsWith('https://videooptimizer.eu/embed/a?', $first['embedUrl']);
        static::assertSame('', $first['embed']['posterSrcsetAttr']);
        static::assertSame('https://t/b.jpg', $second['embed']['poster']);
        static::assertNull($second['durationLabel']);
    }

    public function testResponsivePostersAndTitleComeFromAnAlreadyCachedEmbed(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoPage')->willReturn(['items' => [['uuid' => 'a', 'title' => '', 'poster_url' => 'https://p/a.jpg']], 'nextCursor' => null, 'failed' => false]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $client->method('peekEmbed')->willReturn([
            'title' => 'Cached title',
            'posterSrcset' => [['width' => 480, 'height' => 270, 'url' => 'https://cdn/p480.jpg']],
            'theme' => ['accentColor' => '#C72876'],
        ]);
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot);

        $card = $slot->getData()->getItems()[0];
        static::assertSame('https://cdn/p480.jpg 480w', $card['embed']['posterSrcsetAttr']);
        static::assertSame('Cached title', $card['title']);
        static::assertSame(['accentColor' => '#C72876'], $card['embed']['theme']);
    }

    public function testWithADetailPageTheCardsLink(): void
    {
        $client = $this->client([['uuid' => 'a']]);
        $slot = $this->slot(['libraryId' => 'lib-1', 'detailCategoryId' => self::CATEGORY]);

        $this->enrich($client, $slot);

        $data = $slot->getData();
        static::assertSame('detail', $data->getPresentation());
        static::assertSame(self::CATEGORY, $data->getDetailCategoryId());
    }

    public function testAnInvalidDetailCategoryIsIgnored(): void
    {
        $client = $this->client([['uuid' => 'a']]);
        $slot = $this->slot(['libraryId' => 'lib-1', 'detailCategoryId' => 'javascript:alert(1)']);

        $this->enrich($client, $slot);

        static::assertNull($slot->getData()->getDetailCategoryId());
        static::assertSame('lightbox', $slot->getData()->getPresentation());
    }

    public function testTheCursorComesFromTheElementsOwnRequestParameter(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('storefrontVideoPage')->with('lib-1', 'cur_2', 12, [])
            ->willReturn(['items' => [], 'nextCursor' => null, 'failed' => false]);
        $slot = $this->slot(['libraryId' => 'lib-1']);
        $param = 'vo_cursor_slot1';

        $this->enrich($client, $slot, [$param => 'cur_2', 'vo_cursor_other' => 'x']);

        $data = $slot->getData();
        static::assertSame($param, $data->getCursorParam());
        static::assertSame('cur_2', $data->getCursor());
    }

    public function testAnInvalidCursorIsIgnored(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('storefrontVideoPage')->with('lib-1', null, 12, [])
            ->willReturn(['items' => [], 'nextCursor' => null, 'failed' => false]);

        $this->enrich($client, $this->slot(['libraryId' => 'lib-1']), ['vo_cursor_slot1' => "x\n<script>"]);
    }

    public function testAnArrayCursorDoesNotBreakThePage(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('storefrontVideoPage')->with('lib-1', null, 12, [])
            ->willReturn(['items' => [], 'nextCursor' => null, 'failed' => false]);

        $this->enrich($client, $this->slot(['libraryId' => 'lib-1']), ['vo_cursor_slot1' => ['x']]);
    }

    public function testPageSizeIsClamped(): void
    {
        foreach ([[1, 3], [500, 48], [null, 12], ['x', 12], ['24', 24]] as [$input, $expected]) {
            $client = $this->createMock(VideoOptimizerClient::class);
            $client->expects(static::once())->method('storefrontVideoPage')->with('lib-1', null, $expected, [])
                ->willReturn(['items' => [], 'nextCursor' => null, 'failed' => false]);

            $this->enrich($client, $this->slot(['libraryId' => 'lib-1', 'pageSize' => $input]));
        }
    }

    public function testAFailedListSetsTheErrorFlag(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoPage')->willReturn(['items' => [], 'nextCursor' => null, 'failed' => true]);
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot);

        static::assertTrue($slot->getData()->hasError());
        static::assertSame([], $slot->getData()->getItems());
    }

    public function testWithoutALibraryNothingIsFetched(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('storefrontVideoPage');
        $slot = $this->slot(['headline' => 'Videos']);

        $this->enrich($client, $slot);

        static::assertSame('Videos', $slot->getData()->getHeadline());
        static::assertSame([], $slot->getData()->getItems());
    }

    public function testTheLibraryTagsThePage(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('storefrontVideoPage')->willReturn(['items' => [['uuid' => 'a']], 'nextCursor' => null, 'failed' => false]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $marked = [];
        $client->expects(static::atLeastOnce())->method('markLibraryRendered')->willReturnCallback(function (string $library, ?string $uuid) use (&$marked): void {
            $marked[] = [$library, $uuid];
        });
        // Pages are invalidated by a change of any listed video.
        $client->expects(static::once())->method('markVideoRendered')->with('a');

        $this->enrich($client, $this->slot(['libraryId' => 'lib-1']));

        static::assertContains(['lib-1', 'a'], $marked);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function client(array $items): VideoOptimizerClient
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoPage')->willReturn(['items' => $items, 'nextCursor' => null, 'failed' => false]);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        return $client;
    }

    /**
     * @param array<string, string> $query
     */
    private function enrich(VideoOptimizerClient $client, CmsSlotEntity $slot, array $query = []): void
    {
        (new VideoListElementResolver($client, new NullLogger()))->enrich($slot, $this->context($query), new ElementDataCollection());
    }

    /**
     * @param array<string, string> $query
     */
    private function context(array $query = []): ResolverContext
    {
        return new ResolverContext($this->createStub(SalesChannelContext::class), new Request($query));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot1');
        $slot->setType('scalecommerce-vo-video-list');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
