<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class MediaSplitStruct extends Struct
{
    protected ?string $videoUuid = null;
    protected ?string $playerMode = null;
    protected ?string $embedUrl = null;
    protected ?array $embed = null;
    protected ?string $presentation = null;
    protected ?string $side = null;
    protected ?string $eyebrow = null;
    protected ?string $headline = null;
    protected ?string $text = null;
    protected ?string $ctaLabel = null;
    protected ?string $ctaUrl = null;
    protected bool $error = false;

    public function getVideoUuid(): ?string { return $this->videoUuid; }
    public function setVideoUuid(?string $v): void { $this->videoUuid = $v; }
    public function getPlayerMode(): ?string { return $this->playerMode; }
    public function setPlayerMode(?string $v): void { $this->playerMode = $v; }
    public function getEmbedUrl(): ?string { return $this->embedUrl; }
    public function setEmbedUrl(?string $v): void { $this->embedUrl = $v; }
    public function getEmbed(): ?array { return $this->embed; }
    public function setEmbed(?array $v): void { $this->embed = $v; }
    public function getPresentation(): ?string { return $this->presentation; }
    public function setPresentation(?string $v): void { $this->presentation = $v; }
    public function getSide(): ?string { return $this->side; }
    public function setSide(?string $v): void { $this->side = $v; }
    public function getEyebrow(): ?string { return $this->eyebrow; }
    public function setEyebrow(?string $v): void { $this->eyebrow = $v; }
    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $v): void { $this->headline = $v; }
    public function getText(): ?string { return $this->text; }
    public function setText(?string $v): void { $this->text = $v; }
    public function getCtaLabel(): ?string { return $this->ctaLabel; }
    public function setCtaLabel(?string $v): void { $this->ctaLabel = $v; }
    public function getCtaUrl(): ?string { return $this->ctaUrl; }
    public function setCtaUrl(?string $v): void { $this->ctaUrl = $v; }
    public function hasError(): bool { return $this->error; }
    public function setError(bool $v): void { $this->error = $v; }

    public function getApiAlias(): string { return 'scalecommerce_vo_media_split'; }
}
