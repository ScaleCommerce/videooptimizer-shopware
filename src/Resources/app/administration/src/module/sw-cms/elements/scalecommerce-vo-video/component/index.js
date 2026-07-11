import template from './scalecommerce-vo-cms-element.html.twig';

Shopware.Component.register('scalecommerce-vo-cms-element', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('scalecommerce-vo-video');
    },
    computed: {
        videoUuid() {
            return this.element.config.videoUuid.value;
        },
    },
});
