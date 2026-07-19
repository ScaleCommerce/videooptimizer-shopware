import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-spotlight',
    label: 'scalecommerce-vo.spotlight.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-spotlight-block',
    previewComponent: 'scalecommerce-vo-spotlight-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { spotlight: 'scalecommerce-vo-spotlight' },
});
