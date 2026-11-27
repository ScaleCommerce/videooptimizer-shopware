// Styling lives in src/scss/base.scss, picked up by Shopware's theme compiler
// (importing .scss from JS only works in webpack hot mode, not production builds).

// The plugins are registered with a dynamic import, so Shopware loads them (and hls.js) only on
// pages that contain a matching element instead of on every storefront page.
const PluginManager = window.PluginManager;

// Shopware 6.6 loads all async plugins of a page together and stops initialising every plugin if
// one chunk fails to load (6.7 skips just that one); fall back to a no-op plugin instead.
const load = (importer) => () =>
    importer().catch(() => ({
        default: class extends window.PluginBaseClass {
            init() {}
        },
    }));

PluginManager.register(
    'ScalecommerceVoPlayer',
    load(
        () => import('./scalecommerce-vo-player/scalecommerce-vo-player.plugin')
    ),
    '[data-scalecommerce-vo-player]'
);
PluginManager.register(
    'ScalecommerceVoBlocks',
    load(
        () => import('./scalecommerce-vo-blocks/scalecommerce-vo-blocks.plugin')
    ),
    '[data-scalecommerce-vo-block]'
);
PluginManager.register(
    'ScalecommerceVoHero',
    load(() => import('./scalecommerce-vo-hero/scalecommerce-vo-hero.plugin')),
    // The second selector keeps theme overrides of the pre-1.1 hero template working.
    '[data-scalecommerce-vo-hero], [data-scalecommerce-vo-hero-hls]'
);
PluginManager.register(
    'ScalecommerceVoChapters',
    load(
        () =>
            import(
                './scalecommerce-vo-chapters/scalecommerce-vo-chapters.plugin'
            )
    ),
    '[data-scalecommerce-vo-chapters]'
);
PluginManager.register(
    'ScalecommerceVoMini',
    load(() => import('./scalecommerce-vo-mini/scalecommerce-vo-mini.plugin')),
    '[data-scalecommerce-vo-mini]'
);
