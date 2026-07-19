import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'scalecommerce-vo-background-hero',
    label: 'scalecommerce-vo.backgroundHero.label',
    component: 'scalecommerce-vo-background-hero-component',
    configComponent: 'scalecommerce-vo-background-hero-config',
    previewComponent: 'scalecommerce-vo-background-hero-preview',
    defaultConfig: {
        video: { source: 'static', value: null },
        libraryId: { source: 'static', value: null },
        eyebrow: { source: 'static', value: '' },
        headline: { source: 'static', value: '' },
        subline: { source: 'static', value: '' },
        ctaLabel: { source: 'static', value: '' },
        ctaUrl: { source: 'static', value: '' },
        overlay: { source: 'static', value: 'gradient' },
        height: { source: 'static', value: 'large' },
        headlineColor: { source: 'static', value: null },
        textColor: { source: 'static', value: null },
        priority: { source: 'static', value: false },
    },
});
