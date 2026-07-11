import './page/scalecommerce-vo-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('scalecommerce-vo', {
    type: 'plugin',
    name: 'VideoOptimizer',
    title: 'scalecommerce-vo.general.mainMenuItemGeneral',
    color: '#ff3d58',
    icon: 'regular-play-circle',
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },
    routes: {
        list: {
            component: 'scalecommerce-vo-list',
            path: 'list',
            meta: {
                privilege: 'scalecommerce_vo.viewer',
            },
        },
    },
    navigation: [{
        label: 'scalecommerce-vo.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'scalecommerce.vo.list',
        icon: 'regular-play-circle',
        position: 100,
        privilege: 'scalecommerce_vo.viewer',
    }],
});
