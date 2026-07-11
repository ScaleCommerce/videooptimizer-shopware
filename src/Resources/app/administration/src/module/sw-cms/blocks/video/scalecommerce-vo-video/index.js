import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-video',
    label: 'scalecommerce-vo.cms.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-cms-block',
    previewComponent: 'scalecommerce-vo-cms-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        video: 'scalecommerce-vo-video',
    },
});
