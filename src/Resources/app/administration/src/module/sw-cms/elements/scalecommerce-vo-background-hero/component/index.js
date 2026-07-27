import template from './scalecommerce-vo-background-hero-component.html.twig';
import './scalecommerce-vo-background-hero-component.scss';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';

// The storefront hero is 100vh/72vh/52vh tall; capped here so the editor stays usable
// while the three steps remain proportionally distinguishable.
const EDITOR_HEIGHTS = {
    full: 420,
    large: 340,
    medium: 260,
};

Shopware.Component.register('scalecommerce-vo-background-hero-component', {
    template,
    mixins: ['cms-element', elementVideo],

    computed: {
        videoUuid() {
            return this.element.config?.video?.value ?? null;
        },
        overlay() {
            return this.element.config?.overlay?.value || 'gradient';
        },
        height() {
            return this.element.config?.height?.value || 'large';
        },
        editorHeight() {
            return EDITOR_HEIGHTS[this.height] ?? EDITOR_HEIGHTS.large;
        },
        eyebrow() {
            return this.element.config?.eyebrow?.value || '';
        },
        headline() {
            return this.element.config?.headline?.value || '';
        },
        subline() {
            return this.element.config?.subline?.value || '';
        },
        ctaLabel() {
            return this.element.config?.ctaLabel?.value || '';
        },
        contentStyle() {
            return {
                '--scvo-hero-heading': this.element.config?.headlineColor?.value || null,
                '--scvo-hero-text': this.element.config?.textColor?.value || null,
            };
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-background-hero');
    },
});
