<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver\Struct;

use Shopware\Core\Framework\Struct\Struct;

class VideoListStruct extends Struct
{
    protected ?string $headline = null;

    protected ?string $intro = null;

    protected string $presentation = 'lightbox';

    protected ?string $detailCategoryId = null;

    protected string $cursorParam = '';

    protected ?string $cursor = null;

    protected ?string $nextCursor = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $items = [];

    protected bool $error = false;

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $v): void
    {
        $this->headline = $v;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $v): void
    {
        $this->intro = $v;
    }

    public function getPresentation(): string
    {
        return $this->presentation;
    }

    public function setPresentation(string $v): void
    {
        $this->presentation = $v;
    }

    public function getDetailCategoryId(): ?string
    {
        return $this->detailCategoryId;
    }

    public function setDetailCategoryId(?string $v): void
    {
        $this->detailCategoryId = $v;
    }

    public function getCursorParam(): string
    {
        return $this->cursorParam;
    }

    public function setCursorParam(string $v): void
    {
        $this->cursorParam = $v;
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    public function setCursor(?string $v): void
    {
        $this->cursor = $v;
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function setNextCursor(?string $v): void
    {
        $this->nextCursor = $v;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<int, array<string, mixed>> $v
     */
    public function setItems(array $v): void
    {
        $this->items = $v;
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
        return 'scalecommerce_vo_video_list';
    }
}
