import template from './scalecommerce-vo-cms-element.html.twig';
import './scalecommerce-vo-cms-element.scss';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';
import layout from '../../../../../mixin/scalecommerce-vo-layout';

Shopware.Component.register('scalecommerce-vo-cms-element', {
    template,
    mixins: ['cms-element', elementVideo, layout],

    computed: {
        videoUuid() {
            return this.element.config?.videoUuid?.value ?? null;
        },
        playerMode() {
            return this.element.config?.playerMode?.value || 'native';
        },
        showControls() {
            return this.element.config?.showControls?.value ?? true;
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video');
    },
});
