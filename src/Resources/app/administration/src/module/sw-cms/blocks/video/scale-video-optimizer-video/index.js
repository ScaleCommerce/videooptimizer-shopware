import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scale-video-optimizer-video',
    label: 'scale-video-optimizer.cms.blockLabel',
    category: 'video',
    component: 'scale-video-optimizer-cms-block',
    previewComponent: 'scale-video-optimizer-cms-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        video: 'scale-video-optimizer-video',
    },
});
