import { defineStore } from 'pinia';

import { randomAnimal } from '../data/avatars';
import { skillValue } from '../utils/skills';

const STORAGE_KEY = 'tarapickle:state:v1';

const DEFAULT_SETTINGS = {
    tts: false, // court-call text-to-speech announcements
};

let counter = 0;
let lastTs = 0;

/** Monotonic clock — guarantees unique timestamps (newest-first sorting). */
function now() {
    const ts = Date.now();

    if (ts <= lastTs) {
        lastTs += 1;

        return lastTs;
    }

    lastTs = ts;

    return ts;
}

function uid(prefix) {
    counter += 1;

    return `${prefix}_${Date.now().toString(36)}_${counter}_${Math.random().toString(36).slice(2, 7)}`;
}

function shuffle(items) {
    const copy = [...items];

    for (let i = copy.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));

        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
}

/**
 * Fair 4-player selection ("equal play rule"):
 *  1. Waiting players are grouped by total gamesPlayed — lowest first.
 *  2. Inside a group, the players who have waited longest go first.
 *  3. Exact ties are shuffled, so the pick still feels random.
 *
 * This guarantees nobody plays a second match before everyone in the queue
 * has played at least once, and afterwards keeps total match counts as
 * balanced as possible.
 */
function pickFairPlayers(waiting, count) {
    const byGames = new Map();

    for (const player of waiting) {
        const group = byGames.get(player.gamesPlayed) ?? [];

        group.push(player);
        byGames.set(player.gamesPlayed, group);
    }

    const ordered = [];

    for (const games of [...byGames.keys()].sort((a, b) => a - b)) {
        const group = byGames
            .get(games)
            .sort((a, b) => (a.lastPlayedAt ?? 0) - (b.lastPlayedAt ?? 0));

        // Shuffle only the exact ties (same gamesPlayed AND same lastPlayedAt).
        let start = 0;

        while (start < group.length) {
            let end = start + 1;

            while (
                end < group.length
                && (group[end].lastPlayedAt ?? 0) === (group[start].lastPlayedAt ?? 0)
            ) {
                end += 1;
            }

            ordered.push(...shuffle(group.slice(start, end)));
            start = end;
        }
    }

    return ordered.slice(0, count);
}

/**
 * Split 4 summoned players into two balanced teams.
 *   - Fixed pairs are kept together (the "Fixed Pair" tag wins over balance).
 *   - Remaining singles pair High + Low skill to keep both sides fair.
 *   - With no fixed pairs at all: strongest + weakest vs. the two middle.
 */
function buildTeams(pool) {
    const used = new Set();
    const pairs = [];

    for (const player of pool) {
        if (used.has(player.id)) {
            continue;
        }

        if (player.fixedPairId) {
            const partner = pool.find(
                (other) =>
                    other.fixedPairId === player.fixedPairId
                    && other.id !== player.id
                    && !used.has(other.id),
            );

            if (partner) {
                pairs.push([player, partner]);
                used.add(player.id);
                used.add(partner.id);
            }
        }
    }

    const singles = pool
        .filter((player) => !used.has(player.id))
        .sort((a, b) => skillValue(b.skill) - skillValue(a.skill));

    const groups = [...pairs];

    // Pair the strongest remaining player with the weakest remaining player.
    while (singles.length >= 2) {
        const strongest = singles.shift();
        const weakest = singles.pop();

        groups.push([strongest, weakest]);
    }

    // With 4 players there are always exactly two groups; flip which side
    // gets which group so the "A" label stays fair over many matches.
    if (Math.random() < 0.5) {
        groups.reverse();
    }

    return [
        { key: 'A', playerIds: groups[0].map((player) => player.id) },
        { key: 'B', playerIds: groups[1].map((player) => player.id) },
    ];
}

/**
 * Backfill defaults on a queue loaded from an older localStorage snapshot.
 * Old queues had a single implicit court (`activeMatch`), so they migrate to
 * a real `courts` array without losing the in-flight match.
 */
function normalizeQueue(queue) {
    if (typeof queue.open !== 'boolean') {
        queue.open = true;
    }

    if (!Array.isArray(queue.courts)) {
        const courts = [];

        if (queue.activeMatch) {
            courts.push({ id: uid('court'), label: 'Court 1', activeMatch: queue.activeMatch });
        } else {
            courts.push({ id: uid('court'), label: 'Court 1', activeMatch: null });
        }

        queue.courts = courts;
        delete queue.activeMatch;
    } else {
        queue.courts.forEach((court, index) => {
            court.id ??= uid('court');
            court.label ??= `Court ${index + 1}`;
            court.activeMatch ??= null;
            court.announce ??= true; // per-court court-call sound toggle
        });
    }

    const firstCourtId = queue.courts[0]?.id ?? null;

    for (const player of queue.players ?? []) {
        player.skill ??= null;
        player.paused ??= false;
        player.fixedPairId ??= null;
        player.courtId ??= null;

        // Mid-flow players from the old single-court model belong to Court 1.
        if (player.status !== 'waiting' && !player.courtId && firstCourtId) {
            player.courtId = firstCourtId;
        }
    }
}

function loadState() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return { queues: [], settings: { ...DEFAULT_SETTINGS } };
        }

        const parsed = JSON.parse(raw);
        let queues = null;
        let settings = null;

        if (Array.isArray(parsed)) {
            // v1 snapshot: bare queues array.
            queues = parsed;
            settings = { ...DEFAULT_SETTINGS };
        } else if (parsed && typeof parsed === 'object' && Array.isArray(parsed.queues)) {
            queues = parsed.queues;
            settings = { ...DEFAULT_SETTINGS, ...(parsed.settings ?? {}) };
        }

        if (Array.isArray(queues)) {
            // Skip any corrupt entries so a stale snapshot never crashes the app.
            const validQueues = queues.filter((queue) => queue && typeof queue === 'object');

            for (const queue of validQueues) {
                normalizeQueue(queue);
            }

            return { queues: validQueues, settings: settings ?? { ...DEFAULT_SETTINGS } };
        }
    } catch {
        // Corrupt or unavailable storage — start fresh.
    }

    return { queues: [], settings: { ...DEFAULT_SETTINGS } };
}

/**
 * TaraPickle — open-access, single-page court queue management.
 *
 * Everything lives client-side (Pinia + localStorage), so any visitor can
 * create sessions, add guest players, run multiple courts at once, score
 * matches and track stats with no accounts or backend. All views read from
 * this one store, which is why a status change or avatar swap updates
 * everywhere at once — and a refresh restores everything.
 */
export const useTarapickleStore = defineStore('tarapickle', {
    state: () => {
        const { queues, settings } = loadState();

        return {
            queues,
            settings,
            /** Which queue is expanded in the Queues tab — accordion: only one at a time. */
            expandedQueueId: queues[0]?.id ?? null,
        };
    },

    getters: {
        /** Newest created queue always first. */
        sortedQueues(state) {
            return [...state.queues].sort((a, b) => b.createdAt - a.createdAt);
        },

        /** Every player across every queue, flattened (for board/results). */
        allPlayers(state) {
            const rows = [];

            for (const queue of state.queues) {
                for (const player of queue.players) {
                    rows.push({ ...player, queueId: queue.id, queueName: queue.name });
                }
            }

            return rows;
        },
    },

    actions: {
        persist() {
            try {
                localStorage.setItem(
                    STORAGE_KEY,
                    JSON.stringify({ queues: this.queues, settings: this.settings }),
                );
            } catch {
                // Storage unavailable — keep the in-memory state for the session.
            }
        },

        findQueue(queueId) {
            return this.queues.find((queue) => queue.id === queueId) ?? null;
        },

        /** Next auto name: "Queue #1", "Queue #2", … never colliding. */
        nextQueueNumber() {
            let max = 0;

            for (const queue of this.queues) {
                const match = /^Queue #(\d+)$/.exec(queue.name);

                if (match) {
                    max = Math.max(max, Number(match[1]));
                }
            }

            return max + 1;
        },

        createQueue(name = '') {
            const trimmed = name.trim();

            const queue = {
                id: uid('queue'),
                name: trimmed || `Queue #${this.nextQueueNumber()}`,
                createdAt: now(),
                open: true,
                players: [],
                courts: [{ id: uid('court'), label: 'Court 1', activeMatch: null, announce: true }],
            };

            this.queues.push(queue);
            this.expandedQueueId = queue.id; // auto-open the fresh session
            this.persist();

            return queue;
        },

        deleteQueue(queueId) {
            this.queues = this.queues.filter((queue) => queue.id !== queueId);

            if (this.expandedQueueId === queueId) {
                this.expandedQueueId = this.sortedQueues[0]?.id ?? null;
            }

            this.persist();
        },

        /* ------------------------------------------------------------ *
         * Accordion — expanding one queue collapses every other.
         * ------------------------------------------------------------ */

        /** Expand a queue (collapsing any other). Passing null collapses all. */
        expandQueue(queueId) {
            this.expandedQueueId = queueId ?? null;
        },

        /** Toggle the queue's expansion; opening one closes the rest. */
        toggleExpanded(queueId) {
            this.expandedQueueId = this.expandedQueueId === queueId ? null : queueId;
        },

        /* ------------------------------------------------------------ *
         * Per-court controls
         * ------------------------------------------------------------ */

        /** Flip a court's "announce this court's calls" sound toggle. */
        toggleCourtSound(queueId, courtId) {
            const court = this.findQueue(queueId)?.courts.find((c) => c.id === courtId);

            if (!court) {
                return;
            }

            court.announce = !court.announce;
            this.persist();
        },

        /**
         * Close / wrap up a court call: every called or confirmed (but not yet
         * playing) player on the court goes back to the line. Never mid-match.
         * Returns the players returned to the line (or null when not possible).
         */
        cancelCourtCall(queueId, courtId) {
            const queue = this.findQueue(queueId);
            const court = queue?.courts.find((c) => c.id === courtId);

            if (!queue || !court || court.activeMatch) {
                return null;
            }

            const returned = queue.players.filter(
                (player) => player.courtId === courtId && player.status !== 'waiting',
            );

            if (!returned.length) {
                return null;
            }

            for (const player of returned) {
                player.status = 'waiting';
                player.courtId = null;
            }

            this.persist();

            return returned;
        },

        /** End / reopen a queue session — closed queues reject new entries. */
        toggleQueueOpen(queueId) {
            const queue = this.findQueue(queueId);

            if (!queue) {
                return;
            }

            queue.open = !queue.open;
            this.persist();
        },

        /** Add another court to a session (Court 2, Court 3, … up to 4). */
        addCourt(queueId) {
            const queue = this.findQueue(queueId);

            if (!queue || queue.courts.length >= 4) {
                return null;
            }

            const court = {
                id: uid('court'),
                label: `Court ${queue.courts.length + 1}`,
                activeMatch: null,
                announce: true,
            };

            queue.courts.push(court);
            this.persist();

            return court;
        },

        /** Remove an idle court — only allowed with no match and no one on deck. */
        removeCourt(queueId, courtId) {
            const queue = this.findQueue(queueId);
            const court = queue?.courts.find((c) => c.id === courtId);

            if (!queue || !court || court.activeMatch) {
                return false;
            }

            if (queue.players.some((player) => player.courtId === courtId && player.status !== 'waiting')) {
                return false;
            }

            queue.courts = queue.courts.filter((c) => c.id !== courtId);
            this.persist();

            return true;
        },

        /** Add a guest player with a randomly-assigned cute animal avatar. */
        addPlayer(queueId, name) {
            const queue = this.findQueue(queueId);
            const trimmed = name.trim();

            if (!queue || !trimmed || !queue.open) {
                return null;
            }

            const animal = randomAnimal();

            const player = {
                id: uid('player'),
                name: trimmed,
                avatarUrl: animal.url,
                avatarEmoji: animal.emoji,
                status: 'waiting', // waiting → called → active
                skill: null, // Beginner | Intermediate | Advanced
                paused: false, // "taking a break" — skipped by the randomizer
                fixedPairId: null, // two players sharing an id always team up
                courtId: null, // which court summoned them (called/active only)
                gamesPlayed: 0,
                wins: 0,
                lastPlayedAt: null,
            };

            queue.players.push(player);
            this.persist();

            return player;
        },

        /** Remove a waiting player from the queue. */
        removePlayer(queueId, playerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);

            if (!queue || !player || player.status !== 'waiting') {
                return;
            }

            // Break any fixed pair the removed player was part of.
            if (player.fixedPairId) {
                for (const other of queue.players) {
                    if (other.fixedPairId === player.fixedPairId) {
                        other.fixedPairId = null;
                    }
                }
            }

            queue.players = queue.players.filter((p) => p.id !== playerId);
            this.persist();
        },

        setAvatar(queueId, playerId, animal) {
            const player = this.findQueue(queueId)?.players.find((p) => p.id === playerId);

            if (!player) {
                return;
            }

            player.avatarUrl = animal.url;
            player.avatarEmoji = animal.emoji;
            this.persist();
        },

        setSkill(queueId, playerId, skill) {
            const player = this.findQueue(queueId)?.players.find((p) => p.id === playerId);

            if (!player) {
                return;
            }

            player.skill = skill || null;
            this.persist();
        },

        /** Toggle a player's "taking a break" status (kept in queue + stats). */
        togglePause(queueId, playerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);

            if (!queue || !player || player.status !== 'waiting') {
                return;
            }

            player.paused = !player.paused;
            this.persist();
        },

        /** Mark two players as a fixed pair (randomizer keeps them together). */
        setFixedPair(queueId, playerId, partnerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);
            const partner = queue?.players.find((p) => p.id === partnerId);

            if (!queue || !player || !partner || player.id === partner.id) {
                return;
            }

            // Clear any existing pairing on both players first.
            for (const p of [player, partner]) {
                if (p.fixedPairId) {
                    for (const other of queue.players) {
                        if (other.fixedPairId === p.fixedPairId) {
                            other.fixedPairId = null;
                        }
                    }
                }
            }

            const pairId = uid('pair');

            player.fixedPairId = pairId;
            partner.fixedPairId = pairId;
            this.persist();
        },

        /** Break the fixed pair a player belongs to. */
        unsetFixedPair(queueId, playerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);

            if (!queue || !player?.fixedPairId) {
                return;
            }

            for (const other of queue.players) {
                if (other.fixedPairId === player.fixedPairId) {
                    other.fixedPairId = null;
                }
            }

            this.persist();
        },

        /**
         * Fairly pick 4 waiting players for a specific court and summon them.
         * Paused ("taking a break") players are automatically skipped.
         */
        randomizeCourt(queueId, courtId) {
            const queue = this.findQueue(queueId);
            const court = queue?.courts.find((c) => c.id === courtId);

            if (!queue || !court || court.activeMatch) {
                return null;
            }

            // Refuse while this court is still mid-flow (called or confirmed).
            if (queue.players.some((player) => player.courtId === courtId && player.status !== 'waiting')) {
                return null;
            }

            const waiting = queue.players.filter((player) => player.status === 'waiting' && !player.paused);

            if (waiting.length < 4) {
                return null;
            }

            const picked = pickFairPlayers(waiting, 4);

            for (const player of picked) {
                player.status = 'called';
                player.courtId = courtId;
            }

            this.persist();

            return { court, players: picked };
        },

        /** Player confirms they are ready — when all four are in, the match starts. */
        confirmPlayer(queueId, playerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);

            if (!queue || !player || player.status !== 'called') {
                return;
            }

            player.status = 'active';

            const court = queue.courts.find((c) => c.id === player.courtId);

            if (court) {
                this.startMatchIfReady(queue, court);
            }

            this.persist();
        },

        /** Undo a call / confirmation and send the player back to the line. */
        cancelCall(queueId, playerId) {
            const queue = this.findQueue(queueId);
            const player = queue?.players.find((p) => p.id === playerId);
            const court = player ? queue?.courts.find((c) => c.id === player.courtId) : null;

            // Only while the court is still being assembled — never mid-match.
            if (!queue || !player || player.status === 'waiting' || court?.activeMatch) {
                return;
            }

            player.status = 'waiting';
            player.courtId = null;
            this.persist();
        },

        /**
         * Substitute a called player with a waiting player ("Change Player").
         * The called player goes back to the line; the substitute takes their
         * exact spot on the same court — court assignment stays intact.
         */
        swapPlayer(queueId, calledPlayerId, waitingPlayerId) {
            const queue = this.findQueue(queueId);
            const called = queue?.players.find((p) => p.id === calledPlayerId);
            const substitute = queue?.players.find((p) => p.id === waitingPlayerId);
            const court = called ? queue?.courts.find((c) => c.id === called.courtId) : null;

            if (!queue || !called || !substitute || !court || court.activeMatch) {
                return false;
            }

            // Called OR confirmed-but-unmatched players can be substituted;
            // never mid-match, and never with a paused player.
            if (called.status === 'waiting' || substitute.status !== 'waiting' || substitute.paused) {
                return false;
            }

            substitute.status = 'called';
            substitute.courtId = court.id;

            called.status = 'waiting';
            called.courtId = null;

            this.persist();

            return true;
        },

        /** Once all 4 summoned players on a court are active, split into teams. */
        startMatchIfReady(queue, court) {
            if (court.activeMatch) {
                return;
            }

            const active = queue.players.filter(
                (player) => player.courtId === court.id && player.status === 'active',
            );

            if (active.length !== 4) {
                return;
            }

            court.activeMatch = {
                id: uid('match'),
                startedAt: now(),
                teams: buildTeams(active),
            };
        },

        /**
         * Record the result for a court. Winners get a win, everyone in the
         * match gets a game played, and all four players return to the line.
         * If four or more players are still waiting, the court auto-refills
         * with the next fair four so the next game starts without a click.
         */
        finishMatch(queueId, courtId, { winner, scoreA = 0, scoreB = 0 }) {
            const queue = this.findQueue(queueId);
            const court = queue?.courts.find((c) => c.id === courtId);
            const match = court?.activeMatch;

            if (!queue || !court || !match) {
                return null;
            }

            const teamA = match.teams[0];
            const teamB = match.teams[1];
            const byId = new Map(queue.players.map((player) => [player.id, player]));
            const winnerIds = winner === 'A' ? teamA.playerIds : teamB.playerIds;
            const inMatch = new Set([...teamA.playerIds, ...teamB.playerIds]);

            for (const player of queue.players) {
                if (inMatch.has(player.id)) {
                    player.gamesPlayed += 1;
                    player.status = 'waiting';
                    player.courtId = null;
                    player.lastPlayedAt = now();
                }
            }

            for (const playerId of winnerIds) {
                const player = byId.get(playerId);

                if (player) {
                    player.wins += 1;
                }
            }

            court.activeMatch = null;

            // Auto-refill this court with the next fair four, if available.
            const refilled = [];

            if (!queue.players.some((p) => p.courtId === courtId && p.status !== 'waiting')) {
                const next = this.randomizeCourt(queueId, courtId);

                if (next) {
                    refilled.push(...next.players);
                }
            }

            this.persist();

            return {
                winners: winnerIds.map((id) => byId.get(id)?.name ?? 'Player'),
                scoreA,
                scoreB,
                courtLabel: court.label,
                refilled,
            };
        },

        /* ------------------------------------------------------------ *
         * Settings (persisted alongside queues)
         * ------------------------------------------------------------ */
        setTts(enabled) {
            this.settings.tts = Boolean(enabled);
            this.persist();
        },
    },
});
