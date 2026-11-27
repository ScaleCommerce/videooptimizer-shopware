import template from './scalecommerce-vo-savings.html.twig';
import './scalecommerce-vo-savings.scss';
import {
    formatBytes,
    formatPercent,
    rangeForPreset,
} from '../../helper/savings';

const PRESETS = ['all', '7d', '30d', '90d', 'month'];

/**
 * What VideoOptimizer saves compared to serving the original files (issue #38): the file-size
 * reduction of the encoded versions and the estimated bandwidth saved, for the organization or
 * the selected library and an optional period.
 */
Shopware.Component.register('scalecommerce-vo-savings', {
    template,

    inject: ['scalecommerceVoApiService'],

    props: {
        libraryId: { type: String, required: false, default: null },
    },

    data() {
        return {
            savings: null,
            error: null,
            isLoading: false,
            preset: '30d',
            onlyLibrary: false,
        };
    },

    computed: {
        presetOptions() {
            return PRESETS.map((value) => ({
                value,
                label: this.$tc(`scalecommerce-vo.savings.preset_${value}`),
            }));
        },
        scopeOptions() {
            return [
                {
                    value: false,
                    label: this.$tc(
                        'scalecommerce-vo.savings.scopeOrganization'
                    ),
                },
                {
                    value: true,
                    label: this.$tc('scalecommerce-vo.savings.scopeLibrary'),
                },
            ];
        },
        compression() {
            return this.savings?.compression ?? null;
        },
        // The period if one is chosen, else everything since tracking began.
        traffic() {
            if (!this.savings?.traffic_available) {
                return null;
            }
            return this.preset === 'all'
                ? this.savings.all_time
                : (this.savings.range ?? null);
        },
        rows() {
            if (!this.traffic) {
                return [];
            }
            return [
                ['delivered', formatBytes(this.traffic.delivered_bytes)],
                [
                    'estimatedOriginal',
                    formatBytes(this.traffic.estimated_original_bytes),
                ],
                ['saved', formatBytes(this.traffic.saved_bytes)],
                ['savedPercent', formatPercent(this.traffic.saved_percent)],
            ];
        },
        compressionRows() {
            if (!this.compression) {
                return [];
            }
            return [
                ['original', formatBytes(this.compression.original_bytes)],
                ['encoded', formatBytes(this.compression.encoded_bytes)],
                ['reduced', formatPercent(this.compression.reduced_percent)],
            ];
        },
    },

    watch: {
        libraryId() {
            this.load();
        },
        preset() {
            this.load();
        },
        onlyLibrary() {
            this.load();
        },
    },

    created() {
        this.load();
    },

    beforeUnmount() {
        this._unmounted = true;
    },

    methods: {
        async load() {
            const request = (this._request = (this._request ?? 0) + 1);
            this.isLoading = true;
            this.error = null;
            try {
                const scope = {
                    ...(rangeForPreset(this.preset) ?? {}),
                    libraryId:
                        this.onlyLibrary && this.libraryId
                            ? this.libraryId
                            : null,
                };
                const response =
                    await this.scalecommerceVoApiService.getSavings(scope);
                if (this._unmounted || request !== this._request) {
                    return;
                }
                this.savings = response.data ?? response;
            } catch (error) {
                if (this._unmounted || request !== this._request) {
                    return;
                }
                this.savings = null;
                this.error = error;
            } finally {
                if (!this._unmounted && request === this._request) {
                    this.isLoading = false;
                }
            }
        },
    },
});
