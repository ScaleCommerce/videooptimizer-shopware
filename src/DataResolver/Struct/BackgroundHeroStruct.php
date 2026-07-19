<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class BackgroundHeroStruct extends Struct
{
    protected ?string $videoUuid = null;
    protected ?array $embed = null;
    protected ?string $eyebrow = null;
    protected ?string $headline = null;
    protected ?string $subline = null;
    protected ?string $ctaLabel = null;
    protected ?string $ctaUrl = null;
    protected ?string $overlay = null;
    protected ?string $height = null;
    protected ?string $headlineColor = null;
    protected ?string $textColor = null;
    protected bool $priority = false;
    protected bool $error = false;

    public function getVideoUuid(): ?string { return $this->videoUuid; }
    public function setVideoUuid(?string $v): void { $this->videoUuid = $v; }
    public function getEmbed(): ?array { return $this->embed; }
    public function setEmbed(?array $v): void { $this->embed = $v; }
    public function getEyebrow(): ?string { return $this->eyebrow; }
    public function setEyebrow(?string $v): void { $this->eyebrow = $v; }
    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $v): void { $this->headline = $v; }
    public function getSubline(): ?string { return $this->subline; }
    public function setSubline(?string $v): void { $this->subline = $v; }
    public function getCtaLabel(): ?string { return $this->ctaLabel; }
    public function setCtaLabel(?string $v): void { $this->ctaLabel = $v; }
    public function getCtaUrl(): ?string { return $this->ctaUrl; }
    public function setCtaUrl(?string $v): void { $this->ctaUrl = $v; }
    public function getOverlay(): ?string { return $this->overlay; }
    public function setOverlay(?string $v): void { $this->overlay = $v; }
    public function getHeight(): ?string { return $this->height; }
    public function setHeight(?string $v): void { $this->height = $v; }
    public function getHeadlineColor(): ?string { return $this->headlineColor; }
    public function setHeadlineColor(?string $v): void { $this->headlineColor = $v; }
    public function getTextColor(): ?string { return $this->textColor; }
    public function setTextColor(?string $v): void { $this->textColor = $v; }
    public function getPriority(): bool { return $this->priority; }
    public function setPriority(bool $v): void { $this->priority = $v; }
    public function hasError(): bool { return $this->error; }
    public function setError(bool $v): void { $this->error = $v; }

    public function getApiAlias(): string { return 'scalecommerce_vo_background_hero'; }
}
