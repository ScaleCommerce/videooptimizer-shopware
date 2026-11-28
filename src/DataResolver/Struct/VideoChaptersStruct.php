<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class VideoChaptersStruct extends Struct
{
    protected ?string $videoUuid = null;

    protected ?string $headline = null;

    protected ?string $playerMode = null;

    protected ?string $embedUrl = null;

    protected ?array $embed = null;

    /**
     * @var array<string, mixed>
     */
    protected array $layout = [];

    /**
     * @var list<array{index: int, start: int, time: string, thumb: string, title: string}>
     */
    protected array $scenes = [];

    protected bool $error = false;

    public function getVideoUuid(): ?string
    {
        return $this->videoUuid;
    }

    public function setVideoUuid(?string $v): void
    {
        $this->videoUuid = $v;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $v): void
    {
        $this->headline = $v;
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

    /**
     * @return list<array{index: int, start: int, time: string, thumb: string, title: string}>
     */
    public function getScenes(): array
    {
        return $this->scenes;
    }

    /**
     * @param list<array{index: int, start: int, time: string, thumb: string, title: string}> $v
     */
    public function setScenes(array $v): void
    {
        $this->scenes = $v;
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
        return 'scalecommerce_vo_chapters';
    }
}
