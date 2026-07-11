import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scale-video-optimizer-video',
    label: 'scale-video-optimizer.cms.label',
    component: 'scale-video-optimizer-cms-element',
    configComponent: 'scale-video-optimizer-cms-element-config',
    previewComponent: 'scale-video-optimizer-cms-element-preview',
    defaultConfig: {
        videoUuid: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
    },
});
