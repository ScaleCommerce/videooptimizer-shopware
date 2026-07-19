import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-spotlight',
    label: 'scalecommerce-vo.spotlight.label',
    component: 'scalecommerce-vo-spotlight-component',
    configComponent: 'scalecommerce-vo-spotlight-config',
    previewComponent: 'scalecommerce-vo-spotlight-preview',
    defaultConfig: {
        video: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        eyebrow: { source: 'static', value: '' },
        headline: { source: 'static', value: '' },
        caption: { source: 'static', value: '' },
        presentation: { source: 'static', value: 'lightbox' },
        playerMode: { source: 'static', value: 'native' },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
    },
});
