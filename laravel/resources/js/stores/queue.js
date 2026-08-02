import { defineStore } from 'pinia';

import http from '../api/http';
import { playSummon, vibrate } from '../utils/notify';
import { useAuthStore } from './auth';
import { pinia } from './index';

/** Actions whose payload is structural — refetch the line to stay consistent. */
const STRUCTURAL_ACTIONS = new Set([
    'joined',
    'left',
    'skipped',
    'called',
    'confirmed',
    'completed',
    'expired',
]);

/** Current user id (null when signed out). */
function authId() {
    return useAuthStore(pinia).user?.id ?? null;
}

export const useQueueStore = defineStore('queue', {
    state: () => ({
        courts: [],
        groups: [],
        queue: [],
        activeCourtId: null,
        loading: false,
        channel: null,
    }),

    getters: {
        activeCourt(state) {
            return state.courts.find((court) => court.id === state.activeCourtId) ?? null;
        },

        waitingEntries(state) {
            return state.queue
                .filter((entry) => entry.status === 'waiting')
                .sort((a, b) => (a.position ?? Infinity) - (b.position ?? Infinity));
        },

        calledEntries(state) {
            return state.queue.filter((entry) => entry.status === 'called');
        },

        onCourtEntries(state) {
            return state.queue.filter((entry) => entry.status === 'on_court');
        },

        /** Queue entries the current user is part of (solo or via a group). */
        myEntries(state) {
            return state.queue.filter((entry) => entry.players.some((player) => player.id === authId()));
        },
    },

    actions: {
        async fetchCourts() {
            const { data } = await http.get('/courts');
            this.courts = Array.isArray(data?.data) ? data.data : [];

            if (!this.activeCourtId && this.courts.length) {
                this.activeCourtId = this.courts[0].id;
                this.subscribe();
                await this.fetchQueue();
            }
        },

        async fetchGroups() {
            const { data } = await http.get('/groups');
            this.groups = Array.isArray(data?.data) ? data.data : [];
        },

        async setActiveCourt(courtId) {
            if (courtId === this.activeCourtId) {
                return;
            }

            this.activeCourtId = courtId;
            this.subscribe();
            await this.fetchQueue();
        },

        /** Normalized queue entries (players + label flattened for rendering). */
        async fetchQueue() {
            if (!this.activeCourtId) {
                return;
            }

            const { data } = await http.get(`/courts/${this.activeCourtId}/queue`);

            this.queue = Array.isArray(data?.data)
                ? data.data.map((entry) => ({
                    ...entry,
                    players: entry.user ? [entry.user] : (entry.group?.players ?? []),
                    label: entry.group ? entry.group.name : (entry.user?.name ?? 'Unknown'),
                }))
                : [];
        },

        /* ------------------------------------------------------------------
         * Real-time (Echo) — subscribe to the active court's private channel.
         * ------------------------------------------------------------------ */

        subscribe() {
            if (!window.Echo || !this.activeCourtId) {
                return;
            }

            if (this.channel) {
                this.channel.unsubscribe();
                this.channel = null;
            }

            const channel = window.Echo.private(`court.${this.activeCourtId}`);

            channel
                .listen('QueueUpdated', ({ court_id, action, positions }) => {
                    if (court_id !== this.activeCourtId) {
                        return;
                    }

                    if (STRUCTURAL_ACTIONS.has(action)) {
                        this.fetchQueue();
                        this.fetchCourts();

                        return;
                    }

                    // Position-only update ('moved') — apply locally, no round trip.
                    if (positions) {
                        const byId = new Map(positions);

                        this.queue = this.queue
                            .map((entry) => {
                                if (entry.status === 'waiting' && byId.has(entry.id)) {
                                    return { ...entry, position: byId.get(entry.id) };
                                }

                                return entry;
                            })
                            .sort((a, b) => (a.position ?? Infinity) - (b.position ?? Infinity));
                    }
                })
                .listen('CourtCalled', ({ court_id, entries }) => {
                    if (court_id === this.activeCourtId) {
                        this.fetchQueue();
                    }

                    this.fetchCourts();

                    // "Court ready for you!" — audio + haptic feedback.
                    const calledMe = entries.some((entry) =>
                        entry.players.some((player) => player.id === authId()),
                    );

                    if (calledMe) {
                        playSummon();
                        vibrate([250, 120, 250]);
                    }
                })
                .listen('MatchEnded', () => {
                    this.fetchQueue();
                    this.fetchCourts();
                });

            this.channel = channel;
        },

        /* ------------------------------------------------------------------
         * Player actions
         * ------------------------------------------------------------------ */

        async join(courtId, { group_id } = {}) {
            await http.post(`/courts/${courtId}/queue`, { group_id });

            await this.setActiveCourt(courtId);
            await this.fetchQueue();
            await this.fetchCourts();
        },

        async leave(entryId) {
            await http.delete(`/queue/${entryId}`);

            await this.fetchQueue();
            await this.fetchCourts();
        },

        /* ------------------------------------------------------------------
         * Organizer actions (can:manage-court)
         * ------------------------------------------------------------------ */

        async callNext(courtId) {
            await http.post(`/courts/${courtId}/next-up`);

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async confirmCall(courtId) {
            await http.post(`/courts/${courtId}/confirm-call`);

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async completeMatch(matchId, scoreA, scoreB) {
            await http.post(`/matches/${matchId}/complete`, { score_a: scoreA, score_b: scoreB });

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async skipEntry(entryId) {
            await http.post(`/queue/${entryId}/skip`);

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async addPlayer(courtId, userId) {
            await http.post(`/admin/courts/${courtId}/queue/add`, { user_id: userId });

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async reorderQueue(courtId, orderedIds) {
            await http.patch(`/admin/courts/${courtId}/queue/reorder`, { ordered_ids: orderedIds });

            await this.fetchQueue();
            await this.fetchCourts();
        },

        async searchUsers(query) {
            const { data } = await http.get('/admin/users/search', { params: { q: query } });

            return data.data;
        },
    },
});
