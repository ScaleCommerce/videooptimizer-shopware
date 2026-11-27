// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const path = '../../../src/Resources/app/administration/src/component/scalecommerce-vo-video-gallery/scalecommerce-vo-video-gallery.html.twig';
const twig = readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');

describe('gallery selection mode (#52)', () => {
    const selectMode = twig.slice(twig.indexOf('scalecommerce_vo_video_gallery_select'));

    it('hides the native file input and labels it', () => {
        const input = selectMode.match(/<input[^>]*type="file"[\s\S]*?\/>/)[0];

        expect(input).toContain('scalecommerce-vo-gallery__file-input');
        expect(input).toContain(':aria-label="$tc(\'scalecommerce-vo.gallery.chooseFile\')"');
    });

    it('opens the input from a button and shows the file name', () => {
        expect(selectMode).toMatch(/<mt-button[^>]*@click="\$refs\.fileInput\.click\(\)"/);
        expect(selectMode).toContain('scalecommerce-vo-gallery__file-name');
    });

    it('exposes the full title and status text', () => {
        expect(selectMode).toContain(':title="video.title || video.uuid"');
        expect(selectMode).toContain(':title="statusLabel(video.status)"');
    });
});
