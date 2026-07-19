import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'scalecommerce-vo-background-hero',
    label: 'scalecommerce-vo.backgroundHero.blockLabel',
    category: 'video',
    component: 'scalecommerce-vo-background-hero-block',
    previewComponent: 'scalecommerce-vo-background-hero-block-preview',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: { backgroundHero: 'scalecommerce-vo-background-hero' },
});
