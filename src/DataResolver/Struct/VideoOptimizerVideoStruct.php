<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class VideoOptimizerVideoStruct extends Struct
{
    protected ?string $videoUuid = null;

    protected ?array $embed = null;

    protected bool $error = false;

    public function getVideoUuid(): ?string
    {
        return $this->videoUuid;
    }

    public function setVideoUuid(?string $videoUuid): void
    {
        $this->videoUuid = $videoUuid;
    }

    public function getEmbed(): ?array
    {
        return $this->embed;
    }

    public function setEmbed(?array $embed): void
    {
        $this->embed = $embed;
    }

    public function hasError(): bool
    {
        return $this->error;
    }

    public function setError(bool $error): void
    {
        $this->error = $error;
    }

    public function getApiAlias(): string
    {
        return 'scalecommerce_vo_video';
    }
}
