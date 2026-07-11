import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'vo-video',
    label: 'vo-media.cms.label',
    component: 'vo-cms-el-vo-video',
    configComponent: 'vo-cms-el-config-vo-video',
    previewComponent: 'vo-cms-el-preview-vo-video',
    defaultConfig: {
        videoUuid: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
        aspectRatio: { source: 'static', value: '16/9' },
    },
});
