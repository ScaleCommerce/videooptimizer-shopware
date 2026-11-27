import { vi } from 'vitest';

/**
 * Loads a Shopware administration component definition without Shopware or Vue: the component
 * registers itself through the global `Shopware.Component.register()`, which is captured here.
 * The caller passes the import (`() => import('...')`) so the path resolves relative to the test.
 * `context()` builds a stand-in for `this` with the component's data and methods (bound to it),
 * so methods can be called directly; computed properties and the template are not evaluated.
 */
export async function loadComponent(importComponent) {
    let definition = null;
    globalThis.Shopware = {
        Component: {
            register: (name, config) => {
                definition = config;
            },
        },
        Mixin: { getByName: () => ({}) },
    };
    await importComponent();

    return {
        definition,
        context(overrides = {}) {
            const ctx = {
                ...definition.data.call({}),
                $emit: vi.fn(),
                $tc: (key) => key,
                $refs: {},
                createNotificationError: vi.fn(),
                createNotificationInfo: vi.fn(),
                createNotificationSuccess: vi.fn(),
                ...overrides,
            };
            for (const [name, method] of Object.entries(definition.methods)) {
                ctx[name] ??= method.bind(ctx);
            }
            return ctx;
        },
    };
}
