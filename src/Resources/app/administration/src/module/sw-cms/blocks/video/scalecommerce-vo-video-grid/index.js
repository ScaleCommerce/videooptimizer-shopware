import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-video-grid',
    label: 'scalecommerce-vo.videoGrid.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-video-grid-block',
    previewComponent: 'scalecommerce-vo-video-grid-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { videoGrid: 'scalecommerce-vo-video-grid' },
});
