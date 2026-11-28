import template from './scalecommerce-vo-chapters-component.html.twig';
import './scalecommerce-vo-chapters-component.scss';
import elementVideo from '../../../../../mixin/scalecommerce-vo-element-video';
import layout from '../../../../../mixin/scalecommerce-vo-layout';

Shopware.Component.register('scalecommerce-vo-chapters-component', {
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
        headline() {
            return this.element.config?.headline?.value || '';
        },
        sceneCount() {
            const count = Number(this.element.config?.sceneCount?.value);
            return Number.isFinite(count)
                ? Math.max(3, Math.min(10, Math.round(count)))
                : 5;
        },
        sceneTitles() {
            return String(this.element.config?.sceneTitles?.value ?? '').split(
                /\r?\n/
            );
        },
        scenes() {
            return Array.from({ length: this.sceneCount }, (_, index) => ({
                index,
                label:
                    this.sceneTitles[index]?.trim() ||
                    this.$tc('scalecommerce-vo.chapters.scene', 0, {
                        number: index + 1,
                    }),
            }));
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-chapters');
    },
});
