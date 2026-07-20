import template from './scalecommerce-vo-video-grid-config.html.twig';
import './scalecommerce-vo-video-grid-config.scss';

Shopware.Component.register('scalecommerce-vo-video-grid-config', {
    template,
    mixins: ['cms-element'],
    inject: ['scalecommerceVoApiService'],

    data() {
        return {
            libraries: [],
            posters: {},        // uuid -> poster url
            pickerIndex: null,  // null = closed, -1 = adding new, >=0 = editing that row
            pickerLibraryId: null,
        };
    },

    computed: {
        items() {
            return this.element.config.items.value ?? [];
        },
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
        presentationOptions() {
            return [
                { value: 'facade', label: this.$tc('scalecommerce-vo.videoGrid.presentationFacade') },
                { value: 'lightbox', label: this.$tc('scalecommerce-vo.videoGrid.presentationLightbox') },
                { value: 'direct', label: this.$tc('scalecommerce-vo.videoGrid.presentationDirect') },
            ];
        },
        playerModeOptions() {
            return [
                { value: 'native', label: this.$tc('scalecommerce-vo.videoGrid.playerNative') },
                { value: 'embed', label: this.$tc('scalecommerce-vo.videoGrid.playerEmbed') },
            ];
        },
        pickerSelectedUuid() {
            if (this.pickerIndex === null || this.pickerIndex < 0) return null;
            return this.items[this.pickerIndex]?.video ?? null;
        },
    },

    created() {
        this.initElementConfig('scalecommerce-vo-video-grid');
        if (!Array.isArray(this.element.config.items.value)) {
            this.element.config.items.value = [];
        }
        this.loadLibraries();
        this.loadPosters();
    },

    methods: {
        async loadLibraries() {
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
            } catch (error) {
                this.libraries = [];
            }
        },
        async loadPosters() {
            const uuids = this.items.map((item) => item.video).filter((uuid) => uuid && !this.posters[uuid]);
            await Promise.all(uuids.map(async (uuid) => {
                try {
                    const response = await this.scalecommerceVoApiService.getVideo(uuid);
                    const video = response.data ?? response;
                    this.posters = { ...this.posters, [uuid]: video?.poster_url || video?.thumbnail_url || null };
                } catch (error) {
                    this.posters = { ...this.posters, [uuid]: null };
                }
            }));
        },
        poster(uuid) {
            return uuid ? (this.posters[uuid] ?? null) : null;
        },
        updateItems(next) {
            this.element.config.items.value = next;
            this.$emit('element-update', this.element);
        },
        openAdd() {
            this.pickerIndex = -1;
            this.pickerLibraryId = this.libraries[0]?.id ?? null;
        },
        openPicker(index) {
            this.pickerIndex = index;
            this.pickerLibraryId = this.items[index]?.libraryId ?? this.libraries[0]?.id ?? null;
        },
        closePicker() {
            this.pickerIndex = null;
        },
        onPickerLibraryChange(libraryId) {
            this.pickerLibraryId = libraryId;
        },
        onPickVideo(uuid) {
            if (this.pickerIndex === -1) {
                this.updateItems([...this.items, { video: uuid, libraryId: this.pickerLibraryId, label: '' }]);
            } else if (this.pickerIndex !== null) {
                const next = this.items.map((item, i) => (i === this.pickerIndex
                    ? { ...item, video: uuid, libraryId: this.pickerLibraryId }
                    : item));
                this.updateItems(next);
            }
            this.loadPosters();
            this.closePicker();
        },
        onLabelInput(index, value) {
            this.updateItems(this.items.map((item, i) => (i === index ? { ...item, label: value } : item)));
        },
        moveUp(index) {
            if (index <= 0) return;
            const next = [...this.items];
            [next[index - 1], next[index]] = [next[index], next[index - 1]];
            this.updateItems(next);
        },
        moveDown(index) {
            if (index >= this.items.length - 1) return;
            const next = [...this.items];
            [next[index], next[index + 1]] = [next[index + 1], next[index]];
            this.updateItems(next);
        },
        removeItem(index) {
            this.updateItems(this.items.filter((_, i) => i !== index));
        },
        onPresentationChange(value) {
            this.element.config.presentation.value = value;
            this.$emit('element-update', this.element);
        },
        onPlayerModeChange(value) {
            this.element.config.playerMode.value = value;
            this.$emit('element-update', this.element);
        },
    },
});
