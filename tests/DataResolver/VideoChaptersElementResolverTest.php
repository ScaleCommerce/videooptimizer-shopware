<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoChaptersStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoChaptersElementResolver;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoChaptersElementResolverTest extends TestCase
{
    private const EMBED = [
        'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls']],
        'poster' => 'https://cdn/poster.jpg',
        'resolution' => '1920x1080',
        'duration' => 65,
    ];

    public function testTypeIsChapters(): void
    {
        static::assertSame('scalecommerce-vo-chapters', $this->resolver($this->createStub(VideoOptimizerClient::class))->getType());
    }

    public function testEnrichBuildsTheScenesFromTheDurationAndTheThumbnails(): void
    {
        $slot = $this->slot(['videoUuid' => 'uuid-1', 'sceneCount' => 5, 'sceneTitles' => "Intro\n\n<b>Demo</b>", 'headline' => ' Product tour ']);
        $this->resolver($this->client(self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoChaptersStruct::class, $data);
        static::assertFalse($data->hasError());
        static::assertSame('Product tour', $data->getHeadline());
        static::assertSame('https://cdn/master.m3u8', $data->getEmbed()['hls']);
        $scenes = $data->getScenes();
        static::assertSame([0, 2, 5, 7, 9], array_column($scenes, 'index'));
        static::assertSame([5, 17, 35, 47, 59], array_column($scenes, 'start'));
        static::assertSame(['0:05', '0:17', '0:35', '0:47', '0:59'], array_column($scenes, 'time'));
        static::assertSame(['Intro', '', 'Demo', '', ''], array_column($scenes, 'title'));
        static::assertSame('https://api.example/videos/uuid-1/thumbnails/9', $scenes[4]['thumb']);
    }

    public function testSceneTimesUseTheFloatDuration(): void
    {
        $slot = $this->slot(['videoUuid' => 'uuid-1', 'sceneCount' => 10]);
        $this->resolver($this->client(['duration' => 65.9] + self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        // floor(65.9 / 11 * 10) = 59; rounding the duration first would give 60.
        static::assertSame(59, $slot->getData()->getScenes()[9]['start']);
    }

    public function testSceneCountIsClampedAndDefaultsToFive(): void
    {
        foreach ([[null, 5], ['x', 5], [1, 3], [99, 10], ['7', 7]] as [$input, $expected]) {
            $slot = $this->slot(['videoUuid' => 'uuid-1', 'sceneCount' => $input]);
            $this->resolver($this->client(self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

            static::assertCount($expected, $slot->getData()->getScenes());
        }
    }

    public function testNoScenesWithoutADuration(): void
    {
        foreach ([['duration' => 0], ['duration' => null], ['duration' => 'n/a']] as $extra) {
            $slot = $this->slot(['videoUuid' => 'uuid-1']);
            $this->resolver($this->client($extra + self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

            static::assertSame([], $slot->getData()->getScenes());
            static::assertFalse($slot->getData()->hasError());
        }
    }

    public function testFailedLookupSetsTheErrorFlagWithoutScenes(): void
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new \RuntimeException('gone'));
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $slot = $this->slot(['videoUuid' => 'uuid-1']);
        $this->resolver($client)->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        static::assertTrue($slot->getData()->hasError());
        static::assertSame([], $slot->getData()->getScenes());
    }

    public function testWithoutAVideoNothingIsResolved(): void
    {
        $slot = $this->slot([]);
        $this->resolver($this->client(self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        static::assertNull($slot->getData()->getVideoUuid());
        static::assertSame([], $slot->getData()->getScenes());
    }

    public function testFormatOptionsReachTheStruct(): void
    {
        $slot = $this->slot(['videoUuid' => 'uuid-1', 'format' => '16-9', 'miniPlayer' => true]);
        $this->resolver($this->client(self::EMBED))->enrich($slot, $this->createStub(ResolverContext::class), new ElementDataCollection());

        static::assertSame('16 / 9', $slot->getData()->getLayout()['aspect']);
        static::assertTrue($slot->getData()->getLayout()['mini']);
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function client(array $embed): VideoOptimizerClient
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn($embed);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $client->method('thumbnailUrl')->willReturnCallback(static fn (string $uuid, int $index): string => "https://api.example/videos/$uuid/thumbnails/$index");

        return $client;
    }

    private function resolver(VideoOptimizerClient $client): VideoChaptersElementResolver
    {
        return new VideoChaptersElementResolver($client, new NullLogger());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function slot(array $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('scalecommerce-vo-chapters');
        $collection = new FieldConfigCollection();
        foreach ($config as $name => $value) {
            $collection->add(new FieldConfig($name, FieldConfig::SOURCE_STATIC, $value));
        }
        $slot->setFieldConfig($collection);

        return $slot;
    }
}
