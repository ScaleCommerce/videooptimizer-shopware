import VoVideoPlayer from './vo-video-player/vo-video-player.plugin';

const PluginManager = window.PluginManager;
PluginManager.register('VoVideoPlayer', VoVideoPlayer, '[data-vo-video-player]');
