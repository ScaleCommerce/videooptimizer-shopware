import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-video-detail',
    label: 'scalecommerce-vo.videoDetail.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-video-detail-block',
    previewComponent: 'scalecommerce-vo-video-detail-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { videoDetail: 'scalecommerce-vo-video-detail' },
});
