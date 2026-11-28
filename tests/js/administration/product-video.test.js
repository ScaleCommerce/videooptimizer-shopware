import { describe, expect, it } from 'vitest';
import {
    FIELD,
    FIELD_LIBRARY,
    productVideo,
    withProductVideo,
} from '../../../src/Resources/app/administration/src/helper/product-video.js';

describe('product video (#48)', () => {
    it('reads the video and library of a product', () => {
        expect(productVideo({ customFields: { [FIELD]: 'v1', [FIELD_LIBRARY]: 'l1' } })).toEqual({ uuid: 'v1', libraryId: 'l1' });
    });

    it.each([[null], [undefined], [{}], [{ customFields: null }], [{ customFields: { [FIELD]: 5, [FIELD_LIBRARY]: '' } }]])('has no video for %j', (product) => {
        expect(productVideo(product)).toEqual({ uuid: null, libraryId: null });
    });

    it('sets the video and keeps other custom fields', () => {
        expect(withProductVideo({ other: 1 }, { uuid: 'v1', libraryId: 'l1' })).toEqual({ other: 1, [FIELD]: 'v1', [FIELD_LIBRARY]: 'l1' });
        expect(withProductVideo(null, { uuid: 'v1' })).toEqual({ [FIELD]: 'v1', [FIELD_LIBRARY]: null });
    });

    it('clears with null so the save overwrites the stored value', () => {
        expect(withProductVideo({ other: 1, [FIELD]: 'v1' }, {})).toEqual({ other: 1, [FIELD]: null, [FIELD_LIBRARY]: null });
    });

    it('keeps the library when only the video is cleared (library change)', () => {
        expect(withProductVideo({}, { uuid: null, libraryId: 'l2' })).toEqual({ [FIELD]: null, [FIELD_LIBRARY]: 'l2' });
    });
});
