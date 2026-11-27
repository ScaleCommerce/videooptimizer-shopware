<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Subscriber;

/*
 * DegradedEmbedCacheSubscriber is deprecated since 1.2.0, use EmbedPageCacheSubscriber (renamed: it tags pages per video and caps the cache lifetime of degraded renders).
 * The old name is a class alias declared at the end of EmbedPageCacheSubscriber.php, so type hints, instanceof
 * checks, references and decorators keep working. Autoloading this file declares the alias.
 */
class_exists(EmbedPageCacheSubscriber::class);
