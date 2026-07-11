import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-video',
    label: 'scalecommerce-vo.cms.label',
    component: 'scalecommerce-vo-cms-element',
    configComponent: 'scalecommerce-vo-cms-element-config',
    previewComponent: 'scalecommerce-vo-cms-element-preview',
    defaultConfig: {
        videoUuid: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
    },
});
