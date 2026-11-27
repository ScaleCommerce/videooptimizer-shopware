// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = 'src/Resources/app/administration/src/module/scalecommerce-vo/';
const read = (p) => readFileSync(fileURLToPath(new URL(`../../../${root}${p}`, import.meta.url)), 'utf8');

describe('permanent quality note (#50)', () => {
    it('is rendered next to the quality field without a condition', () => {
        const twig = read('page/scalecommerce-vo-list/scalecommerce-vo-list.html.twig');
        const note = twig.match(/<p[^>]*scalecommerce-vo-list__quality-note[^>]*>[\s\S]*?<\/p>/);

        expect(note).not.toBeNull();
        expect(note[0]).not.toMatch(/v-if|v-show/);
        expect(note[0]).toContain('scalecommerce-vo.library.qualityApplyNote');
    });

    it.each(['en-GB', 'de-DE'])('has the %s snippet naming the forced reprocess', (locale) => {
        const snippets = JSON.parse(read(`snippet/${locale}.json`));

        expect(snippets['scalecommerce-vo'].library.qualityApplyNote).toMatch(/Re-encode all qualities|Alle Qualitäten neu kodieren/);
    });
});
