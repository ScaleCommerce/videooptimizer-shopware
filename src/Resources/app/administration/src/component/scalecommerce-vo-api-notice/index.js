import template from './scalecommerce-vo-api-notice.html.twig';
import { apiErrorKind } from '../../helper/api-error';

/**
 * Explains why VideoOptimizer data could not be loaded (missing/rejected token, missing
 * permission, API unavailable) instead of leaving an empty selection (issue #11).
 */
Shopware.Component.register('scalecommerce-vo-api-notice', {
    template,

    inject: ['acl'],

    props: {
        error: {
            type: [Object, Error],
            required: false,
            default: null,
        },
    },

    computed: {
        info() {
            return this.error ? apiErrorKind(this.error) : null;
        },
        message() {
            if (!this.info) {
                return '';
            }
            const text = this.$tc(
                `scalecommerce-vo.apiNotice.${this.info.kind}`
            );
            return this.info.detail ? `${text} ${this.info.detail}` : text;
        },
        showConfigLink() {
            return (
                !!this.info &&
                ['tokenMissing', 'invalidBaseUrl', 'tokenRejected'].includes(
                    this.info.kind
                ) &&
                this.acl.can('system.plugin_maintain')
            );
        },
        configRoute() {
            return {
                name: 'sw.extension.config',
                params: { namespace: 'ScaleVideoOptimizer' },
            };
        },
    },
});
