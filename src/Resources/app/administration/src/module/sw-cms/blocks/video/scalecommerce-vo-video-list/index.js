import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-video-list',
    label: 'scalecommerce-vo.videoList.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-video-list-block',
    previewComponent: 'scalecommerce-vo-video-list-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { videoList: 'scalecommerce-vo-video-list' },
});
