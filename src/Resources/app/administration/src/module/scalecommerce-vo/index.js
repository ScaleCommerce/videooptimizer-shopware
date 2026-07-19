import './page/scalecommerce-vo-list';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('scalecommerce-vo', {
    type: 'plugin',
    name: 'VideoOptimizer',
    title: 'scalecommerce-vo.general.mainMenuItemGeneral',
    color: '#ff3d58',
    icon: 'regular-play',
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
        // Nest under the "Content" main menu entry so the module is reachable from the sidebar.
        // A top-level entry without a parent is not rendered by the admin menu in Shopware 6.7.
        parent: 'sw-content',
        path: 'scalecommerce.vo.list',
        icon: 'regular-play',
        position: 100,
        privilege: 'scalecommerce_vo.viewer',
    }],
});
