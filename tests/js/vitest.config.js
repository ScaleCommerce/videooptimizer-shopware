import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const here = (path) => fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
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
