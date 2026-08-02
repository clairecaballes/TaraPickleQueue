import axios from 'axios';

import { useAuthStore } from '../stores/auth';
import { pinia } from '../stores';

export const TOKEN_KEY = 'auth_token';

/**
 * The API client. The Sanctum bearer token is injected automatically and the
 * session is torn down when the token is rejected (401).
 */
const http = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401 && !error.config?.url?.includes('/auth/login')) {
            useAuthStore(pinia).clearSession();

            if (window.location.pathname !== '/login') {
                window.location.assign('/login');
            }
        }

        return Promise.reject(error);
    },
);

/** Pull a human-friendly message out of an axios error. */
export function errorMessage(error, fallback = 'Something went wrong.') {
    return error?.response?.data?.message || error?.message || fallback;
}

export default http;
