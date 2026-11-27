<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\DataResolver;

use Psr\Log\LoggerInterface;
use ScaleCommerce\VideoOptimizer\DataResolver\Struct\VideoDetailStruct;
use ScaleCommerce\VideoOptimizer\Service\Scenes;
use ScaleCommerce\VideoOptimizer\Service\VideoOptimizerClient;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * "Video detail": the video whose UUID is in the URL (`?vo_video=<uuid>`, set by the video list), or
 * the default video of the element when the URL carries none. A UUID from the URL is only accepted
 * for videos of the token's organization (and of the element's library, if one is chosen); an invalid,
 * unknown or foreign one shows a neutral "not found" (no title anywhere, no details about why) and no
 * player; while the API cannot answer the element says so ("temporarily unavailable").
 */
class VideoDetailElementResolver extends AbstractCmsElementResolver
{
    use VideoSurfaceTrait;

    public const PARAMETER = 'vo_video';
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        private readonly VideoOptimizerClient $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getType(): string
    {
        return 'scalecommerce-vo-video-detail';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        [$uuid] = $this->requestedVideo($slot, $resolverContext);
        if ($uuid !== null) {
            $this->client->queueEmbed($uuid);
        }

        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $struct = new VideoDetailStruct();
        $slot->setData($struct);

        [$uuid, $fromUrl] = $this->requestedVideo($slot, $resolverContext);
        $config = $slot->getFieldConfig();
        if ($uuid === null) {
            // A malformed value in the URL must not fall back to the default video.
            $struct->setNotFound($fromUrl);

            return;
        }

        if ($fromUrl) {
            // The embed is public and proves nothing: the video has to belong to the organization of
            // the token (and to the element's library, if one is chosen), checked with the token.
            $owner = $this->client->storefrontVideoOwner($uuid);
            if ($owner['status'] === 'unavailable') {
                $struct->setUnavailable(true);

                return;
            }
            $libraryId = $config->get('libraryId')?->getValue();
            if ($owner['status'] !== 'ok' || (is_string($libraryId) && $libraryId !== '' && $owner['library'] !== $libraryId)) {
                $struct->setNotFound(true);

                return;
            }
        }

        $struct->setVideoUuid($uuid);
        $surface = $this->buildVideoSurface($config, $uuid, $this->client, $this->logger);
        $struct->setPlayerMode($surface['playerMode']);
        $struct->setEmbedUrl($surface['embedUrl']);
        $struct->setLayout($surface['layout']);
        if ($surface['error']) {
            // The video exists, the player data could not be loaded: a temporary problem.
            $struct->setUnavailable($fromUrl);
            $struct->setError(!$fromUrl);

            return;
        }
        $embed = $surface['embed'];
        $struct->setEmbed($embed);
        $title = $embed['title'] ?? null;
        $struct->setTitle(is_string($title) && trim($title) !== '' ? trim($title) : null);
        $duration = (int) round((float) ($embed['duration'] ?? 0));
        $struct->setDurationLabel($duration > 0 ? Scenes::formatTime($duration) : null);
    }

    /**
     * The video to show: [uuid or null, whether it came from the URL].
     *
     * @return array{0: ?string, 1: bool}
     */
    private function requestedVideo(CmsSlotEntity $slot, ResolverContext $resolverContext): array
    {
        // all(): get() throws a BadRequestException for ?vo_video[]=x, which would break the page.
        $fromRequest = $resolverContext->getRequest()->query->all()[self::PARAMETER] ?? null;
        if ($fromRequest !== null) {
            return [is_string($fromRequest) && preg_match(self::UUID_PATTERN, $fromRequest) === 1 ? strtolower($fromRequest) : null, true];
        }
        $default = $slot->getFieldConfig()->get('videoUuid')?->getValue();

        return [is_string($default) && $default !== '' ? $default : null, false];
    }
}
