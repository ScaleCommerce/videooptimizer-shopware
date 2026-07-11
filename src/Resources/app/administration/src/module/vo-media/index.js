import './page/vo-media-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('vo-media', {
    type: 'plugin',
    name: 'VideoOptimizer',
    title: 'vo-media.general.mainMenuItemGeneral',
    color: '#ff3d58',
    icon: 'regular-play-circle',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },
    routes: {
        list: {
            component: 'vo-media-list',
            path: 'list',
        },
    },
    navigation: [{
        label: 'vo-media.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'vo.media.list',
        icon: 'regular-play-circle',
        position: 100,
    }],
});
