<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SpotlightStruct extends Struct
{
    protected ?string $videoUuid = null;
    protected ?string $playerMode = null;
    protected ?string $embedUrl = null;
    protected ?array $embed = null;
    protected ?string $presentation = null;
    protected ?string $eyebrow = null;
    protected ?string $headline = null;
    protected ?string $caption = null;
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
    public function getEyebrow(): ?string { return $this->eyebrow; }
    public function setEyebrow(?string $v): void { $this->eyebrow = $v; }
    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $v): void { $this->headline = $v; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $v): void { $this->caption = $v; }
    public function hasError(): bool { return $this->error; }
    public function setError(bool $v): void { $this->error = $v; }

    public function getApiAlias(): string { return 'scalecommerce_vo_spotlight'; }
}
