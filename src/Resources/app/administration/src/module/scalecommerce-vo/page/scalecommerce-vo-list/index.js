import template from './scalecommerce-vo-list.html.twig';
import './scalecommerce-vo-list.scss';

const { Component, Mixin } = Shopware;

// Formats a byte count as a human-readable size (e.g. 1536 -> "1.5 KB").
function formatBytes(bytes) {
    const value = Number(bytes) || 0;
    if (value <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
    const scaled = value / (1024 ** exponent);
    return `${exponent === 0 ? scaled : scaled.toFixed(1)} ${units[exponent]}`;
}

Component.register('scalecommerce-vo-list', {
    template,

    inject: ['scalecommerceVoApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            libraries: [],
            selectedLibraryId: null,
            encodings: { codecs: [], resolutions: [] },
            mode: 'edit',
            formName: '',
            formDescription: '',
            selectedCodecs: [],
            selectedResolutions: [],
            isSaving: false,
            confirmDeleteLibrary: false,
        };
    },

    computed: {
        libraryOptions() {
            return this.libraries.map((library) => ({ value: library.id, label: library.name }));
        },
        activeLibrary() {
            if (this.mode === 'create' || !this.selectedLibraryId) {
                return null;
            }
            return this.libraries.find((library) => library.id === this.selectedLibraryId) || null;
        },
        mediaManaged() {
            const library = this.activeLibrary;
            return !library || library.media_managed !== false;
        },
        canSave() {
            return !!this.formName.trim() && !this.isSaving;
        },
        storageLabel() {
            return this.activeLibrary ? formatBytes(this.activeLibrary.storage_usage) : null;
        },
        createdLabel() {
            const library = this.activeLibrary;
            if (!library || !library.created_at) {
                return null;
            }
            const date = new Date(library.created_at);
            return Number.isNaN(date.getTime()) ? library.created_at : date.toLocaleDateString();
        },
        galleryLibraryId() {
            return this.mode === 'create' ? null : this.selectedLibraryId;
        },
    },

    created() {
        this._init();
    },

    methods: {
        async _init() {
            await this.loadLibraries();
            this._loadForm();
            this.loadEncodings();
        },

        async loadLibraries() {
            this.isLoading = true;
            try {
                const response = await this.scalecommerceVoApiService.getLibraries();
                this.libraries = response.data ?? response;
                if (this.libraries.length && !this.selectedLibraryId) {
                    this.selectedLibraryId = this.libraries[0].id;
                }
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isLoading = false;
            }
        },

        async loadEncodings() {
            try {
                const response = await this.scalecommerceVoApiService.getEncodings();
                const data = response.data ?? response;
                this.encodings = { codecs: data.codecs ?? [], resolutions: data.resolutions ?? [] };
            } catch (error) {
                // Best effort: the pickers still work from the stored keys.
                console.warn('[VideoOptimizer] failed to load encodings', error);
            }
        },

        _splitKeys(value) {
            return (value || '').split(',').map((key) => key.trim()).filter(Boolean);
        },

        _loadForm() {
            const library = this.activeLibrary;
            if (!library) {
                this.formName = '';
                this.formDescription = '';
                this.selectedCodecs = [];
                this.selectedResolutions = [];
                return;
            }
            this.formName = library.name || '';
            this.formDescription = library.description || '';
            this.selectedCodecs = this._splitKeys(library.codec);
            this.selectedResolutions = this._splitKeys(library.resolutions);
        },

        onSelectLibrary(id) {
            this.selectedLibraryId = id;
            this.mode = 'edit';
            this._loadForm();
        },

        onNewLibrary() {
            this.mode = 'create';
            this.formName = '';
            this.formDescription = '';
            this.selectedCodecs = [];
            this.selectedResolutions = [];
        },

        onCancelCreate() {
            this.mode = 'edit';
            this._loadForm();
        },

        toggleCodec(key) {
            this.selectedCodecs = this.selectedCodecs.includes(key)
                ? this.selectedCodecs.filter((current) => current !== key)
                : [...this.selectedCodecs, key];
        },

        toggleResolution(key) {
            this.selectedResolutions = this.selectedResolutions.includes(key)
                ? this.selectedResolutions.filter((current) => current !== key)
                : [...this.selectedResolutions, key];
        },

        optionDisabled(option) {
            return !this.mediaManaged || option.available === false;
        },

        optionLabel(option) {
            return option.available === false
                ? `${option.label} (${this.$tc('scalecommerce-vo.library.addon')})`
                : option.label;
        },

        // Joins selected keys in the canonical /encodings order; unknown stored keys are appended.
        _ladderValue(group, selected) {
            const order = (this.encodings[group] || []).map((option) => option.key);
            const inOrder = order.filter((key) => selected.includes(key));
            const extras = selected.filter((key) => !order.includes(key));
            return [...inOrder, ...extras].join(',');
        },

        async onSave() {
            const name = this.formName.trim();
            if (!name) {
                return;
            }
            this.isSaving = true;
            try {
                const codec = this._ladderValue('codecs', this.selectedCodecs);
                const resolutions = this._ladderValue('resolutions', this.selectedResolutions);

                if (this.mode === 'create') {
                    const payload = { name };
                    if (this.formDescription.trim()) {
                        payload.description = this.formDescription.trim();
                    }
                    if (codec) {
                        payload.codec = codec;
                    }
                    if (resolutions) {
                        payload.resolutions = resolutions;
                    }
                    const response = await this.scalecommerceVoApiService.createLibrary(payload);
                    const created = response.data ?? response;
                    this.mode = 'edit';
                    await this.loadLibraries();
                    if (created && created.id) {
                        this.selectedLibraryId = created.id;
                    }
                    this._loadForm();
                    this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.library.created') });
                } else {
                    await this.scalecommerceVoApiService.updateLibrary(this.selectedLibraryId, {
                        name,
                        description: this.formDescription.trim(),
                        codec,
                        resolutions,
                    });
                    await this.loadLibraries();
                    this._loadForm();
                    this.createNotificationSuccess({ message: this.$tc('scalecommerce-vo.library.saved') });
                }
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            } finally {
                this.isSaving = false;
            }
        },

        onDeleteLibraryClick() {
            this.confirmDeleteLibrary = true;
        },

        async onConfirmDeleteLibrary() {
            this.confirmDeleteLibrary = false;
            if (!this.selectedLibraryId) {
                return;
            }
            try {
                await this.scalecommerceVoApiService.deleteLibrary(this.selectedLibraryId);
                this.selectedLibraryId = null;
                this.mode = 'edit';
                await this.loadLibraries();
                this._loadForm();
            } catch (error) {
                this.createNotificationError({ message: this._errorText(error) });
            }
        },

        _errorText(error) {
            return error?.response?.data?.errors?.[0]?.detail ?? this.$tc('scalecommerce-vo.list.genericError');
        },
    },
});
