import './page/scale-video-optimizer-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('scale-video-optimizer', {
    type: 'plugin',
    name: 'VideoOptimizer',
    title: 'scale-video-optimizer.general.mainMenuItemGeneral',
    color: '#ff3d58',
    icon: 'regular-play-circle',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },
    routes: {
        list: {
            component: 'scale-video-optimizer-list',
            path: 'list',
            meta: {
                privilege: 'scale_video_optimizer.viewer',
            },
        },
    },
    navigation: [{
        label: 'scale-video-optimizer.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'scale.video.optimizer.list',
        icon: 'regular-play-circle',
        position: 100,
        privilege: 'scale_video_optimizer.viewer',
    }],
});
