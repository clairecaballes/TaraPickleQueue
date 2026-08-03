import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;

/**
 * Reverb broadcasts over the Pusher protocol. The backend publishes all
 * real-time events (QueueUpdated, CourtCalled, MatchEnded) on private
 * `court.{id}` channels — Echo fetches a signed authorization from
 * `/broadcasting/auth` (guarded by auth:sanctum) before subscribing.
 */
if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || import.meta.env.VITE_PUSHER_HOST,
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
} else {
    console.warn('Real-time broadcasting is disabled: VITE_REVERB_APP_KEY is missing.');
    window.Echo = null;
}

/**
 * Keep Echo's authorization header in sync with the session.
 */
export function refreshEchoAuth(token) {
    const headers = window.Echo?.connector?.options?.auth?.headers;

    if (headers) {
        headers.Authorization = token ? `Bearer ${token}` : '';
    }
}