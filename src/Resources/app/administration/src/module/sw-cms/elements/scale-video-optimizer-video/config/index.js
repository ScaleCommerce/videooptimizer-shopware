import template from './scale-video-optimizer-cms-element-config.html.twig';

Shopware.Component.register('scale-video-optimizer-cms-element-config', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('scale-video-optimizer-video');
    },
    computed: {
        pickerValue() {
            return {
                libraryId: this.element.config.libraryId.value,
                videoUuid: this.element.config.videoUuid.value,
            };
        },
    },
    methods: {
        onPickerChange({ libraryId, videoUuid }) {
            this.element.config.libraryId.value = libraryId;
            this.element.config.videoUuid.value = videoUuid;
            this.$emit('element-update', this.element);
        },
    },
});
