<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * The video of a product as a slide of the product image gallery (extension `scalecommerceVoVideo`
 * of the gallery's media item).
 */
class ProductVideoStruct extends Struct
{
    public function __construct(
        protected string $videoUuid,
        protected string $playerMode,
        protected string $embedUrl,
        /**
         * @var array<string, mixed>
         */
        protected array $embed,
        protected string $title,
    ) {
    }

    public function getVideoUuid(): string
    {
        return $this->videoUuid;
    }

    public function getPlayerMode(): string
    {
        return $this->playerMode;
    }

    public function getEmbedUrl(): string
    {
        return $this->embedUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmbed(): array
    {
        return $this->embed;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getApiAlias(): string
    {
        return 'scalecommerce_vo_product_video';
    }
}
