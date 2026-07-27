import template from './scalecommerce-vo-media-split-component.html.twig';
import './scalecommerce-vo-media-split-component.scss';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';

Shopware.Component.register('scalecommerce-vo-media-split-component', {
    template,
    mixins: ['cms-element', elementVideo],

    computed: {
        videoUuid() {
            return this.element.config?.video?.value ?? null;
        },
        side() {
            return this.element.config?.side?.value || 'left';
        },
        eyebrow() {
            return this.element.config?.eyebrow?.value || '';
        },
        headline() {
            return this.element.config?.headline?.value || '';
        },
        text() {
            return this.element.config?.text?.value || '';
        },
        ctaLabel() {
            return this.element.config?.ctaLabel?.value || '';
        },
        presentation() {
            return this.element.config?.presentation?.value || 'facade';
        },
        playerMode() {
            return this.element.config?.playerMode?.value || 'native';
        },
        showControls() {
            return this.element.config?.showControls?.value ?? true;
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-media-split');
    },
});
