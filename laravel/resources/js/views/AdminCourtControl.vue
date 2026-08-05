<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
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
import { ordinal } from '../utils/format';
import { getTheme, toggleTheme } from '../utils/theme';

const auth = useAuthStore();
const queue = useQueueStore();
const router = useRouter();

const { courts, activeCourtId, waitingEntries, calledEntries } = storeToRefs(queue);

/** Outdoor Daylight high-contrast toggle. */
const daylight = ref(getTheme() === 'daylight');

function toggleThemeMode() {
    daylight.value = toggleTheme() === 'daylight';
}

const busy = ref(false);
const error = ref('');
const toast = ref('');

/* ------------------------------------------------------------------ *
 * Courts
 * ------------------------------------------------------------------ */
const activeCourt = computed(
    () => courts.value.find((court) => court.id === activeCourtId.value) ?? null,
);

const activeMatch = computed(() => activeCourt.value?.current_match ?? null);

onMounted(async () => {
    await queue.fetchCourts();
    await queue.fetchQueue();
});

function notify(message) {
    toast.value = message;
    window.setTimeout(() => {
        if (toast.value === message) {
            toast.value = '';
        }
    }, 3500);
}

async function run(action) {
    if (busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await action();
    } catch (err) {
        error.value = errorMessage(err, 'Action failed.');
    } finally {
        busy.value = false;
    }
}

/* ------------------------------------------------------------------ *
 * Call next / confirm / complete
 * ------------------------------------------------------------------ */
async function callNext() {
    await run(() => queue.callNext(activeCourtId.value));

    if (!error.value) {
        notify('Players called to the court.');
    }
}

async function confirmCall() {
    await run(() => queue.confirmCall(activeCourtId.value));

    if (!error.value) {
        notify('Court confirmed — match is live.');
    }
}

const scoreOpen = ref(false);
const scoreA = ref(0);
const scoreB = ref(0);

function openScore() {
    const teams = activeMatch.value?.teams ?? [];

    scoreA.value = teams[0]?.score ?? 0;
    scoreB.value = teams[1]?.score ?? 0;
    scoreOpen.value = true;
}

async function submitScore() {
    if (!activeMatch.value) {
        return;
    }

    await run(async () => {
        await queue.completeMatch(activeMatch.value.id, scoreA.value, scoreB.value);
        scoreOpen.value = false;
    });

    if (!error.value) {
        notify('Match recorded — winners stay or requeue per court rule.');
    }
}

/* ------------------------------------------------------------------ *
 * Drag-and-drop reorder of the waiting line
 * ------------------------------------------------------------------ */
const dragIndex = ref(null);
const overIndex = ref(null);

function onDragStart(index) {
    dragIndex.value = index;
}

function onDragOver(index) {
    if (index !== overIndex.value) {
        overIndex.value = index;
    }
}

function onDrop() {
    if (dragIndex.value === null || overIndex.value === null || dragIndex.value === overIndex.value) {
        resetDrag();

        return;
    }

    const ordered = waitingEntries.value.map((entry) => entry.id);
    const [moved] = ordered.splice(dragIndex.value, 1);
    ordered.splice(overIndex.value, 0, moved);

    const from = dragIndex.value;
    const to = overIndex.value;

    resetDrag();

    run(() => queue.reorderQueue(activeCourtId.value, ordered)).then(() => {
        if (!error.value) {
            notify(`Line reordered — ${ordinal(from)} moved to ${ordinal(to)}.`);
        }
    });
}

function onDragLeave(index) {
    if (overIndex.value === index) {
        overIndex.value = null;
    }
}

function resetDrag() {
    dragIndex.value = null;
    overIndex.value = null;
}

/* ------------------------------------------------------------------ *
 * Manual add / remove
 * ------------------------------------------------------------------ */
const addOpen = ref(false);
const query = ref('');
const searchResults = ref([]);
const searching = ref(false);
const addingId = ref(null);
const skipId = ref(null);
let searchTimer = null;

async function search(q) {
    query.value = q;

    window.clearTimeout(searchTimer);

    if (!q.trim()) {
        searchResults.value = [];

        return;
    }

    searchTimer = window.setTimeout(async () => {
        searching.value = true;

        try {
            searchResults.value = await queue.searchUsers(q.trim());
        } catch {
            searchResults.value = [];
        } finally {
            searching.value = false;
        }
    }, 250);
}

function openAdd() {
    query.value = '';
    searchResults.value = [];
    addOpen.value = true;
}

async function addPlayer(user) {
    if (addingId.value) {
        return;
    }

    addingId.value = user.id;

    try {
        await queue.addPlayer(activeCourtId.value, user.id);
        addOpen.value = false;
        notify(`${user.name} added to the line.`);
    } catch (err) {
        error.value = errorMessage(err, 'Could not add player.');
    } finally {
        addingId.value = null;
    }
}

async function removeEntry(entry) {
    if (skipId.value) {
        return;
    }

    skipId.value = entry.id;

    try {
        await queue.skipEntry(entry.id);
        notify(`${entry.label} removed from the line.`);
    } catch (err) {
        error.value = errorMessage(err, 'Could not remove player.');
    } finally {
        skipId.value = null;
    }
}

async function logout() {
    await auth.logout();
    router.replace('/login');
}

function selectCourt(courtId) {
    if (courtId) {
        queue.setActiveCourt(courtId);
    }
}
</script>

<template>
    <div class="min-h-screen pb-16">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-950/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5">
                <div class="flex items-center gap-2.5">
                    <button
                        class="grid size-12 place-items-center rounded-xl bg-volt-300"
                        title="Back to dashboard"
                        aria-label="Back to dashboard"
                        @click="router.push('/')"
                    >
                        <svg class="size-5 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div>
                        <span class="block text-lg font-black tracking-tight text-white">Court control</span>
                        <span class="text-[11px] text-charcoal-300">Organizer console</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Badge color="volt" size="sm" dot>Admin</Badge>

                    <!-- Outdoor Daylight toggle -->
                    <button
                        class="grid size-12 place-items-center rounded-full transition"
                        :class="
                            daylight
                                ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                : 'text-charcoal-300 hover:bg-white/10 hover:text-white'
                        "
                        :title="daylight ? 'Switch to dark theme' : 'Switch to Outdoor Daylight (high contrast)'"
                        aria-label="Toggle Outdoor Daylight theme"
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

                    <button
                        class="inline-flex min-h-12 items-center gap-1.5 rounded-full border border-white/10 px-4 py-2 text-sm font-semibold text-charcoal-200 transition hover:border-volt-300/40 hover:text-volt-200"
                        @click="router.push('/admin/analytics')"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 20h12M8 16h8M9 12h6M10 8h4M12 4v16" />
                        </svg>
                        Analytics
                    </button>
                    <PlayerAvatar :player="auth.user" size="sm" />
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
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 pt-8">
            <Transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0 -translate-y-2"
                leave-active-class="transition-opacity duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    class="flex items-center gap-3 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200"
                >
                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.5l2.5 2.5 4.5-5" />
                    </svg>
                    {{ toast }}
                </div>
            </Transition>

            <AlertBanner type="error" :message="error" @close="error = ''" />

            <!-- Court tabs -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="court in courts"
                    :key="court.id"
                    class="inline-flex min-h-12 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                    :class="
                        court.id === activeCourtId
                            ? 'border-volt-300/60 bg-volt-300/15 text-volt-200'
                            : 'border-white/10 bg-white/[0.03] text-charcoal-200 hover:border-white/25 hover:text-white'
                    "
                    @click="selectCourt(court.id)"
                >
                    {{ court.name }}
                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] text-charcoal-200">
                        {{ court.waiting_count }}
                    </span>
                </button>
            </div>

            <!-- Control row -->
            <div class="flex flex-wrap items-center gap-3">
                <BaseButton size="lg" :loading="busy" @click="callNext">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-5-5m5 5l-5 5" />
                    </svg>
                    Call next {{ activeCourt?.max_players ?? 4 }} players
                </BaseButton>

                <BaseButton v-if="activeMatch" variant="secondary" :loading="busy" @click="openScore">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    Record score
                </BaseButton>

                <BaseButton v-if="calledEntries.length" variant="secondary" :loading="busy" @click="confirmCall">
                    Confirm {{ calledEntries.length }} called player{{ calledEntries.length === 1 ? '' : 's' }} on court
                </BaseButton>

                <div class="ml-auto">
                    <BaseButton variant="ghost" @click="openAdd">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                        </svg>
                        Add player
                    </BaseButton>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Live match -->
                <section class="lg:col-span-1">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-charcoal-300">Live match</h2>

                    <BaseCard padding="md">
                        <template v-if="activeMatch">
                            <div class="mb-4 flex items-center justify-between">
                                <Badge color="green" size="sm" dot>In progress</Badge>
                                <span class="font-mono text-xs text-charcoal-300">
                                    {{ new Date(activeMatch.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(team, i) in activeMatch.teams"
                                    :key="team.id"
                                    class="flex items-center justify-between rounded-xl bg-navy-950/50 px-4 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex -space-x-2">
                                            <PlayerAvatar v-for="player in team.players" :key="player.id" :player="player" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-white">
                                                {{ team.players.map((p) => p.name.split(' ')[0]).join(' & ') }}
                                            </p>
                                            <p class="text-[11px] text-charcoal-300">Team {{ i === 0 ? 'A' : 'B' }}</p>
                                        </div>
                                    </div>
                                    <span class="text-2xl font-black text-volt-300">{{ team.score }}</span>
                                </div>
                            </div>
                        </template>

                        <div v-else class="flex flex-col items-center gap-2 py-8 text-center">
                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-300">
                                <span class="size-2 rounded-full bg-emerald-400" />
                                Court free
                            </span>
                            <p class="text-sm text-charcoal-300">Call the next players to start a match.</p>
                        </div>
                    </BaseCard>

                    <!-- Called (on deck) -->
                    <h2 class="mb-3 mt-6 text-sm font-bold uppercase tracking-wide text-charcoal-300">On deck — called</h2>
                    <BaseCard padding="none">
                        <ul v-if="calledEntries.length" class="divide-y divide-white/5">
                            <li v-for="entry in calledEntries" :key="entry.id" class="flex items-center gap-3 px-4 py-3">
                                <div class="flex -space-x-2">
                                    <PlayerAvatar v-for="player in entry.players" :key="player.id" :player="player" size="sm" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-base font-semibold text-white">{{ entry.label }}</p>
                                    <p class="text-xs text-charcoal-300">{{ entry.players_count }} players</p>
                                </div>
                                <Badge color="volt" size="sm" dot>Called</Badge>
                            </li>
                        </ul>
                        <p v-else class="px-4 py-6 text-center text-sm text-charcoal-300">No one waiting to be confirmed.</p>
                    </BaseCard>
                </section>

                <!-- Waiting line with drag-and-drop -->
                <section class="lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-charcoal-300">
                            Waiting line
                            <span class="ml-1 font-normal normal-case text-charcoal-400">· drag to reorder</span>
                        </h2>
                        <Badge color="gray" size="sm">{{ waitingEntries.length }} in line</Badge>
                    </div>

                    <BaseCard padding="none">
                        <ul v-if="waitingEntries.length" class="divide-y divide-white/5">
                            <li
                                v-for="(entry, index) in waitingEntries"
                                :key="entry.id"
                                class="flex cursor-grab items-center gap-3 px-4 py-3 transition select-none active:cursor-grabbing"
                                :class="
                                    dragIndex === index
                                        ? 'bg-volt-300/10 opacity-60'
                                        : overIndex === index
                                          ? 'border-t-2 border-volt-300/70'
                                          : 'hover:bg-white/[0.03]'
                                "
                                draggable="true"
                                @dragstart="onDragStart(index)"
                                @dragover.prevent="onDragOver(index)"
                                @dragleave="onDragLeave(index)"
                                @drop.prevent="onDrop"
                                @dragend="resetDrag"
                            >
                                <svg class="size-4 shrink-0 text-charcoal-500" viewBox="0 0 24 24" fill="currentColor">
                                    <circle cx="9" cy="6" r="1.6" />
                                    <circle cx="15" cy="6" r="1.6" />
                                    <circle cx="9" cy="12" r="1.6" />
                                    <circle cx="15" cy="12" r="1.6" />
                                    <circle cx="9" cy="18" r="1.6" />
                                    <circle cx="15" cy="18" r="1.6" />
                                </svg>

                                <span
                                    class="inline-grid size-11 shrink-0 place-items-center rounded-full text-sm font-black"
                                    :class="index === 0 ? 'bg-volt-300 text-ink' : 'bg-white/10 text-charcoal-200'"
                                >
                                    {{ ordinal(index) }}
                                </span>

                                <div class="flex -space-x-2">
                                    <PlayerAvatar v-for="player in entry.players" :key="player.id" :player="player" size="sm" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-base font-semibold text-white">{{ entry.label }}</p>
                                    <p class="text-xs text-charcoal-300">{{ entry.players_count }} players</p>
                                </div>

                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    :loading="skipId === entry.id"
                                    @click="removeEntry(entry)"
                                >
                                    Remove
                                </BaseButton>
                            </li>
                        </ul>
                        <p v-else class="px-4 py-8 text-center text-sm text-charcoal-300">
                            Line is empty — use “Call next” or “Add player”.
                        </p>
                    </BaseCard>
                </section>
            </div>
        </main>

        <footer class="mt-10 px-4 pb-4 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Pickle Ta Bai! by Claire</p>
        </footer>

        <!-- Score entry modal -->
        <BaseModal v-model="scoreOpen" title="Record score">
            <p class="mb-4 text-sm text-charcoal-300">Enter the final scores. The higher score wins — no ties.</p>

            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">Team A</span>
                    <input
                        v-model.number="scoreA"
                        type="number"
                        min="0"
                        class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-3 text-center text-2xl font-black text-white transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                    />
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">Team B</span>
                    <input
                        v-model.number="scoreB"
                        type="number"
                        min="0"
                        class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-3 text-center text-2xl font-black text-white transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                    />
                </label>
            </div>

            <p v-if="scoreA === scoreB && scoreA > 0" class="mt-3 text-xs text-volt-200">
                Scores can't tie — adjust one of them.
            </p>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="ghost" @click="scoreOpen = false">Cancel</BaseButton>
                    <BaseButton :loading="busy" :disabled="scoreA === scoreB" @click="submitScore">
                        Save score
                    </BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Add player modal -->
        <BaseModal v-model="addOpen" title="Add player to the line">
            <p class="mb-4 text-sm text-charcoal-300">
                Search by name or phone number, then tap a player to put them at the back of the line.
            </p>

            <div class="relative">
                <svg
                    class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-charcoal-400"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" d="M21 21l-4.5-4.5" />
                </svg>
                <input
                    v-model="query"
                    type="search"
                    placeholder="Name or phone…"
                    class="w-full rounded-xl border border-white/10 bg-navy-950/60 py-2.5 pl-10 pr-4 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                    @input="search($event.target.value)"
                />
            </div>

            <div class="mt-4 space-y-2">
                <p v-if="searching" class="py-3 text-center text-sm text-charcoal-300">Searching…</p>

                <template v-else-if="searchResults.length">
                    <button
                        v-for="user in searchResults"
                        :key="user.id"
                        class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-left transition hover:border-volt-300/40 hover:bg-white/[0.06]"
                        :disabled="addingId === user.id"
                        @click="addPlayer(user)"
                    >
                        <PlayerAvatar :player="user" />
                        <span class="min-w-0 flex-1">
                            <span class="block break-words text-base font-bold text-white">{{ user.name }}</span>
                            <span class="block text-xs text-charcoal-300">{{ user.phone || user.email }}</span>
                        </span>
                        <Badge v-if="user.skill_rating != null" color="navy" size="sm">{{ user.skill_rating.toFixed(1) }}</Badge>
                        <span v-if="addingId === user.id" class="text-xs text-volt-200">Adding…</span>
                        <svg class="size-4 text-volt-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                        </svg>
                    </button>
                </template>

                <p v-else-if="query.trim() && !searching" class="py-3 text-center text-sm text-charcoal-300">
                    No players match “{{ query }}”.
                </p>
            </div>
        </BaseModal>
    </div>
</template>
