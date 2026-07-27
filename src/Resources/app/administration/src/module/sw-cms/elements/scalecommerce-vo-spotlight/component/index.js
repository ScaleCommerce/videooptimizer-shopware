import template from './scalecommerce-vo-spotlight-component.html.twig';
import './scalecommerce-vo-spotlight-component.scss';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';

Shopware.Component.register('scalecommerce-vo-spotlight-component', {
    template,
    mixins: ['cms-element', elementVideo],

    computed: {
        videoUuid() {
            return this.element.config?.video?.value ?? null;
        },
        eyebrow() {
            return this.element.config?.eyebrow?.value || '';
        },
        headline() {
            return this.element.config?.headline?.value || '';
        },
        caption() {
            return this.element.config?.caption?.value || '';
        },
        presentation() {
            return this.element.config?.presentation?.value || 'lightbox';
        },
        playerMode() {
            return this.element.config?.playerMode?.value || 'native';
        },
        showControls() {
            return this.element.config?.showControls?.value ?? true;
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-spotlight');
    },
});
