import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-video-list',
    label: 'scalecommerce-vo.videoList.label',
    component: 'scalecommerce-vo-video-list-component',
    configComponent: 'scalecommerce-vo-video-list-config',
    previewComponent: 'scalecommerce-vo-video-list-preview',
    defaultConfig: {
        libraryId: { source: 'static', value: null },
        tags: { source: 'static', value: [] },
        pageSize: { source: 'static', value: 12 },
        detailCategoryId: { source: 'static', value: null },
        headline: { source: 'static', value: '' },
        intro: { source: 'static', value: '' },
    },
});
