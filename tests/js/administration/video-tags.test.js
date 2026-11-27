import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';
import {
    addTag,
    metadataPatch,
    metadataProblem,
    metadataRows,
    normalizeTag,
    removeTag,
    sameTags,
} from '../../../src/Resources/app/administration/src/helper/video-tags.js';

describe('tags (#37)', () => {
    it.each([['  promo ', 'promo'], ['', null], ['a,b', null], ['x'.repeat(51), null], ['x'.repeat(50), 'x'.repeat(50)]])('normalizes %j to %j', (input, expected) => {
        expect(normalizeTag(input)).toBe(expected);
    });

    it('does not add a tag twice, regardless of case', () => {
        const tags = ['Promo'];
        expect(addTag(tags, 'promo')).toBe(tags);
        expect(addTag(tags, 'sale')).toEqual(['Promo', 'sale']);
        expect(addTag(tags, 'a,b')).toBe(tags);
    });

    it('stops at 20 tags', () => {
        const tags = Array.from({ length: 20 }, (_, i) => `t${i}`);
        expect(addTag(tags, 'more')).toBe(tags);
    });

    it('removes and compares tag lists', () => {
        expect(removeTag(['a', 'b'], 'a')).toEqual(['b']);
        expect(sameTags(['A', 'b'], ['B', 'a'])).toBe(true);
        expect(sameTags(['a'], ['a', 'b'])).toBe(false);
    });
});

describe('metadata (#37)', () => {
    it('turns the metadata object into rows and back into a patch', () => {
        const original = { a: '1', b: '2', c: '3' };
        expect(metadataRows(original)).toEqual([{ key: 'a', value: '1' }, { key: 'b', value: '2' }, { key: 'c', value: '3' }]);
        const rows = [{ key: 'a', value: '1' }, { key: 'b', value: ' changed ' }, { key: 'd', value: 'new' }, { key: '', value: '' }];
        // c was removed, b changed, d is new, a is untouched
        expect(metadataPatch(original, rows)).toEqual({ b: 'changed', c: null, d: 'new' });
    });

    it('has no patch without changes and removes keys with an empty value', () => {
        expect(metadataPatch({ a: '1' }, [{ key: 'a', value: '1' }])).toBeNull();
        expect(metadataPatch({ a: '1' }, [{ key: 'a', value: ' ' }])).toEqual({ a: null });
        expect(metadataPatch(undefined, [])).toBeNull();
    });

    it.each([
        [[{ key: 'Upper', value: 'x' }], 'keyInvalid'],
        [[{ key: '', value: 'x' }], 'keyInvalid'],
        [[{ key: 'a', value: '1' }, { key: 'a', value: '2' }], 'keyDuplicate'],
        [[{ key: 'a', value: 'x'.repeat(65) }], 'valueTooLong'],
        [Array.from({ length: 11 }, (_, i) => ({ key: `k${i}`, value: 'v' })), 'tooMany'],
        [[{ key: 'a', value: '1' }, { key: '', value: '' }], null],
        [[...Array.from({ length: 10 }, (_, i) => ({ key: `k${i}`, value: 'v' })), { key: 'gone', value: '' }], null],
    ])('finds the problem of %j', (rows, expected) => {
        expect(metadataProblem(rows)).toBe(expected);
    });
});

describe('scalecommerce-vo-video-tags (#37)', () => {
    let tags;
    beforeAll(async () => {
        tags = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-tags/index.js'));
    });

    it('sends only what changed and reports the saved video', async () => {
        const service = { updateVideo: vi.fn().mockResolvedValue({ data: { uuid: 'v', tags: ['a', 'b'] } }) };
        const ctx = tags.context({
            scalecommerceVoApiService: service, video: { uuid: 'v', tags: ['a'], metadata: { x: '1' } }, tags: ['a', 'b'],
            canSave: true, tagsChanged: true, patch: null, loadTags: vi.fn(),
        });

        await ctx.onSave();

        expect(service.updateVideo).toHaveBeenCalledWith('v', { tags: ['a', 'b'] });
        expect(ctx.$emit).toHaveBeenCalledWith('saved', { uuid: 'v', tags: ['a', 'b'] });
        expect(ctx.isSaving).toBe(false);
    });

    it('sends the metadata patch with removed keys as null', async () => {
        const service = { updateVideo: vi.fn().mockResolvedValue({ data: {} }) };
        const ctx = tags.context({
            scalecommerceVoApiService: service, video: { uuid: 'v' }, tags: [],
            canSave: true, tagsChanged: false, patch: { x: null }, loadTags: vi.fn(),
        });

        await ctx.onSave();

        expect(service.updateVideo).toHaveBeenCalledWith('v', { metadata: { x: null } });
    });

    it('shows the API error and stays editable', async () => {
        const service = { updateVideo: vi.fn().mockRejectedValue({ response: { data: { errors: [{ detail: 'Invalid tags' }] } } }) };
        const ctx = tags.context({ scalecommerceVoApiService: service, video: { uuid: 'v' }, tags: ['a'], canSave: true, tagsChanged: true, patch: null });

        await ctx.onSave();

        expect(ctx.createNotificationError).toHaveBeenCalledWith({ message: 'Invalid tags' });
        expect(ctx.isSaving).toBe(false);
    });

    it('does nothing when saving is not allowed', async () => {
        const service = { updateVideo: vi.fn() };
        await tags.context({ scalecommerceVoApiService: service, canSave: false }).onSave();
        expect(service.updateVideo).not.toHaveBeenCalled();
    });
});

describe('scalecommerce-vo-video-filter (#37)', () => {
    let filter;
    beforeAll(async () => {
        filter = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-filter/index.js'));
    });

    it('emits the chosen tags and a valid metadata filter', () => {
        const ctx = filter.context({ tags: ['a'], metadata: { cms_id: '42' } });
        ctx.emitChange();
        expect(ctx.$emit).toHaveBeenCalledWith('change', { tags: ['a'], metadata: { cms_id: '42' } });
    });

    it('clears all filters', () => {
        const ctx = filter.context({ tags: ['a'], metadataKey: 'k', metadataValue: 'v', metadata: {} });
        ctx.onClear();
        expect(ctx.tags).toEqual([]);
        expect(ctx.metadataKey).toBe('');
        expect(ctx.$emit).toHaveBeenCalledWith('change', { tags: [], metadata: {} });
    });
});
