import Echo from 'laravel-echo';

import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Reverb broadcasts over the Pusher protocol. The backend publishes all
 * real-time events (QueueUpdated, CourtCalled, MatchEnded) on private
 * `court.{id}` channels — Echo fetches a signed authorization from
 * `/broadcasting/auth` (guarded by auth:sanctum) before subscribing.
 *
 * The Sanctum bearer token must be stored under `auth_token` when the user
 * logs in (the API returns it as `access_token`).
 */
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
        },
    },
});

/**
 * Keep Echo's authorization header in sync with the session.
 *
 * echo.js captures the token at import time (before the user logs in on the
 * SPA), so after login/register/logout we must refresh the header — otherwise
 * private-channel authorization would silently fail until a page reload.
 */
export function refreshEchoAuth(token) {
    const headers = window.Echo?.connector?.options?.auth?.headers;

    if (headers) {
        headers.Authorization = token ? `Bearer ${token}` : '';
    }
}

/**
 * Live court subscription — call this once per court you are displaying.
 *
 *   window.Echo.private(`court.${courtId}`)
 *       .listen('QueueUpdated', (e) => renderQueue(e))
 *       .listen('CourtCalled', (e) => summonPlayers(e))
 *       .listen('MatchEnded', (e) => freeCourt(e));
 */
