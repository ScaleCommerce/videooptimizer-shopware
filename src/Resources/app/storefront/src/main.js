import ScalecommerceVoPlayer from './scalecommerce-vo-player/scalecommerce-vo-player.plugin';
import ScalecommerceVoBlocks from './scalecommerce-vo-blocks/scalecommerce-vo-blocks.plugin';
// Styling lives in src/scss/base.scss, picked up by Shopware's theme compiler
// (importing .scss from JS only works in webpack hot mode, not production builds).

const PluginManager = window.PluginManager;
PluginManager.register('ScalecommerceVoPlayer', ScalecommerceVoPlayer, '[data-scalecommerce-vo-player]');
PluginManager.register('ScalecommerceVoBlocks', ScalecommerceVoBlocks, '[data-scalecommerce-vo-block]');
