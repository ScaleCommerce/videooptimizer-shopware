import ScalecommerceVoPlayer from './scalecommerce-vo-player/scalecommerce-vo-player.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('ScalecommerceVoPlayer', ScalecommerceVoPlayer, '[data-scalecommerce-vo-player]');
