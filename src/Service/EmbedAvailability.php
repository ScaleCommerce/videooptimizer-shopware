<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Service;

/*
 * EmbedAvailability is deprecated since 1.2.0, use EmbedRenderState (renamed: it also collects the rendered videos for the page cache tags).
 * The old name is a class alias declared at the end of EmbedRenderState.php, so type hints, instanceof
 * checks, references and decorators keep working. Autoloading this file declares the alias.
 */
class_exists(EmbedRenderState::class);
