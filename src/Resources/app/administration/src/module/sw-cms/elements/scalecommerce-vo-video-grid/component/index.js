import template from './scalecommerce-vo-video-grid-component.html.twig';

Shopware.Component.register('scalecommerce-vo-video-grid-component', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('scalecommerce-vo-video-grid');
    },
    computed: {
        headline() {
            return this.element.config?.headline?.value || this.$tc('scalecommerce-vo.videoGrid.label');
        },
        count() {
            return (this.element.config?.items?.value ?? []).filter((item) => item.video).length;
        },
    },
});
