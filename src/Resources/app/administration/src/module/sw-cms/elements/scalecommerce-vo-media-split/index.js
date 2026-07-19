import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-media-split',
    label: 'scalecommerce-vo.mediaSplit.label',
    component: 'scalecommerce-vo-media-split-component',
    configComponent: 'scalecommerce-vo-media-split-config',
    previewComponent: 'scalecommerce-vo-media-split-preview',
    defaultConfig: {
        video: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        side: { source: 'static', value: 'left' },
        eyebrow: { source: 'static', value: '' },
        headline: { source: 'static', value: '' },
        text: { source: 'static', value: '' },
        ctaLabel: { source: 'static', value: '' },
        ctaUrl: { source: 'static', value: '' },
        presentation: { source: 'static', value: 'facade' },
        playerMode: { source: 'static', value: 'native' },
        showControls: { source: 'static', value: true },
        autoplay: { source: 'static', value: false },
        muted: { source: 'static', value: false },
        loop: { source: 'static', value: false },
    },
});
