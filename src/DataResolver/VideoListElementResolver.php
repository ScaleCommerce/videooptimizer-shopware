<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoListStruct;
use ScaleCommerce\VideoOptimizer\Service\EmbedSources;
use ScaleCommerce\VideoOptimizer\Service\Exception\InvalidApiBaseUrlException;
use ScaleCommerce\VideoOptimizer\Service\Scenes;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * "Video list": cards of the ready videos of a library (optionally only those with all of the given
 * tags), paged with the API cursor ("?vo_cursor_<slot id>=" links). A card links to the page that
 * holds the video detail element ("?vo_video=<uuid>"), or opens the lightbox (hosted player) when none is chosen.
 */
class VideoListElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public const DEFAULT_PAGE_SIZE = 12;
    public const MIN_PAGE_SIZE = 3;
    public const MAX_PAGE_SIZE = 48;

    /**
     * Pages fetched by collect(), consumed by enrich() (keyed by slot).
     *
     * @var array<string, array{items: list<array<string, mixed>>, nextCursor: ?string, failed: bool}>
     */
    private array $pages = [];

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-video-list';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $libraryId = $config->get('libraryId')?->getValue();
        if (!is_string($libraryId) || $libraryId === '') {
            return null;
        }

        // The page is fetched (cached) here; the cards need no embed lookup, see enrich().
        $page = $this->client->storefrontVideoPage(
            $libraryId,
            $this->cursor($slot, $resolverContext),
            $this->pageSize($config->get('pageSize')?->getValue()),
            $this->tags($config->get('tags')?->getValue()),
        );
        $this->pages[$slot->getUniqueIdentifier()] = $page;

        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoListStruct();
        $slot->setData($struct);

        $config = $slot->getFieldConfig();
        $string = static fn (string $key): ?string => is_string($config->get($key)?->getValue()) && $config->get($key)->getValue() !== '' ? $config->get($key)->getValue() : null;
        $struct->setHeadline($string('headline'));
        $struct->setIntro($string('intro'));
        $struct->setCursorParam($this->cursorParam($slot));
        $struct->setCursor($this->cursor($slot, $resolverContext));

        $detail = $string('detailCategoryId');
        $struct->setDetailCategoryId($detail !== null && preg_match('/^[0-9a-f]{32}$/i', $detail) === 1 ? $detail : null);
        // The cards open the lightbox unless a detail page is chosen.
        $struct->setPresentation($struct->getDetailCategoryId() === null ? 'lightbox' : 'detail');

        $libraryId = $string('libraryId');
        if ($libraryId === null) {
            return;
        }
        $this->markLibraryRendered($config, $this->client);

        $page = $this->pages[$slot->getUniqueIdentifier()] ?? null;
        unset($this->pages[$slot->getUniqueIdentifier()]);
        if ($page === null) {
            // enrich() without collect(): fetch the same page here.
            $page = $this->client->storefrontVideoPage(
                $libraryId,
                $struct->getCursor(),
                $this->pageSize($config->get('pageSize')?->getValue()),
                $this->tags($config->get('tags')?->getValue()),
            );
        }
        if ($page['failed']) {
            $struct->setError(true);

            return;
        }
        $struct->setNextCursor($page['nextCursor']);

        // Cards come from the list itself: no embed lookup per card (a page of 12 would mean 12 requests).
        // The responsive posters are used when the video's embed is cached anyway; the lightbox
        // always shows the hosted embed, which needs nothing but the UUID.
        $items = [];
        foreach ($page['items'] as $video) {
            $uuid = (string) $video['uuid'];
            $this->client->markLibraryRendered($libraryId, $uuid);
            $this->client->markVideoRendered($uuid);
            try {
                $embedUrl = $this->buildEmbedUrl($uuid, $config, $this->client);
            } catch (InvalidApiBaseUrlException $e) {
                $this->logger->warning('VideoOptimizer embed base URL is misconfigured; hiding the video list.', ['element' => $this->getType(), 'error' => $e->getMessage()]);
                $struct->setError(true);

                return;
            }
            $cached = $this->client->peekEmbed($uuid) ?? [];
            $srcset = EmbedSources::posterSrcset($cached['posterSrcset'] ?? null);
            $poster = is_string($video['poster_url'] ?? null) && $video['poster_url'] !== '' ? $video['poster_url'] : (is_string($video['thumbnail_url'] ?? null) ? $video['thumbnail_url'] : null);
            $title = is_string($video['title'] ?? null) && $video['title'] !== '' ? $video['title'] : (is_string($cached['title'] ?? null) ? $cached['title'] : '');
            $duration = is_numeric($video['duration'] ?? null) ? (int) round((float) $video['duration']) : 0;
            $items[] = [
                'videoUuid' => $uuid,
                'title' => $title,
                'durationLabel' => $duration > 0 ? Scenes::formatTime($duration) : null,
                'playerMode' => 'embed',
                'embedUrl' => $embedUrl,
                'embed' => [
                    'poster' => $poster,
                    'posterSrcsetAttr' => EmbedSources::srcsetAttribute($srcset),
                    'title' => $title,
                    // Cards of a list share one ratio (16:9) whatever the videos' own ratios are.
                    'aspectRatio' => null,
                    'theme' => EmbedSources::theme($cached['theme'] ?? null),
                    'tracks' => [],
                ],
            ];
        }
        $struct->setItems($items);
    }

    /**
     * Request parameter of this element's cursor: one per element, so several lists on a page page independently.
     */
    private function cursorParam(CmsSlotEntity $slot): string
    {
        return 'vo_cursor_' . preg_replace('/[^A-Za-z0-9]/', '', $slot->getUniqueIdentifier());
    }

    private function cursor(CmsSlotEntity $slot, ResolverContext $resolverContext): ?string
    {
        $value = $resolverContext->getRequest()->query->all()[$this->cursorParam($slot)] ?? null;

        return is_string($value) && preg_match('/^[A-Za-z0-9_=+\/.:-]{1,200}$/D', $value) === 1 ? $value : null;
    }

    private function pageSize(mixed $value): int
    {
        return is_numeric($value) ? max(self::MIN_PAGE_SIZE, min(self::MAX_PAGE_SIZE, (int) $value)) : self::DEFAULT_PAGE_SIZE;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $tag): string => is_string($tag) ? trim($tag) : '', $value),
            static fn (string $tag): bool => $tag !== '' && mb_strlen($tag) <= 50,
        )));
    }
}
