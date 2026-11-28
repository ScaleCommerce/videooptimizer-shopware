<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\ProductVideoStruct;
use ScaleCommerce\VideoOptimizer\Service\ProductVideoSlide;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductVideoSlideTest extends TestCase
{
    private const EMBED = [
        'sources' => [['src' => 'https://cdn/master.m3u8', 'type' => 'application/vnd.apple.mpegurl', 'codec' => 'hls'], ['src' => 'https://cdn/720p.mp4', 'type' => 'video/mp4', 'codec' => 'h264', 'label' => '720p']],
        'poster' => 'https://cdn/poster.jpg',
        'title' => 'Product film',
        'resolution' => '1920x1080',
    ];

    public function testBuildsAVideoMediaItemWithThePlayerData(): void
    {
        $client = $this->client(self::EMBED);
        $item = $this->slide($client)->create([ProductVideoSlide::FIELD => 'vid-1', ProductVideoSlide::FIELD_LIBRARY => 'lib-1']);

        $media = $item->getMedia();
        static::assertSame('VIDEO', $media->getMediaType()->getName());
        static::assertSame('video/mp4', $media->getMimeType());
        static::assertSame('https://cdn/720p.mp4', $media->getUrl());
        $video = $media->getExtension(ProductVideoSlide::EXTENSION);
        static::assertInstanceOf(ProductVideoStruct::class, $video);
        static::assertSame('vid-1', $video->getVideoUuid());
        static::assertSame('Product film', $video->getTitle());
        static::assertSame('native', $video->getPlayerMode());
        static::assertSame('https://cdn/poster.jpg', $video->getEmbed()['poster']);
        static::assertStringStartsWith('https://videooptimizer.eu/embed/vid-1?', $video->getEmbedUrl());
    }

    public function testThePluginSettingChoosesTheHostedPlayer(): void
    {
        $item = $this->slide($this->client(self::EMBED), 'embed')->create([ProductVideoSlide::FIELD => 'vid-1']);

        static::assertSame('embed', $item->getMedia()->getExtension(ProductVideoSlide::EXTENSION)->getPlayerMode());
    }

    public function testTheLibraryTagsThePage(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn(self::EMBED);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        $client->expects(static::atLeastOnce())->method('markLibraryRendered')->with('lib-1', 'vid-1');

        $this->slide($client)->create([ProductVideoSlide::FIELD => 'vid-1', ProductVideoSlide::FIELD_LIBRARY => 'lib-1']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|null}>
     */
    public static function noVideo(): iterable
    {
        yield 'no custom fields' => [null];
        yield 'empty' => [[]];
        yield 'blank' => [[ProductVideoSlide::FIELD => '']];
        yield 'not a string' => [[ProductVideoSlide::FIELD => ['x']]];
        yield 'path trick' => [[ProductVideoSlide::FIELD => '../x']];
    }

    /**
     * @param array<string, mixed>|null $fields
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('noVideo')]
    public function testWithoutAValidVideoThereIsNoSlide(?array $fields): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->expects(static::never())->method('getEmbed');

        static::assertNull($this->slide($client)->create($fields));
    }

    public function testAVideoThatCannotBeLoadedOrPlayedIsLeftOut(): void
    {
        $failing = $this->createStub(VideoOptimizerClient::class);
        $failing->method('getEmbed')->willThrowException(new \RuntimeException('gone'));
        $failing->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');
        static::assertNull($this->slide($failing)->create([ProductVideoSlide::FIELD => 'vid-1']));

        static::assertNull($this->slide($this->client(['sources' => [], 'poster' => 'https://cdn/p.jpg']))->create([ProductVideoSlide::FIELD => 'vid-1']));
    }

    /**
     * @param array<string, mixed> $embed
     */
    private function client(array $embed): VideoOptimizerClient
    {
        $client = $this->createStub(VideoOptimizerClient::class);
        $client->method('getEmbed')->willReturn($embed);
        $client->method('embedBaseUrl')->willReturn('https://videooptimizer.eu');

        return $client;
    }

    private function slide(VideoOptimizerClient $client, string $mode = 'native'): ProductVideoSlide
    {
        $config = $this->createStub(SystemConfigService::class);
        $config->method('getString')->willReturnMap([['ScaleVideoOptimizer.config.productVideoPlayerMode', null, $mode]]);

        return new ProductVideoSlide($client, $config, new NullLogger());
    }
}
