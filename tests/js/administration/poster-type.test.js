import { describe, expect, it } from 'vitest';
import { resolvePosterContentType } from '../../../src/Resources/app/administration/src/helper/poster-type.js';

describe('resolvePosterContentType (#30)', () => {
    it.each([
        ['image/jpeg', {}, 'image/jpeg'],
        ['image/webp', { mimeType: 'image/png' }, 'image/webp'],
        ['IMAGE/PNG; charset=binary', {}, 'image/png'],
        ['application/octet-stream', { mimeType: 'image/jpeg' }, 'image/jpeg'],
        ['application/octet-stream', { mimeType: 'image/jpg' }, 'image/jpeg'],
        ['', { mimeType: 'image/webp' }, 'image/webp'],
        ['binary/octet-stream', { fileExtension: 'PNG' }, 'image/png'],
        ['application/octet-stream', { fileExtension: 'jpg' }, 'image/jpeg'],
        ['application/octet-stream', { fileName: 'poster.final.webp' }, 'image/webp'],
        ['application/octet-stream', { url: 'https://cdn/media/ab/cd/poster.jpeg?ts=1' }, 'image/jpeg'],
        ['application/octet-stream', { mimeType: 'application/octet-stream', fileExtension: 'jpg' }, 'image/jpeg'],
    ])('%s with %j resolves to %s', (blobType, media, expected) => {
        expect(resolvePosterContentType(blobType, media)).toBe(expected);
    });

    it.each([
        ['application/octet-stream', { mimeType: 'video/mp4', fileExtension: 'mp4' }],
        ['application/octet-stream', { mimeType: 'image/gif', fileExtension: 'gif' }],
        ['application/octet-stream', {}],
        ['application/octet-stream', undefined],
    ])('a generic type with %j gives no allowed type', (blobType, media) => {
        expect(['image/jpeg', 'image/png', 'image/webp']).not.toContain(resolvePosterContentType(blobType, media));
    });

    it('keeps a real but unsupported type so the caller rejects it', () => {
        expect(resolvePosterContentType('image/gif', { mimeType: 'image/png' })).toBe('image/gif');
    });
});
