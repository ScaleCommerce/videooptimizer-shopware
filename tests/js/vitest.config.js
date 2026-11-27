import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const here = (path) => fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
    plugins: [
        {
            // Administration components import their template and styles; neither matters here.
            name: 'stub-component-assets',
            enforce: 'pre',
            resolveId(source) {
                return /\.(html\.twig|scss)$/.test(source) ? `\0stub-asset:${source}.js` : null;
            },
            load(id) {
                return id.startsWith('\0stub-asset:') ? 'export default ""' : null;
            },
        },
    ],
    resolve: {
        alias: {
            // Controllable stand-in for hls.js (the helper imports the light build on demand).
            'hls.js/dist/hls.light.mjs': here('./stubs/hls.js'),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['**/*.test.js'],
        restoreMocks: true,
    },
});
