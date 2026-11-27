import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-chapters',
    label: 'scalecommerce-vo.chapters.label',
    component: 'scalecommerce-vo-chapters-component',
    configComponent: 'scalecommerce-vo-chapters-config',
    previewComponent: 'scalecommerce-vo-chapters-preview',
    defaultConfig: {
        videoUuid: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        headline: { source: 'static', value: '' },
        sceneCount: { source: 'static', value: 5 },
        sceneTitles: { source: 'static', value: '' },
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
