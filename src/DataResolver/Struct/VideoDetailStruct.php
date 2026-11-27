<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class VideoDetailStruct extends Struct
{
    protected ?string $videoUuid = null;

    protected ?string $title = null;

    protected ?string $playerMode = null;

    protected ?string $embedUrl = null;

    protected ?array $embed = null;

    /**
     * @var array<string, mixed>
     */
    protected array $layout = [];

    protected ?string $durationLabel = null;

    protected bool $notFound = false;

    protected bool $unavailable = false;

    protected bool $error = false;

    public function getVideoUuid(): ?string
    {
        return $this->videoUuid;
    }

    public function setVideoUuid(?string $v): void
    {
        $this->videoUuid = $v;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $v): void
    {
        $this->title = $v;
    }

    public function getPlayerMode(): ?string
    {
        return $this->playerMode;
    }

    public function setPlayerMode(?string $v): void
    {
        $this->playerMode = $v;
    }

    public function getEmbedUrl(): ?string
    {
        return $this->embedUrl;
    }

    public function setEmbedUrl(?string $v): void
    {
        $this->embedUrl = $v;
    }

    public function getEmbed(): ?array
    {
        return $this->embed;
    }

    public function setEmbed(?array $v): void
    {
        $this->embed = $v;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @param array<string, mixed> $v
     */
    public function setLayout(array $v): void
    {
        $this->layout = $v;
    }

    public function getDurationLabel(): ?string
    {
        return $this->durationLabel;
    }

    public function setDurationLabel(?string $v): void
    {
        $this->durationLabel = $v;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    public function setNotFound(bool $v): void
    {
        $this->notFound = $v;
    }

    public function isUnavailable(): bool
    {
        return $this->unavailable;
    }

    public function setUnavailable(bool $v): void
    {
        $this->unavailable = $v;
    }

    public function hasError(): bool
    {
        return $this->error;
    }

    public function setError(bool $v): void
    {
        $this->error = $v;
    }

    public function getApiAlias(): string
    {
        return 'scalecommerce_vo_video_detail';
    }
}
