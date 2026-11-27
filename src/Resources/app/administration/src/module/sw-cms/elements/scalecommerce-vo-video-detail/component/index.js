import template from './scalecommerce-vo-video-detail-component.html.twig';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';
import layout from '../../../../../mixin/scalecommerce-vo-layout';

Shopware.Component.register('scalecommerce-vo-video-detail-component', {
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
        title() {
            return this.video?.title || '';
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video-detail');
    },
});
