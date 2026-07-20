<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class VideoGridStruct extends Struct
{
    protected ?string $headline = null;
    protected ?string $intro = null;
    protected ?string $presentation = null;

    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    public function getHeadline(): ?string { return $this->headline; }
    public function setHeadline(?string $v): void { $this->headline = $v; }
    public function getIntro(): ?string { return $this->intro; }
    public function setIntro(?string $v): void { $this->intro = $v; }
    public function getPresentation(): ?string { return $this->presentation; }
    public function setPresentation(?string $v): void { $this->presentation = $v; }

    /** @return array<int, array<string, mixed>> */
    public function getItems(): array { return $this->items; }
    /** @param array<int, array<string, mixed>> $v */
    public function setItems(array $v): void { $this->items = $v; }

    public function getApiAlias(): string { return 'scalecommerce_vo_video_grid'; }
}
