// Styling lives in src/scss/base.scss, picked up by Shopware's theme compiler
// (importing .scss from JS only works in webpack hot mode, not production builds).

// The plugins are registered with a dynamic import, so Shopware loads them (and hls.js) only on
// pages that contain a matching element instead of on every storefront page.
const PluginManager = window.PluginManager;
PluginManager.register(
    'ScalecommerceVoPlayer',
    () => import('./scalecommerce-vo-player/scalecommerce-vo-player.plugin'),
    '[data-scalecommerce-vo-player]'
);
PluginManager.register(
    'ScalecommerceVoBlocks',
    () => import('./scalecommerce-vo-blocks/scalecommerce-vo-blocks.plugin'),
    '[data-scalecommerce-vo-block]'
);
PluginManager.register(
    'ScalecommerceVoHero',
    () => import('./scalecommerce-vo-hero/scalecommerce-vo-hero.plugin'),
    // The second selector keeps theme overrides of the pre-1.1 hero template working.
    '[data-scalecommerce-vo-hero], [data-scalecommerce-vo-hero-hls]'
);
