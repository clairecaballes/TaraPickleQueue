import { defineStore } from 'pinia';

import http, { TOKEN_KEY } from '../api/http';
import { refreshEchoAuth } from '../echo';

function normalizeUser(user) {
    const payload = user?.data ?? user;

    if (!payload || typeof payload !== 'object') {
        return null;
    }

    return {
        ...(payload ?? {}),
        is_admin: payload?.is_admin ?? payload?.isAdmin ?? false,
    };
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        isAdmin: (state) => Boolean(state.user?.is_admin || state.user?.isAdmin || state.user?.role === 'admin'),
    },

    actions: {
        /**
         * Restore the session from the stored token before the first route
         * navigation so the router guards see the real user.
         */
        async ensureSession() {
            if (!localStorage.getItem(TOKEN_KEY)) {
                this.initialized = true;

                return;
            }

            this.loading = true;

            try {
                const { data } = await http.get('/auth/me');
                this.user = normalizeUser(data);
            } catch {
                this.clearSession();
            } finally {
                this.loading = false;
                this.initialized = true;
            }
        },

        async login(credentials) {
            const { data } = await http.post('/auth/login', credentials);

            const user = normalizeUser(data?.user ?? data);

            this.setSession(data.access_token, user);

            return data;
        },

        async register(payload) {
            const { data } = await http.post('/auth/register', payload);

            this.setSession(data.access_token, data.user ?? data ?? null);

            return data;
        },

        setSession(token, user) {
            localStorage.setItem(TOKEN_KEY, token);
            this.user = normalizeUser(user);
            refreshEchoAuth(token);
        },

        async logout() {
            try {
                await http.post('/auth/logout');
            } catch {
                // Token may already be revoked — local teardown below is enough.
            }

            this.clearSession();
        },

        clearSession() {
            localStorage.removeItem(TOKEN_KEY);
            this.user = null;
            refreshEchoAuth(null);
        },
    },
});
