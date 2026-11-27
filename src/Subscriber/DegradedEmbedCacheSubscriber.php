<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

/**
 * @deprecated since 1.2.0, use EmbedPageCacheSubscriber (renamed: it tags pages per video and caps
 *             the cache lifetime of degraded renders). Kept so existing references keep working; the
 *             service ID is an alias of the new one.
 */
class DegradedEmbedCacheSubscriber extends EmbedPageCacheSubscriber
{
}
