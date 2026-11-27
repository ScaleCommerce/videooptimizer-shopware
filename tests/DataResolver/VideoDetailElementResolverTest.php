<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoDetailElementResolver;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class VideoDetailElementResolverTest extends TestCase
{
    private const URL_VIDEO = '11111111-2222-3333-4444-555555555555';
    private const DEFAULT_VIDEO = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    public function testTypeIsVideoDetail(): void
    {
        static::assertSame('scalecommerce-vo-video-detail', (new VideoDetailElementResolver($this->createStub(VideoOptimizerClient::class), new NullLogger()))->getType());
    }

    public function testShowsTheVideoOfTheUrlWhenItBelongsToTheLibrary(): void
    {
        $client = $this->client(['storefrontVideoOwner' => ['status' => 'ok', 'library' => 'lib-1']]);
        $slot = $this->slot(['libraryId' => 'lib-1', 'videoUuid' => self::DEFAULT_VIDEO, 'format' => '16-9']);

        $this->enrich($client, $slot, ['vo_video' => strtoupper(self::URL_VIDEO)]);

        $data = $slot->getData();
        static::assertInstanceOf(VideoDetailStruct::class, $data);
        static::assertFalse($data->isNotFound());
        static::assertSame(self::URL_VIDEO, $data->getVideoUuid());
        static::assertSame('Embed title', $data->getTitle());
        static::assertSame('1:05', $data->getDurationLabel());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        static::assertSame('16 / 9', $data->getLayout()['aspect']);
        static::assertStringContainsString('/embed/' . self::URL_VIDEO . '?', $data->getEmbedUrl());
    }

    public function testFallsBackToTheDefaultVideoWithoutAUrlParameter(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('storefrontVideoOwner');
        $client->expects(static::once())->method('getEmbed')->with(self::DEFAULT_VIDEO)->willReturn($this->embed());
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $slot = $this->slot(['libraryId' => 'lib-1', 'videoUuid' => self::DEFAULT_VIDEO]);

        $this->enrich($client, $slot);

        static::assertSame(self::DEFAULT_VIDEO, $slot->getData()->getVideoUuid());
        static::assertFalse($slot->getData()->isNotFound());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'not a uuid' => ['abc'];
        yield 'path trick' => ['../../etc/passwd'];
        yield 'markup' => ['<script>alert(1)</script>'];
        yield 'empty' => [''];
        yield 'array' => [['x']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testAnInvalidUrlValueIsNotFoundAndNeverFallsBackToTheDefault(mixed $value): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');
        $client->expects(static::never())->method('storefrontVideoOwner');
        $slot = $this->slot(['libraryId' => 'lib-1', 'videoUuid' => self::DEFAULT_VIDEO]);

        $this->enrich($client, $slot, ['vo_video' => $value]);

        static::assertTrue($slot->getData()->isNotFound());
        static::assertNull($slot->getData()->getVideoUuid());
        static::assertNull($slot->getData()->getEmbed());
    }

    public function testAVideoOfAnotherLibraryIsNotFound(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn(['status' => 'ok', 'library' => 'other-library']);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot, ['vo_video' => self::URL_VIDEO]);

        static::assertTrue($slot->getData()->isNotFound());
        static::assertNull($slot->getData()->getEmbed());
    }

    public function testAnUnknownVideoIsNotFound(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn(['status' => 'missing', 'library' => null]);
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot, ['vo_video' => self::URL_VIDEO]);

        static::assertTrue($slot->getData()->isNotFound());
    }

    public function testWithoutALibraryAnyVideoOfTheOrganizationIsShown(): void
    {
        $client = $this->client(['storefrontVideoOwner' => ['status' => 'ok', 'library' => 'any-library']]);
        $slot = $this->slot(['videoUuid' => self::DEFAULT_VIDEO]);

        $this->enrich($client, $slot, ['vo_video' => self::URL_VIDEO]);

        static::assertFalse($slot->getData()->isNotFound());
        static::assertSame(self::URL_VIDEO, $slot->getData()->getVideoUuid());
    }

    public function testAnOutageIsReportedAsUnavailableNotAsNotFound(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn(['status' => 'unavailable', 'library' => null]);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot, ['vo_video' => self::URL_VIDEO]);

        static::assertTrue($slot->getData()->isUnavailable());
        static::assertFalse($slot->getData()->isNotFound());
        static::assertNull($slot->getData()->getTitle());
    }

    public function testAForeignVideoNeverExposesItsTitle(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn(['status' => 'missing', 'library' => null]);
        $client->expects(static::never())->method('getEmbed');
        $slot = $this->slot(['libraryId' => 'lib-1']);

        $this->enrich($client, $slot, ['vo_video' => self::URL_VIDEO]);

        static::assertNull($slot->getData()->getTitle());
        static::assertNull($slot->getData()->getEmbed());
        static::assertNull($slot->getData()->getDurationLabel());
    }

    public function testAVideoWhoseEmbedFailsIsUnavailableForTheUrlAndAnErrorForTheDefault(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn(['status' => 'ok', 'library' => 'lib-1']);
        $client->method('getEmbed')->willThrowException(new \RuntimeException('boom'));
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        $fromUrl = $this->slot(['libraryId' => 'lib-1']);
        $this->enrich($client, $fromUrl, ['vo_video' => self::URL_VIDEO]);
        static::assertTrue($fromUrl->getData()->isUnavailable());
        static::assertFalse($fromUrl->getData()->isNotFound());
        static::assertFalse($fromUrl->getData()->hasError());

        $default = $this->slot(['libraryId' => 'lib-1', 'videoUuid' => self::DEFAULT_VIDEO]);
        $this->enrich($client, $default);
        static::assertFalse($default->getData()->isNotFound());
        static::assertTrue($default->getData()->hasError());
    }

    public function testWithoutAnyVideoNothingIsShown(): void
    {
        $slot = $this->slot([]);

        $this->enrich($this->createStub(VideoOptimizerClient::class), $slot);

        static::assertNull($slot->getData()->getVideoUuid());
        static::assertFalse($slot->getData()->isNotFound());
    }

    public function testCollectQueuesTheRequestedVideo(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::once())->method('queueEmbed')->with(self::URL_VIDEO);

        (new VideoDetailElementResolver($client, new NullLogger()))->collect($this->slot(['libraryId' => 'lib-1']), $this->context(['vo_video' => self::URL_VIDEO]));
    }

    /**
     * @return array<string, mixed>
     */
    private function embed(): array
    {
        return [
            'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
            'poster' => 'https://cdn/poster.jpg',
            'title' => ' Embed title ',
            'duration' => 65,
        ];
    }

    /**
     * @param array<string, mixed> $returns
     */
    private function client(array $returns): VideoOptimizerClient
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('storefrontVideoOwner')->willReturn($returns['storefrontVideoOwner'] ?? ['status' => 'missing', 'library' => null]);
        $client->method('getEmbed')->willReturn($this->embed());
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        return $client;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function enrich(VideoOptimizerClient $client, CmsSlotEntity $slot, array $query = []): void
    {
        (new VideoDetailElementResolver($client, new NullLogger()))->enrich($slot, $this->context($query), new ElementDataCollection());
    }

    /**
     * @param array<string, mixed> $query
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
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-video-detail');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
