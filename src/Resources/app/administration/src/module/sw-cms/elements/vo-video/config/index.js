import template from './vo-cms-el-config-vo-video.html.twig';

Shopware.Component.register('vo-cms-el-config-vo-video', {
    template,
    mixins: ['cms-element'],
    created() {
        this.initElementConfig('vo-video');
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
