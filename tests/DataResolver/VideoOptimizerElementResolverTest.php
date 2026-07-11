<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\DataResolver;

use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoOptimizerVideoStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoOptimizerElementResolver;
use ScaleCommerce\VideoOptimizer\Service\Exception\VideoOptimizerApiException;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

class VideoOptimizerElementResolverTest extends TestCase
{
    public function testGetTypeIsVoVideo(): void
    {
        $resolver = new VideoOptimizerElementResolver($this->createMock(VideoOptimizerClient::class));
        static::assertSame('vo-video', $resolver->getType());
    }

    public function testEnrichSetsEmbedData(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->with('uuid-1')->willReturn(['uuid' => 'uuid-1', 'sources' => ['hls' => 'master.m3u8']]);

        $slot = $this->slotWithUuid('uuid-1');
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoOptimizerVideoStruct::class, $data);
        static::assertFalse($data->hasError());
        static::assertSame('master.m3u8', $data->getEmbed()['sources']['hls']);
    }

    public function testEnrichHandlesApiErrorGracefully(): void
    {
        $client = $this->createMock(VideoOptimizerClient::class);
        $client->method('getEmbed')->willThrowException(new VideoOptimizerApiException(404, 'Not found'));

        $slot = $this->slotWithUuid('missing');
        $resolver = new VideoOptimizerElementResolver($client);
        $resolver->enrich($slot, $this->createMock(ResolverContext::class), new ElementDataCollection());

        static::assertTrue($slot->getData()->hasError());
    }

    private function slotWithUuid(string $uuid): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-1');
        $slot->setType('vo-video');
        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('videoUuid', FieldConfig::SOURCE_STATIC, $uuid));
        $slot->setFieldConfig($config);
        return $slot;
    }
}
