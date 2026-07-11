import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'vo-video',
    label: 'vo-media.cms.blockLabel',
    category: 'video',
    component: 'sw-cms-block-vo-video',
    previewComponent: 'sw-cms-preview-vo-video',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        video: 'vo-video',
    },
});
