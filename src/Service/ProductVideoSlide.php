<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\ProductVideoStruct;
use ScaleCommerce\VideoOptimizer\DataResolver\VideoSurfaceTrait;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Turns the video of a product (custom fields `scalecommerce_vo_product_video` and
 * `scalecommerce_vo_product_video_library`, no entity and no migration) into a slide of the
 * product image gallery: a media item of type video that carries the player data as extension.
 * The gallery template (utilities/video.html.twig) renders it as poster + play button.
 */
class ProductVideoSlide
{
    use VideoSurfaceTrait;

    public const FIELD = 'scalecommerce_vo_product_video';
    public const FIELD_LIBRARY = 'scalecommerce_vo_product_video_library';
    public const EXTENSION = 'scalecommerceVoVideo';

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly SystemConfigService $systemConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'product-video';
    }

    /**
     * @param array<string, mixed>|null $customFields translated custom fields of the product (variants inherit)
     */
    public function create(?array $customFields): ?ImageSliderItemStruct
    {
        $uuid = $customFields[self::FIELD] ?? null;
        if (!is_string($uuid) || preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $uuid) !== 1) {
            return null;
        }
        $library = $customFields[self::FIELD_LIBRARY] ?? null;
        $mode = $this->systemConfig->getString('ScaleVideoOptimizer.config.productVideoPlayerMode') === 'embed' ? 'embed' : 'native';

        $config = new FieldConfigCollection();
        $config->add(new FieldConfig('playerMode', FieldConfig::SOURCE_STATIC, $mode));
        if (is_string($library) && $library !== '') {
            $config->add(new FieldConfig('libraryId', FieldConfig::SOURCE_STATIC, $library));
        }
        $surface = $this->buildVideoSurface($config, $uuid, $this->client, $this->logger);
        if ($surface['error']) {
            return null;
        }
        $embed = $surface['embed'];
        $title = is_string($embed['title'] ?? null) ? $embed['title'] : '';

        $media = new MediaEntity();
        $id = Uuid::randomHex();
        $media->setId($id);
        $media->setUniqueIdentifier($id);
        $media->setMediaType(new VideoType());
        $media->setMimeType('video/mp4');
        $media->setFileExtension('mp4');
        $media->setPrivate(false);
        // Only used by a theme that renders the plain <video> of the core template instead.
        $media->setUrl((string) ($embed['mp4'] ?? $embed['hls'] ?? $surface['embedUrl']));
        $media->addExtension(self::EXTENSION, new ProductVideoStruct($uuid, $surface['playerMode'], $surface['embedUrl'], $embed, $title));

        $item = new ImageSliderItemStruct();
        $item->setMedia($media);

        return $item;
    }
}
