<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';

import { errorMessage } from '../api/http';
import AlertBanner from '../components/ui/AlertBanner.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import BaseModal from '../components/ui/BaseModal.vue';
import PlayerAvatar from '../components/ui/PlayerAvatar.vue';
import { useAuthStore } from '../stores/auth';
import { useQueueStore } from '../stores/queue';
import { elapsedSince, formatDuration, ordinal } from '../utils/format';
import { getTheme, toggleTheme } from '../utils/theme';

const auth = useAuthStore();
const queue = useQueueStore();
const route = useRoute();
const router = useRouter();

const { courts, activeCourtId, waitingEntries, calledEntries, myEntries, groups } = storeToRefs(queue);

/* ------------------------------------------------------------------ *
 * Live clock — drives the in-progress match timers.
 * ------------------------------------------------------------------ */
const now = ref(Date.now());
let timer = null;

/* ------------------------------------------------------------------ *
 * Outdoor Daylight theme toggle
 * ------------------------------------------------------------------ */
const daylight = ref(getTheme() === 'daylight');

function toggleThemeMode() {
    daylight.value = toggleTheme() === 'daylight';
}

/* ------------------------------------------------------------------ *
 * Deep-link hydration + real-time + polling fallback.
 *
 * Shared links carry the court id in the URL (/queue/{courtId}) so a fresh
 * mobile session hydrates the right line immediately, subscribes to that
 * court's real-time channel, and keeps a light polling fallback running in
 * case WebSockets stall (background tab, weak signal on the court).
 * ------------------------------------------------------------------ */
const POLL_MS = 10_000;
let pollTimer = null;

const refreshing = ref(false);

/**
 * Pull the latest queue + courts. The manual button shows a spinner; the
 * silent polling path (every 10s) never toggles it, so the header icon
 * doesn't spin on background ticks.
 */
async function refreshNow({ silent = false } = {}) {
    if (!silent) {
        if (refreshing.value) {
            return;
        }

        refreshing.value = true;
    }

    try {
        await Promise.all([queue.fetchQueue(), queue.fetchCourts()]);
    } catch {
        // Polling retries automatically — never surface noise mid-game.
    } finally {
        if (!silent) {
            refreshing.value = false;
        }
    }
}

function startPolling() {
    window.clearInterval(pollTimer);
    pollTimer = window.setInterval(() => refreshNow({ silent: true }), POLL_MS);
}

function onVisible() {
    if (!document.hidden) {
        queue.fetchQueue();
        queue.fetchCourts();
    }
}

onMounted(async () => {
    timer = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);

    // 1) Hydrate the deep-linked court (if any) before the courts load so
    //    fetchCourts() never overrides it with the first court.
    const courtId = Number(route.params.courtId);

    if (Number.isInteger(courtId) && courtId > 0) {
        await queue.activateCourt(courtId);
    }

    // 2) Pull the full picture, then sanity-check the deep link.
    await Promise.all([queue.fetchCourts(), queue.fetchGroups()]);

    if (route.params.courtId) {
        const exists = courts.value.some((court) => court.id === courtId);

        // Stale/removed court in a shared link — fall back to the first one.
        if (!exists && courts.value.length) {
            await queue.setActiveCourt(courts.value[0].id);
        }
    }

    // 3) Real-time channel was attached by activateCourt()/fetchCourts() —
    //    the polling loop is the safety net, not the primary source.
    startPolling();
    window.addEventListener('visibilitychange', onVisible);
});

// Navigating between two shared links (/queue/3 → /queue/5) reuses this
// component, so watch the param and rehydrate instead of waiting for a remount.
watch(
    () => route.params.courtId,
    async (courtId) => {
        const id = Number(courtId);

        if (!Number.isInteger(id) || id <= 0) {
            return;
        }

        await queue.activateCourt(id);

        const exists = courts.value.some((court) => court.id === id);

        if (!exists && courts.value.length) {
            await queue.setActiveCourt(courts.value[0].id);
        }
    },
);

onBeforeUnmount(() => {
    window.clearInterval(timer);
    window.clearInterval(pollTimer);
    window.removeEventListener('visibilitychange', onVisible);
});

const activeCourt = computed(
    () => courts.value.find((court) => court.id === activeCourtId.value) ?? null,
);

const matchElapsed = (court) =>
    court?.current_match?.started_at ? formatDuration(elapsedSince(court.current_match.started_at, now.value)) : '';

/** Called entries belonging to one court card (the "on deck" team). */
const courtOnDeck = (court) => calledEntries.value.filter((entry) => entry.court_id === court.id);

/** A queue entry that the current user is part of and that is "called". */
const myTurnEntry = computed(() => myEntries.value.find((entry) => entry.status === 'called') ?? null);

const myTurnCourt = computed(() =>
    myTurnEntry.value ? courts.value.find((court) => court.id === myTurnEntry.value.court_id) ?? null : null,
);

/** Lets the player dismiss the "your turn" banner without losing state. */
const turnDismissed = ref(false);

watch(
    () => myTurnEntry.value?.id,
    () => {
        turnDismissed.value = false;
    },
);

/* ------------------------------------------------------------------ *
 * Join-queue modal state
 * ------------------------------------------------------------------ */
const joinOpen = ref(false);
const joinType = ref('doubles');
const joinCourtId = ref(null);
const joinGroupId = ref(null);
const joinLoading = ref(false);
const joinError = ref('');
const leavingId = ref(null);

const joinCourts = computed(() => courts.value.filter((court) => court.play_type === joinType.value));

const selectedJoinCourt = computed(() =>
    joinCourts.value.find((court) => court.id === joinCourtId.value) ?? null,
);

function openJoin(court) {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }

    joinType.value = court.play_type;
    joinCourtId.value = court.id;
    joinGroupId.value = null;
    joinError.value = '';
    joinOpen.value = true;
}

function switchType(type) {
    joinType.value = type;

    const first = joinCourts.value[0];
    joinCourtId.value = first ? first.id : null;
    joinGroupId.value = null;
}

function selectCourt(courtId) {
    joinCourtId.value = courtId;
}

async function submitJoin() {
    if (!joinCourtId.value || joinLoading.value) {
        return;
    }

    joinLoading.value = true;
    joinError.value = '';

    try {
        await queue.join(joinCourtId.value, joinGroupId.value ? { group_id: joinGroupId.value } : {});
        joinOpen.value = false;
    } catch (err) {
        joinError.value = errorMessage(err, 'Could not join the queue.');
    } finally {
        joinLoading.value = false;
    }
}

async function leave(entry) {
    if (leavingId.value) {
        return;
    }

    leavingId.value = entry.id;

    try {
        await queue.leave(entry.id);
    } catch (err) {
        window.alert(errorMessage(err, 'Could not leave the queue.'));
    } finally {
        leavingId.value = null;
    }
}

async function logout() {
    await auth.logout();
    router.replace('/login');
}

const isMyEntry = (entry) => Array.isArray(entry?.players) && entry.players.some((player) => player.id === auth.user?.id);
</script>

<template>
    <div class="min-h-screen pb-16">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-950/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3.5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-volt-300">
                        <svg class="size-5 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="8" cy="6" r="3" />
                            <circle cx="16" cy="8" r="2.5" />
                            <circle cx="8" cy="18" r="2.5" />
                            <path stroke-linecap="round" d="M10.5 6h3.5a3 3 0 013 3v0M12 18h3a2 2 0 002-2v-6" />
                        </svg>
                    </div>
                    <span class="whitespace-nowrap text-lg font-black tracking-tight text-white">TaraPickle</span>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <button
                        v-if="auth.isAdmin"
                        class="min-h-12 rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-charcoal-200 transition hover:border-volt-300/40 hover:text-volt-200"
                        @click="router.push('/admin')"
                    >
                        Court control
                    </button>

                    <!-- Outdoor Daylight toggle -->
                    <button
                        class="grid size-12 place-items-center rounded-full transition"
                        :class="
                            daylight
                                ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                : 'text-charcoal-300 hover:bg-white/10 hover:text-white'
                        "
                        :title="daylight ? 'Switch to dark theme' : 'Switch to Outdoor Daylight (high contrast)'"
                        :aria-label="daylight ? 'Switch to dark theme' : 'Switch to Outdoor Daylight'"
                        :aria-pressed="daylight"
                        @click="toggleThemeMode"
                    >
                        <svg v-if="daylight" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="4" />
                            <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4l1.4-1.4" />
                        </svg>
                        <svg v-else class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36l-.7-.7M6.34 6.34l-.7-.7m12.72 0l-.7.7M6.34 17.66l-.7.7M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>

                    <!-- Manual refresh (min 48x48px) — pull the latest state when
                         the phone loses signal or WebSockets stall. -->
                    <button
                        class="grid size-12 place-items-center rounded-full text-charcoal-300 transition hover:bg-white/10 hover:text-white"
                        title="Refresh queue"
                        aria-label="Refresh queue"
                        @click="refreshNow"
                    >
                        <svg
                            class="size-5"
                            :class="refreshing ? 'animate-spin' : ''"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 9a7 7 0 0111.8-2.3M18.5 15a7 7 0 01-11.8 2.3" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-2.5">
                        <PlayerAvatar :player="auth.user" size="sm" />
                        <span class="hidden whitespace-nowrap text-sm font-semibold text-white sm:block">{{ auth.user?.name }}</span>
                        <button
                            class="grid size-12 place-items-center rounded-full text-charcoal-300 transition hover:bg-white/10 hover:text-white"
                            title="Sign out"
                            aria-label="Sign out"
                            @click="logout"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4M10 5V3h10v18H10v-2" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-8 px-4 pt-8">
            <!-- "Your turn" alert -->
            <Transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-opacity duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="myTurnEntry && !turnDismissed"
                    class="flex flex-wrap items-center gap-4 rounded-2xl border border-volt-300/40 bg-volt-300/15 px-5 py-4 shadow-glow"
                >
                    <div class="grid size-11 shrink-0 animate-pulse place-items-center rounded-full bg-volt-300">
                        <svg class="size-6 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12l-3.75 3.75M3 12h13.5M12 3.75c.75 1.5 2.25 4.5 2.25 8.25s-1.5 6.75-2.25 8.25c-.75-1.5-2.25-4.5-2.25-8.25S11.25 5.25 12 3.75z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="fluid-display font-black text-white">
                            {{ myTurnCourt?.name ?? 'Court' }} ready for you!
                        </h2>
                        <p class="text-sm text-volt-100/90">
                            Grab your paddle and head over — the organizer is about to confirm the court.
                        </p>
                    </div>
                    <BaseButton variant="secondary" size="sm" @click="turnDismissed = true">
                        Got it
                    </BaseButton>
                </div>
            </Transition>

            <!-- Active courts -->
            <section>
                <div class="mb-4 flex items-end justify-between">
                    <div>
                        <h1 class="fluid-display font-black tracking-tight text-white">Active courts</h1>
                        <p class="text-sm text-charcoal-300">Live matches and the players dinking on them right now.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <BaseCard
                        v-for="court in courts"
                        :key="court.id"
                        padding="md"
                        hoverable
                        :class="court.id === activeCourtId && 'border-volt-300/40 ring-1 ring-volt-300/30'"
                    >
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="break-words text-base font-black text-white">{{ court.name }}</h3>
                                <p class="text-xs text-charcoal-300">{{ court.location }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <Badge :color="court.play_type === 'singles' ? 'navy' : 'volt'" size="sm">
                                    {{ court.play_type }}
                                </Badge>
                                <Badge v-if="court.current_match" color="green" size="sm" dot>In Play</Badge>
                                <Badge v-else color="gray" size="sm">Empty</Badge>
                            </div>
                        </div>

                        <div v-if="court.current_match" class="space-y-2">
                            <!-- Two clearly separated teams: Team A vs Team B -->
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="(team, i) in court.current_match.teams"
                                    :key="team.id"
                                    class="rounded-xl border px-3 py-2.5"
                                    :class="i === 0 ? 'border-volt-300/30 bg-volt-300/[0.07]' : 'border-sky-400/30 bg-sky-400/[0.07]'"
                                >
                                    <div class="mb-1.5 flex items-center justify-between gap-1">
                                        <span
                                            class="text-[10px] font-black uppercase tracking-wider"
                                            :class="i === 0 ? 'text-volt-200' : 'text-sky-200'"
                                        >
                                            Team {{ i === 0 ? 'A' : 'B' }}
                                        </span>
                                        <span class="text-lg font-black leading-none text-white">{{ team.score }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <div class="flex -space-x-1.5">
                                            <PlayerAvatar
                                                v-for="player in team.players"
                                                :key="player.id"
                                                :player="player"
                                                size="sm"
                                            />
                                        </div>
                                        <p class="min-w-0 flex-1 break-words text-sm font-semibold leading-tight text-white">
                                            {{ team.players.map((player) => player.name).join(' & ') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="inline-flex items-center gap-1.5 font-mono text-charcoal-200">
                                    <svg class="size-3.5 text-volt-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="9" />
                                        <path stroke-linecap="round" d="M12 7v5l3 2" />
                                    </svg>
                                    {{ matchElapsed(court) }}
                                </span>
                                <Badge color="gray" size="sm">{{ court.waiting_count }} waiting</Badge>
                            </div>

                            <!-- On deck (up next) below the active players -->
                            <div v-if="courtOnDeck(court).length" class="rounded-xl border border-volt-300/25 bg-volt-300/[0.05] px-3 py-2">
                                <p class="mb-1 text-[10px] font-black uppercase tracking-wider text-volt-200">On deck — up next</p>
                                <div class="space-y-1">
                                    <p
                                        v-for="entry in courtOnDeck(court)"
                                        :key="entry.id"
                                        class="break-words text-sm font-semibold leading-snug text-white"
                                    >
                                        {{ entry.label }}
                                        <span class="font-normal text-volt-100/80">
                                            · {{ entry.players_count }} player{{ entry.players_count === 1 ? '' : 's' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-300">
                                <span class="size-2 rounded-full bg-emerald-400" />
                                Court free
                            </span>
                            <Badge color="gray" size="sm">{{ court.waiting_count }} waiting</Badge>
                        </div>

                        <div class="mt-4">
                            <BaseButton
                                :variant="court.id === activeCourtId ? 'primary' : 'secondary'"
                                block
                                size="sm"
                                @click="queue.setActiveCourt(court.id); openJoin(court)"
                            >
                                <template v-if="court.id !== activeCourtId">View &amp; join</template>
                                <template v-else>Join queue</template>
                            </BaseButton>
                        </div>
                    </BaseCard>
                </div>
            </section>

            <!-- Next up -->
            <section>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="fluid-display font-black tracking-tight text-white">Next up</h2>
                        <p class="text-sm text-charcoal-300">Your place in line — updates live.</p>
                    </div>
                </div>

                <!-- Court selector -->
                <div class="mb-4 flex flex-wrap gap-3">
                    <button
                        v-for="court in courts"
                        :key="court.id"
                        class="inline-flex min-h-12 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                        :class="
                            court.id === activeCourtId
                                ? 'border-volt-300/60 bg-volt-300/15 text-volt-200'
                                : 'border-white/10 bg-white/[0.03] text-charcoal-200 hover:border-white/25 hover:text-white'
                        "
                        @click="queue.setActiveCourt(court.id)"
                    >
                        {{ court.name }}
                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] text-charcoal-200">
                            {{ court.waiting_count }}
                        </span>
                    </button>
                </div>

                <BaseCard padding="none">
                    <template v-if="waitingEntries.length || calledEntries.length">
                        <ul class="divide-y divide-white/5">
                            <!-- On-deck (called) entries first -->
                            <li
                                v-for="entry in calledEntries"
                                :key="entry.id"
                                class="flex flex-wrap items-center gap-3 bg-volt-300/[0.07] px-4 py-3.5 sm:px-5"
                            >
                                <span class="w-14 shrink-0 text-center">
                                    <span class="inline-grid size-11 place-items-center rounded-full bg-volt-300/20 text-sm font-black text-volt-200 ring-1 ring-volt-300/40">
                                        ON DECK
                                    </span>
                                </span>

                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex -space-x-2">
                                        <PlayerAvatar v-for="player in entry.players" :key="player.id" :player="player" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="break-words text-base font-semibold text-white">{{ entry.label }}</p>
                                        <p class="text-xs text-charcoal-300">
                                            {{ entry.players_count }} player{{ entry.players_count === 1 ? '' : 's' }} · headed to court
                                        </p>
                                    </div>
                                </div>

                                <Badge color="volt" size="sm" dot>{{ isMyEntry(entry) ? 'You! Grab your paddle' : 'Called' }}</Badge>
                            </li>

                            <!-- Waiting entries -->
                            <li
                                v-for="(entry, index) in waitingEntries"
                                :key="entry.id"
                                class="flex flex-wrap items-center gap-3 px-4 py-3.5 transition hover:bg-white/[0.03] sm:px-5"
                            >
                                <span class="w-14 shrink-0 text-center">
                                    <span
                                        class="inline-grid size-11 place-items-center rounded-full text-sm font-black"
                                        :class="
                                            index === 0
                                                ? 'bg-volt-300 text-ink shadow-[0_4px_16px_-2px_rgb(255_214_10/0.5)]'
                                                : 'bg-white/10 text-charcoal-200'
                                        "
                                    >
                                        {{ ordinal(entry.position) }}
                                    </span>
                                </span>

                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex -space-x-2">
                                        <PlayerAvatar v-for="player in entry.players" :key="player.id" :player="player" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="break-words text-base font-semibold text-white">{{ entry.label }}</p>
                                        <p class="text-xs text-charcoal-300">
                                            {{ entry.players_count }} player{{ entry.players_count === 1 ? '' : 's' }}
                                            <span v-if="entry.group" class="text-volt-200/80">· squad</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <Badge v-if="isMyEntry(entry)" color="volt" size="sm">You</Badge>
                                    <BaseButton
                                        v-if="isMyEntry(entry)"
                                        variant="ghost"
                                        size="sm"
                                        :loading="leavingId === entry.id"
                                        @click="leave(entry)"
                                    >
                                        Leave
                                    </BaseButton>
                                </div>
                            </li>
                        </ul>
                    </template>

                    <template v-else>
                        <div
                            class="flex cursor-pointer flex-col items-center gap-3 px-6 py-14 text-center transition hover:bg-white/[0.03]"
                            @click="activeCourt && openJoin(activeCourt)"
                        >
                            <div class="grid size-14 place-items-center rounded-full bg-white/5">
                                <svg class="size-7 text-charcoal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-white">The line is empty</p>
                                <p class="text-sm text-charcoal-300">Be first on {{ activeCourt?.name }} — grab a spot.</p>
                            </div>
                            <BaseButton v-if="activeCourt" size="sm" @click.stop="openJoin(activeCourt)">
                                Join queue
                            </BaseButton>
                        </div>
                    </template>
                </BaseCard>
            </section>
        </main>

        <footer class="mt-10 px-4 pb-4 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Tara Pickle by Claire</p>
        </footer>

        <!-- Join queue modal -->
        <BaseModal v-model="joinOpen" title="Join the queue" max-width="max-w-xl">
            <AlertBanner type="error" :message="joinError" @close="joinError = ''" />

            <!-- Step 1: play type -->
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-charcoal-300">Play type</p>
            <div class="mb-5 grid grid-cols-2 gap-3">
                <button
                    v-for="type in ['doubles', 'singles']"
                    :key="type"
                    class="min-h-12 rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        joinType === type
                            ? 'border-volt-300/60 bg-volt-300/15'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/25'
                    "
                    @click="switchType(type)"
                >
                    <span class="block text-base font-bold text-white capitalize">{{ type }}</span>
                    <span class="text-xs text-charcoal-300">{{ type === 'doubles' ? '2v2 · 4 players' : '1v1 · 2 players' }}</span>
                </button>
            </div>

            <!-- Step 2: court -->
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-charcoal-300">Court</p>
            <div class="mb-5 space-y-2">
                <button
                    v-for="court in joinCourts"
                    :key="court.id"
                    class="flex min-h-12 w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        joinCourtId === court.id
                            ? 'border-volt-300/60 bg-volt-300/15'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/25'
                    "
                    @click="selectCourt(court.id)"
                >
                    <span class="min-w-0">
                        <span class="block break-words text-base font-bold text-white">{{ court.name }}</span>
                        <span class="text-xs text-charcoal-300">{{ court.location }}</span>
                    </span>
                    <Badge color="gray" size="sm">{{ court.waiting_count }} waiting</Badge>
                </button>

                <p v-if="!joinCourts.length" class="rounded-xl bg-white/[0.03] px-4 py-3 text-sm text-charcoal-300">
                    No {{ joinType }} courts available right now.
                </p>
            </div>

            <!-- Step 3: squad -->
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-charcoal-300">Squad</p>
            <div class="space-y-2">
                <button
                    class="flex min-h-12 w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        joinGroupId === null
                            ? 'border-volt-300/60 bg-volt-300/15'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/25'
                    "
                    @click="joinGroupId = null"
                >
                    <PlayerAvatar :player="auth.user" />
                    <span class="text-base font-bold text-white">Solo — just me</span>
                    <span class="ml-auto text-xs text-charcoal-300">1 spot</span>
                </button>

                <button
                    v-for="group in groups"
                    :key="group.id"
                    class="flex min-h-12 w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        joinGroupId === group.id
                            ? 'border-volt-300/60 bg-volt-300/15'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/25'
                    "
                    @click="joinGroupId = group.id"
                >
                    <div class="flex -space-x-2">
                        <PlayerAvatar v-for="player in group.players" :key="player.id" :player="player" size="sm" />
                    </div>
                    <span class="min-w-0 flex-1 break-words text-base font-bold text-white">{{ group.name }}</span>
                    <span class="text-xs text-charcoal-300">{{ group.players.length }} spots</span>
                </button>

                <p v-if="!groups.length" class="rounded-xl bg-white/[0.03] px-4 py-3 text-sm text-charcoal-300">
                    You don't have any squads yet — join solo and the organizer can group you.
                </p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="ghost" @click="joinOpen = false">Cancel</BaseButton>
                    <BaseButton :loading="joinLoading" :disabled="!selectedJoinCourt" @click="submitJoin">
                        Join {{ selectedJoinCourt?.name ?? 'queue' }}
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </div>
</template>
