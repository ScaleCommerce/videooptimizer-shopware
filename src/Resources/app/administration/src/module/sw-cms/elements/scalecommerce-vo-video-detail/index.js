import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-video-detail',
    label: 'scalecommerce-vo.videoDetail.label',
    component: 'scalecommerce-vo-video-detail-component',
    configComponent: 'scalecommerce-vo-video-detail-config',
    previewComponent: 'scalecommerce-vo-video-detail-preview',
    defaultConfig: {
        videoUuid: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        playerMode: { source: 'static', value: 'native' },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
        format: { source: 'static', value: 'original' },
        focalX: { source: 'static', value: 50 },
        focalY: { source: 'static', value: 50 },
    },
});
