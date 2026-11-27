import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-chapters',
    label: 'scalecommerce-vo.chapters.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-chapters-block',
    previewComponent: 'scalecommerce-vo-chapters-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { chapters: 'scalecommerce-vo-chapters' },
});
