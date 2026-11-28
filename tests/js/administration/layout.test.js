import { describe, expect, it } from 'vitest';
import { formatAspect } from '../../../src/Resources/app/administration/src/helper/layout.js';

describe('format option (#47)', () => {
    it.each([
        ['9-16', '9 / 16'],
        ['21-9', '21 / 9'],
        ['original', '16 / 9'],
        [undefined, '16 / 9'],
        ['bogus', '16 / 9'],
    ])('previews %s as %s', (format, aspect) => {
        expect(formatAspect(format)).toBe(aspect);
    });
});
