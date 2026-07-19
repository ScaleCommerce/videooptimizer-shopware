import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-media-split',
    label: 'scalecommerce-vo.mediaSplit.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-media-split-block',
    previewComponent: 'scalecommerce-vo-media-split-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        mediaSplit: 'scalecommerce-vo-media-split',
    },
});
