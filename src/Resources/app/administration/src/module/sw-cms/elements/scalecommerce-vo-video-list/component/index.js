import template from './scalecommerce-vo-video-list-component.html.twig';
import './scalecommerce-vo-video-list-component.scss';

const PLACEHOLDER_CARDS = 6;

Shopware.Component.register('scalecommerce-vo-video-list-component', {
    template,
    mixins: ['cms-element'],

    computed: {
        headline() {
            return this.element.config?.headline?.value || '';
        },
        cards() {
            const size = Number(this.element.config?.pageSize?.value);
            const count = Number.isFinite(size) && size > 0 ? size : 12;
            return Array.from(
                { length: Math.min(count, PLACEHOLDER_CARDS) },
                (_, index) => index
            );
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video-list');
    },
});
