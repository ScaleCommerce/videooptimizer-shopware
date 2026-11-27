import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { loadComponent } from './support/load-component.js';

describe('scalecommerce-vo-webhook (#39)', () => {
    let webhook;
    beforeAll(async () => {
        webhook = await loadComponent(() => import('../../../src/Resources/app/administration/src/component/scalecommerce-vo-webhook/index.js'));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('loads the receiver info', async () => {
        const service = { getWebhookInfo: vi.fn().mockResolvedValue({ data: { url: 'https://shop/x', configured: true, lastDelivery: null } }) };
        const ctx = webhook.context({ scalecommerceVoApiService: service });

        await ctx.load();

        expect(ctx.info.url).toBe('https://shop/x');
        expect(ctx.error).toBeNull();
    });

    it('keeps the error for the notice when the info cannot be loaded', async () => {
        const failure = new Error('x');
        const ctx = webhook.context({ scalecommerceVoApiService: { getWebhookInfo: vi.fn().mockRejectedValue(failure) } });

        await ctx.load();

        expect(ctx.error).toBe(failure);
    });

    it('copies the URL and confirms', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const ctx = webhook.context({ info: { url: 'https://shop/x' } });

        await ctx.onCopy();

        expect(writeText).toHaveBeenCalledWith('https://shop/x');
        expect(ctx.createNotificationSuccess).toHaveBeenCalledOnce();
    });

    it('asks for a manual copy when the clipboard is not available', async () => {
        vi.stubGlobal('navigator', {});
        const ctx = webhook.context({ info: { url: 'https://shop/x' } });

        await ctx.onCopy();

        expect(ctx.createNotificationError).toHaveBeenCalledOnce();
    });
});
