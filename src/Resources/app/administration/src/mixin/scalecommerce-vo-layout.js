/**
 * Options of the surface "format" (fixed ratio, the video fills it) for the CMS element
 * configurations. Plain-object mixin, like scalecommerce-vo-library-loader.
 */
import { formatAspect } from '../helper/layout';

export default {
    computed: {
        // Editor preview ratio of the chosen format (the 16:9 default stands for the original ratio).
        formatAspect() {
            return formatAspect(this.element?.config?.format?.value);
        },
        formatOptions() {
            return [
                ['original', 'formatOriginal'],
                ['16-9', 'format169'],
                ['9-16', 'format916'],
                ['1-1', 'format11'],
                ['4-5', 'format45'],
                ['21-9', 'format219'],
            ].map(([value, key]) => ({
                value,
                label: this.$tc(`scalecommerce-vo.cms.${key}`),
            }));
        },
    },
};
