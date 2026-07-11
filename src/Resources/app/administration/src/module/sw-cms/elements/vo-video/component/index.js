import template from './vo-cms-el-vo-video.html.twig';

Shopware.Component.register('vo-cms-el-vo-video', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('vo-video');
    },
    computed: {
        videoUuid() {
            return this.element.config.videoUuid.value;
        },
    },
});
