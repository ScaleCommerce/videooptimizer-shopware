import template from './scale-video-optimizer-cms-element.html.twig';

Shopware.Component.register('scale-video-optimizer-cms-element', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('scale-video-optimizer-video');
    },
    computed: {
        videoUuid() {
            return this.element.config.videoUuid.value;
        },
    },
});
