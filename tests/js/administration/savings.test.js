import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';
import { formatBytes, formatPercent, rangeForPreset } from '../../../src/Resources/app/administration/src/helper/savings.js';

describe('savings helpers (#38)', () => {
    it.each([[0, '0 B'], [1023, '1,023 B'], [1536, '1.5 KB'], [5 * 1024 ** 3, '5.0 GB'], [150 * 1024 ** 2, '150 MB']])('formats %i bytes as %s', (bytes, expected) => {
        expect(formatBytes(bytes, 'en')).toBe(expected);
    });

    it('does not format invalid sizes', () => {
        expect(formatBytes(null)).toBe('-');
        expect(formatBytes(-1)).toBe('-');
        expect(formatBytes(undefined)).toBe('-');
    });

    it('formats percentages', () => {
        expect(formatPercent(84.53, 'en')).toBe('84.5 %');
        expect(formatPercent(undefined)).toBe('-');
    });

    it('builds whole UTC day ranges ending today', () => {
        const now = new Date('2026-11-27T23:30:00Z');
        expect(rangeForPreset('7d', now)).toEqual({ from: '2026-11-21', to: '2026-11-27' });
        expect(rangeForPreset('30d', now)).toEqual({ from: '2026-10-29', to: '2026-11-27' });
        expect(rangeForPreset('month', now)).toEqual({ from: '2026-11-01', to: '2026-11-27' });
        expect(rangeForPreset('all', now)).toBeNull();
    });
});

describe('scalecommerce-vo-savings (#38)', () => {
    let savings;
    beforeAll(async () => {
        savings = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-savings/index.js'));
    });

    it('asks for the period and, when chosen, the library', async () => {
        const service = { getSavings: vi.fn().mockResolvedValue({ data: { traffic_available: true } }) };
        const ctx = savings.context({ scalecommerceVoApiService: service, preset: 'all', onlyLibrary: true, libraryId: 'lib-1' });

        await ctx.load();

        expect(service.getSavings).toHaveBeenCalledWith({ libraryId: 'lib-1' });
        expect(ctx.savings).toEqual({ traffic_available: true });
        expect(ctx.isLoading).toBe(false);
    });

    it('keeps the error for the notice', async () => {
        const failure = new Error('down');
        const service = { getSavings: vi.fn().mockRejectedValue(failure) };
        const ctx = savings.context({ scalecommerceVoApiService: service, preset: 'all', onlyLibrary: false });

        await ctx.load();

        expect(ctx.error).toBe(failure);
        expect(ctx.savings).toBeNull();
    });

    it('ignores a slower older response', async () => {
        let resolveFirst;
        const service = { getSavings: vi.fn()
            .mockReturnValueOnce(new Promise((resolve) => { resolveFirst = resolve; }))
            .mockResolvedValueOnce({ data: { traffic_available: false, tag: 'second' } }) };
        const ctx = savings.context({ scalecommerceVoApiService: service, preset: 'all', onlyLibrary: false });

        const first = ctx.load();
        await ctx.load();
        resolveFirst({ data: { tag: 'first' } });
        await first;

        expect(ctx.savings.tag).toBe('second');
    });
});
