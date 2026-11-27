<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/**
 * @deprecated since 1.2.0, use EmbedRenderState (renamed: it also collects the rendered videos for
 *             the page cache tags). Kept so existing references and service decorators keep working.
 */
class EmbedAvailability extends EmbedRenderState
{
}
