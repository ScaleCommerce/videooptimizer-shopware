import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-video-grid',
    label: 'scalecommerce-vo.videoGrid.label',
    component: 'scalecommerce-vo-video-grid-component',
    configComponent: 'scalecommerce-vo-video-grid-config',
    previewComponent: 'scalecommerce-vo-video-grid-preview',
    defaultConfig: {
        headline: { source: 'static', value: '' },
        intro: { source: 'static', value: '' },
        items: { source: 'static', value: [] },
        presentation: { source: 'static', value: 'lightbox' },
        playerMode: { source: 'static', value: 'native' },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
    },
});
