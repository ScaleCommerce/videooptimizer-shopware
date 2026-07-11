import ScaleVideoOptimizerPlayer from './scale-video-optimizer-player/scale-video-optimizer-player.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('ScaleVideoOptimizerPlayer', ScaleVideoOptimizerPlayer, '[data-scale-video-optimizer-player]');
