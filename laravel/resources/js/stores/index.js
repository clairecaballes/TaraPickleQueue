import { createPinia } from 'pinia';

/**
 * Shared Pinia instance — created here so it can be imported from plain
 * modules (e.g. the http client) without circular imports.
 */
export const pinia = createPinia();
