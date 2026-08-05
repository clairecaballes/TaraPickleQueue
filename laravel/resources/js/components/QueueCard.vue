<script setup>
import { computed, ref } from 'vue';

import { useTarapickleStore } from '../stores/tarapickle';
import { SKILLS } from '../utils/skills';
import { downloadQueueCsv, queuePdf, queuePng, shareQueue } from '../utils/snapshot';
import AvatarPickerModal from './AvatarPickerModal.vue';
import CourtPanel from './CourtPanel.vue';
import PlayerFace from './PlayerFace.vue';
import QueueShareModal from './QueueShareModal.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseModal from './ui/BaseModal.vue';

const props = defineProps({
    queue: { type: Object, required: true },
    now: { type: Number, default: () => Date.now() },
});

const emit = defineEmits(['toast', 'announce']);

const store = useTarapickleStore();

/* ------------------------------------------------------------------ *
 * Derived lists — every section reads from the same store, so a status
 * change or avatar swap is reflected in the pool, on deck and board at once.
 * ------------------------------------------------------------------ */
const waiting = computed(() => props.queue.players.filter((p) => p.status === 'waiting'));
const anyLive = computed(() => props.queue.courts.some((court) => court.activeMatch));

/** Accordion — only one queue is expanded at a time (store holds the id). */
const expanded = computed(() => store.expandedQueueId === props.queue.id);

function toggleExpanded() {
    store.toggleExpanded(props.queue.id);
}

/* ------------------------------------------------------------------ *
 * Per-queue Share & Snapshot
 * ------------------------------------------------------------------ */
const snapshotOpen = ref(false);
const qrOpen = ref(false);

async function openShare() {
    const result = await shareQueue(props.queue);

    if (result === 'copied') {
        emit('toast', `${props.queue.name} summary + link copied 📋`);
    } else if (result === null) {
        emit('toast', 'Could not share from this browser.');
    }
}

function exportPng() {
    snapshotOpen.value = false;
    const count = queuePng(props.queue);

    emit('toast', count ? `${props.queue.name} snapshot downloaded as PNG 🎉` : 'Nothing to export yet — add players first.');
}

function exportPdf() {
    snapshotOpen.value = false;
    const count = queuePdf(props.queue);

    emit('toast', count ? 'Print dialog opened — choose “Save as PDF” 📄' : 'Nothing to export yet — add players first.');
}

function exportCsv() {
    snapshotOpen.value = false;
    const count = downloadQueueCsv(props.queue);

    emit('toast', count ? `${props.queue.name} results downloaded as CSV 🎉` : 'Nothing to export yet — add players first.');
}
const createdLabel = computed(() => {
    const date = new Date(props.queue.createdAt);

    return `${date.toLocaleDateString([], { month: 'short', day: 'numeric' })} · ${date.toLocaleTimeString([], {
        hour: 'numeric',
        minute: '2-digit',
    })}`;
});

function winRate(player) {
    return player.gamesPlayed ? Math.round((player.wins / player.gamesPlayed) * 100) : 0;
}

/** Wins desc, win rate as the tie-breaker, then name. */
const leaderboard = computed(() =>
    [...props.queue.players]
        .sort((a, b) => b.wins - a.wins || winRate(b) - winRate(a) || a.name.localeCompare(b.name))
        .map((player, index) => ({ ...player, rank: index + 1 })),
);

function rankClass(rank) {
    // Rank 2 uses a constant silver so it stays legible in both themes.
    if (rank === 1) return 'bg-volt-300 text-ink shadow-[0_2px_10px_-2px_rgb(255_214_10/0.6)]';
    if (rank === 2) return 'bg-[#d0d0d4] text-ink';
    if (rank === 3) return 'bg-amber-500 text-ink';
    return 'bg-white/10 text-charcoal-300';
}

/* ------------------------------------------------------------------ *
 * Session-level actions
 * ------------------------------------------------------------------ */
const playerName = ref('');

function addPlayer() {
    const player = store.addPlayer(props.queue.id, playerName.value);

    if (player) {
        playerName.value = '';
        emit('toast', `${player.name} is in the line 🐾`);
    }
}

function remove(player) {
    store.removePlayer(props.queue.id, player.id);
    emit('toast', `${player.name} was removed from ${props.queue.name}.`);
}

function toggleQueueOpen() {
    store.toggleQueueOpen(props.queue.id);

    emit(
        'toast',
        props.queue.open ? `${props.queue.name} is open for new players.` : `${props.queue.name} is closed — no new entries.`,
    );
}

function addCourt() {
    const court = store.addCourt(props.queue.id);

    if (court) {
        emit('toast', `${court.label} added to ${props.queue.name}.`);
    }
}

/* Avatar picker */
const pickerOpen = ref(false);
const pickerPlayer = ref(null);

function openPicker(player) {
    pickerPlayer.value = player;
    pickerOpen.value = true;
}

function onPick(animal) {
    if (pickerPlayer.value) {
        store.setAvatar(props.queue.id, pickerPlayer.value.id, animal);
        emit('toast', `${pickerPlayer.value.name} is now a ${animal.name.toLowerCase()} 🐾`);
    }
}

/* Fixed pair picker */
const pairOpen = ref(false);
const pairPlayer = ref(null);

const pairCandidates = computed(() =>
    waiting.value.filter(
        (player) => player.id !== pairPlayer.value?.id && !player.fixedPairId && !player.paused,
    ),
);

function pairPartner(player) {
    return (
        props.queue.players.find(
            (other) => other.fixedPairId === player.fixedPairId && other.id !== player.id,
        ) ?? null
    );
}

function openPair(player) {
    pairPlayer.value = player;
    pairOpen.value = true;
}

function pairWith(partner) {
    store.setFixedPair(props.queue.id, pairPlayer.value.id, partner.id);
    pairOpen.value = false;
    emit('toast', `${pairPlayer.value.name} & ${partner.name} are now a fixed pair 🔗`);
}

function unpair(player) {
    store.unsetFixedPair(props.queue.id, player.id);
    emit('toast', `${player.name}'s fixed pair was cleared.`);
}

function togglePause(player) {
    store.togglePause(props.queue.id, player.id);
    emit('toast', player.paused ? `${player.name} is taking a break ☕` : `${player.name} is back in rotation.`);
}

function setSkill(player, event) {
    store.setSkill(props.queue.id, player.id, event.target.value || null);
}

/* Delete — quick inline confirmation */
const confirmingDelete = ref(false);
</script>

<template>
    <article
        class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] shadow-card backdrop-blur-sm transition-all duration-200"
        :class="anyLive ? 'border-volt-300/30 shadow-glow' : 'hover:border-white/20'"
    >
        <!-- Header -->
        <header class="flex flex-wrap items-center gap-3 border-b border-white/10 px-3 py-3 sm:px-4">
            <!-- Expand / collapse chevron -->
            <button
                type="button"
                class="grid size-12 shrink-0 place-items-center rounded-full transition hover:bg-white/10"
                :title="expanded ? 'Collapse queue' : 'Expand queue'"
                :aria-label="expanded ? 'Collapse queue' : 'Expand queue'"
                :aria-expanded="expanded"
                @click="toggleExpanded"
            >
                <svg
                    class="size-4 text-charcoal-300 transition-transform duration-200"
                    :class="expanded ? 'rotate-180' : ''"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <!-- Title — clicking expands/collapses too -->
            <button
                type="button"
                class="group flex min-w-0 flex-1 items-center gap-3 rounded-xl p-1 text-left transition hover:bg-white/[0.03]"
                @click="toggleExpanded"
            >
                <div
                    class="grid size-10 shrink-0 place-items-center rounded-xl text-volt-300 ring-1 ring-volt-300/30"
                    :class="anyLive ? 'bg-volt-300/20 animate-pulse' : 'bg-volt-300/10'"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M4 15h16M9 4v16M15 4v16" />
                        <path stroke-linecap="round" d="M2 6v4M2 14v4M22 6v4M22 14v4" />
                    </svg>
                </div>

                <div class="min-w-0">
                    <h3 class="flex flex-wrap items-center gap-2 break-words text-lg font-black leading-snug tracking-tight text-white">
                        {{ queue.name }}
                        <Badge v-if="!queue.open" color="gray" size="sm">Closed</Badge>
                    </h3>
                    <p class="break-words text-xs text-charcoal-400">
                        Created {{ createdLabel }} · {{ queue.players.length }} player{{ queue.players.length === 1 ? '' : 's' }} ·
                        {{ queue.courts.length }} court{{ queue.courts.length === 1 ? '' : 's' }}
                    </p>
                </div>
            </button>

            <Badge v-if="anyLive" color="green" size="sm" dot>LIVE</Badge>

            <!-- Share this queue -->
            <button
                class="grid size-12 place-items-center rounded-full text-charcoal-400 transition hover:bg-sky-400/10 hover:text-sky-200"
                title="Share this queue"
                aria-label="Share this queue"
                @click="openShare"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v6a2 2 0 002 2h12a2 2 0 002-2v-6M16 6l-4-4m0 0L8 6m4-4v12" />
                </svg>
            </button>

            <!-- Snapshot: PNG / PDF / CSV -->
            <div class="relative">
                <button
                    class="grid size-12 place-items-center rounded-full text-charcoal-400 transition hover:bg-volt-300/10 hover:text-volt-200"
                    title="Snapshot this queue — PNG, PDF or CSV"
                    aria-label="Snapshot this queue"
                    @click="snapshotOpen = !snapshotOpen"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h2l2-2h6l2 2h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm9 8a3 3 0 100-6 3 3 0 000 6z" />
                    </svg>
                </button>

                <div v-if="snapshotOpen" class="fixed inset-0 z-20" @click="snapshotOpen = false" />
                <Transition
                    enter-active-class="transition-all duration-150"
                    enter-from-class="opacity-0 -translate-y-1 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                >
                    <div
                        v-if="snapshotOpen"
                        class="absolute right-0 top-full z-30 mt-2 w-48 overflow-hidden rounded-xl border border-white/10 bg-navy-900 p-1.5 shadow-2xl"
                    >
                        <button
                            class="flex min-h-12 w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-semibold text-white transition hover:bg-white/10"
                            @click="exportPng"
                        >
                            <span class="text-base">📸</span> PNG snapshot
                        </button>
                        <button
                            class="flex min-h-12 w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-semibold text-white transition hover:bg-white/10"
                            @click="exportPdf"
                        >
                            <span class="text-base">📄</span> PDF (print / save)
                        </button>
                        <button
                            class="flex min-h-12 w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-semibold text-white transition hover:bg-white/10"
                            @click="exportCsv"
                        >
                            <span class="text-base">📊</span> CSV results
                        </button>
                    </div>
                </Transition>
            </div>

            <!-- Scan to view this queue -->
            <button
                class="grid size-12 place-items-center rounded-full text-charcoal-400 transition hover:bg-volt-300/10 hover:text-volt-200"
                title="Scan to view this queue — live on-deck board"
                aria-label="Scan to view this queue"
                @click="qrOpen = true"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                    <path stroke-linecap="round" d="M10 3.5h3M10 20.5h3M3.5 10v3M20.5 10v3M14 3.5v2M20.5 6v2M17 10h1.5M10 14h.01M6 20.5v-4M20.5 17v3" />
                </svg>
            </button>

            <!-- End / close queue toggle -->
            <BaseButton
                v-if="!confirmingDelete"
                variant="ghost"
                size="sm"
                :title="queue.open ? 'Close the queue to new players' : 'Reopen the queue for new players'"
                @click="toggleQueueOpen"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path v-if="queue.open" stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 018 0v3m1 0h1a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6a2 2 0 012-2h13z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0l2-2m-2 2l-2-2M8 11V8a4 4 0 018 0v3M6 11h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                </svg>
                {{ queue.open ? 'End Queue' : 'Reopen' }}
            </BaseButton>

            <Transition
                enter-active-class="transition-all duration-200"
                enter-from-class="opacity-0 scale-90"
                enter-to-class="opacity-100 scale-100"
            >
                <div
                    v-if="confirmingDelete"
                    class="flex items-center gap-2 rounded-full border border-red-400/40 bg-red-400/10 px-3 py-1.5"
                >
                    <span class="text-xs font-semibold text-red-200">Delete this queue?</span>
                    <button
                        class="min-h-12 min-w-12 rounded-full bg-red-500 px-3 py-1.5 text-sm font-bold text-white transition hover:bg-red-400"
                        @click="store.deleteQueue(queue.id)"
                    >
                        Delete
                    </button>
                    <button
                        class="min-h-12 min-w-12 rounded-full px-3 py-1.5 text-sm font-semibold text-charcoal-300 transition hover:bg-white/10 hover:text-white"
                        @click="confirmingDelete = false"
                    >
                        Keep
                    </button>
                </div>
            </Transition>

            <button
                v-if="!confirmingDelete"
                class="grid size-12 place-items-center rounded-full text-charcoal-400 transition hover:bg-red-400/10 hover:text-red-300"
                title="Delete queue"
                aria-label="Delete queue"
                @click="confirmingDelete = true"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0l-.7 12.1A2 2 0 0116.3 21H7.7a2 2 0 01-2-1.9L5 7m4 4v6m6-6v6" />
                </svg>
            </button>
        </header>

        <!-- Collapsed summary — one glance, expand to manage -->
        <button
            v-if="!expanded"
            type="button"
            class="flex w-full flex-wrap items-center gap-3 px-4 py-3 text-left transition hover:bg-white/[0.04] sm:px-5"
            @click="toggleExpanded"
        >
            <div class="flex -space-x-2">
                <PlayerFace v-for="player in waiting.slice(0, 5)" :key="player.id" :player="player" size="sm" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-charcoal-300">
                <span class="font-bold text-white">{{ waiting.length }} waiting</span>
                · {{ queue.courts.filter((c) => c.activeMatch).length }} live court{{ queue.courts.filter((c) => c.activeMatch).length === 1 ? '' : 's' }}
                · {{ queue.players.length }} player{{ queue.players.length === 1 ? '' : 's' }}
            </div>
            <span class="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-volt-200">
                Expand
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </span>
        </button>

        <div v-else class="grid gap-6 p-4 sm:p-5 lg:grid-cols-[1fr_320px]">
            <!-- Left: live flow -->
            <div class="space-y-5">
                <!-- Quick add -->
                <form v-if="queue.open" class="flex gap-2" @submit.prevent="addPlayer">
                    <div class="relative flex-1">
                        <svg
                            class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-charcoal-400"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="10" cy="8" r="3.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 20c.8-3.2 3.3-5 6.5-5s5.7 1.8 6.5 5M17 8h5m-2.5-2.5V10.5" />
                        </svg>
                        <input
                            v-model="playerName"
                            type="text"
                            maxlength="40"
                            placeholder="Player name… (guest, no sign-up)"
                            class="w-full rounded-full border border-white/10 bg-navy-950/60 py-3 pl-10 pr-4 text-base text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                            @keydown.enter.prevent="addPlayer"
                        />
                    </div>
                    <BaseButton type="submit" :disabled="!playerName.trim()">Add to Queue</BaseButton>
                </form>

                <div
                    v-else
                    class="flex items-center gap-3 rounded-xl border border-dashed border-white/15 bg-white/[0.02] px-4 py-3"
                >
                    <svg class="size-4 shrink-0 text-charcoal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0l2-2m-2 2l-2-2M8 11V8a4 4 0 018 0v3M6 11h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                    </svg>
                    <p class="text-sm text-charcoal-300">
                        <span class="font-semibold text-white">Queue closed.</span> New entries are paused — manage the
                        active courts below, then reopen when you're ready.
                    </p>
                    <BaseButton size="sm" variant="secondary" class="ml-auto shrink-0" @click="toggleQueueOpen">
                        Reopen
                    </BaseButton>
                </div>

                <!-- Courts -->
                <section>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-charcoal-300">
                            <span
                                class="grid size-5 place-items-center rounded-md text-volt-300 ring-1 ring-volt-300/30"
                                :class="anyLive ? 'bg-volt-300/20 animate-pulse' : 'bg-volt-300/10'"
                            >
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M4 15h16M9 4v16M15 4v16" />
                                </svg>
                            </span>
                            Courts
                            <Badge color="gray" size="sm">{{ queue.courts.length }} active</Badge>
                        </h4>
                        <div class="ml-auto">
                            <BaseButton
                                v-if="queue.courts.length < 4"
                                variant="ghost"
                                size="sm"
                                @click="addCourt"
                            >
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" d="M12 5v14m-7-7h14" />
                                </svg>
                                Add court
                            </BaseButton>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                        <CourtPanel
                            v-for="court in queue.courts"
                            :key="court.id"
                            :queue="queue"
                            :court="court"
                            :now="now"
                            @toast="(message) => emit('toast', message)"
                            @announce="(message) => emit('announce', message)"
                        />
                    </div>

                    <p class="mt-2 text-[11px] text-charcoal-400">
                        Multiple courts run at once — when one match ends, its court auto-fills with the next fair four
                        while the others keep playing.
                    </p>
                </section>

                <!-- Waiting pool -->
                <section>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h4 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-charcoal-300">
                            Waiting pool
                            <Badge color="gray" size="sm">{{ waiting.length }} in line</Badge>
                        </h4>
                        <p class="ml-auto text-[11px] text-charcoal-400">
                            Fair pick — fewest games first, paused players auto-skip.
                        </p>
                    </div>

                    <TransitionGroup tag="ul" name="list" class="space-y-2">
                        <li
                            v-for="player in waiting"
                            :key="player.id"
                            class="group flex flex-wrap items-center gap-3 rounded-xl border px-3 py-2 transition sm:flex-nowrap"
                            :class="
                                player.paused
                                    ? 'border-white/5 bg-navy-950/20 opacity-60'
                                    : 'border-white/5 bg-navy-950/30 hover:border-volt-300/25 hover:bg-navy-950/50'
                            "
                        >
                            <PlayerFace :player="player" size="md" editable @edit="openPicker" />

                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-1.5 break-words text-base font-semibold text-white">
                                    {{ player.name }}
                                    <span
                                        v-if="player.fixedPairId"
                                        class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-sky-400/10 px-1.5 py-0.5 text-[9px] font-bold text-sky-200 ring-1 ring-sky-400/30"
                                        :title="`Fixed pair with ${pairPartner(player)?.name ?? 'partner'}`"
                                    >
                                        🔗 {{ pairPartner(player)?.name.split(' ')[0] ?? 'paired' }}
                                    </span>
                                </p>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-[11px] text-charcoal-400">
                                        {{ player.wins }}w · {{ player.gamesPlayed }}g
                                    </span>
                                    <SkillChip v-if="player.skill" :skill="player.skill" />
                                    <Badge v-if="player.paused" color="gray" size="sm" dot>Taking a break</Badge>
                                </div>
                            </div>

                            <!-- Skill rating tag -->
                            <select
                                :value="player.skill ?? ''"
                                class="min-h-12 rounded-full border border-white/10 bg-navy-950/70 px-3 py-2 text-sm font-semibold text-charcoal-200 transition focus:border-volt-300/60 focus:outline-none"
                                :title="`Set ${player.name}'s skill rating`"
                                @change="setSkill(player, $event)"
                            >
                                <option value="">Level</option>
                                <option v-for="skill in SKILLS" :key="skill.value" :value="skill.value">
                                    {{ skill.emoji }} {{ skill.label }}
                                </option>
                            </select>

                            <!-- Fixed pair -->
                            <button
                                class="grid size-12 place-items-center rounded-full transition"
                                :class="
                                    player.fixedPairId
                                        ? 'bg-sky-400/15 text-sky-200 ring-1 ring-sky-400/40'
                                        : 'text-charcoal-500 hover:bg-sky-400/10 hover:text-sky-200'
                                "
                                :title="player.fixedPairId ? `Clear fixed pair with ${pairPartner(player)?.name ?? ''}` : 'Tag a fixed pair — they always team up'"
                                :aria-label="player.fixedPairId ? 'Clear fixed pair' : 'Tag fixed pair'"
                                @click="player.fixedPairId ? unpair(player) : openPair(player)"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 13.5a4 4 0 015.7 0l2.3 2.3a4 4 0 01-5.7 5.7l-1-1M13.5 10.5a4 4 0 00-5.7 0l-2.3 2.3a4 4 0 005.7 5.7l1-1" />
                                </svg>
                            </button>

                            <!-- Pause / BRB -->
                            <button
                                class="grid size-12 place-items-center rounded-full transition"
                                :class="
                                    player.paused
                                        ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                        : 'text-charcoal-500 hover:bg-volt-300/10 hover:text-volt-200'
                                "
                                :title="player.paused ? 'Resume ' + player.name : `Pause ${player.name} (skipped by the randomizer)`"
                                :aria-label="player.paused ? 'Resume player' : 'Pause player'"
                                @click="togglePause(player)"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path v-if="player.paused" stroke-linecap="round" stroke-linejoin="round" d="M12 8v5l3 2" />
                                    <circle v-else-if="!player.paused" cx="12" cy="12" r="9" />
                                    <path v-else stroke-linecap="round" d="M12 7v5l3 2" />
                                </svg>
                            </button>

                            <button
                                class="grid size-12 place-items-center rounded-full text-charcoal-500 transition hover:bg-red-400/10 hover:text-red-300"
                                title="Remove from queue"
                                aria-label="Remove from queue"
                                @click="remove(player)"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </li>
                    </TransitionGroup>

                    <div v-if="!waiting.length" class="rounded-xl border border-dashed border-white/10 px-4 py-5 text-center">
                        <p class="text-sm font-semibold text-charcoal-300">The line is empty</p>
                        <p class="text-xs text-charcoal-400">Add players above, then hit Randomize Court on any court.</p>
                    </div>
                </section>
            </div>

            <!-- Right: live leaderboard -->
            <aside class="min-w-0">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-charcoal-300">Live leaderboard</h4>
                    <Badge color="gray" size="sm">{{ queue.players.length }} player{{ queue.players.length === 1 ? '' : 's' }}</Badge>
                </div>

                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/10 bg-navy-950/40 text-[10px] uppercase tracking-wider text-charcoal-400">
                                <th class="px-3 py-2 text-left font-semibold">#</th>
                                <th class="px-2 py-2 text-left font-semibold">Player</th>
                                <th class="px-2 py-2 text-right font-semibold">Wins</th>
                                <th class="px-2 py-2 text-right font-semibold">Games</th>
                                <th class="px-3 py-2 text-right font-semibold">Win %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="row in leaderboard" :key="row.id" class="transition hover:bg-white/[0.03]">
                                <td class="px-3 py-2">
                                    <span
                                        class="grid size-6 place-items-center rounded-full text-[11px] font-black"
                                        :class="rankClass(row.rank)"
                                    >
                                        {{ row.rank }}
                                    </span>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center gap-2">
                                        <PlayerFace :player="row" size="sm" editable @edit="openPicker" />
                                        <span class="min-w-0 break-words text-sm font-semibold text-white">{{ row.name }}</span>
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right font-black text-volt-300">{{ row.wins }}</td>
                                <td class="px-2 py-2 text-right text-xs text-charcoal-300">{{ row.gamesPlayed }}</td>
                                <td class="px-3 py-2 text-right">
                                    <span
                                        class="text-xs font-bold"
                                        :class="winRate(row) >= 50 ? 'text-emerald-300' : 'text-charcoal-200'"
                                    >
                                        {{ winRate(row) }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="!queue.players.length"
                    class="mt-2 rounded-xl border border-dashed border-white/10 px-3 py-5 text-center text-xs text-charcoal-400"
                >
                    No stats yet — play a match to light up the board.
                </p>
            </aside>
        </div>

        <!-- Avatar picker -->
        <AvatarPickerModal
            v-model="pickerOpen"
            :current-url="pickerPlayer?.avatarUrl ?? ''"
            :title="`Pick ${pickerPlayer?.name ?? 'a'} new avatar`"
            @select="onPick"
        />

        <!-- Fixed pair picker -->
        <BaseModal v-model="pairOpen" :title="`Fixed pair — pick ${pairPlayer?.name ?? 'a'} partner`">
            <p class="mb-4 text-sm text-charcoal-300">
                Fixed pairs always stay on the same team when both are summoned. Pick an unpaired player to team up with.
            </p>

            <ul v-if="pairCandidates.length" class="max-h-64 space-y-2 overflow-y-auto pr-1">
                <li v-for="player in pairCandidates" :key="player.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-left transition hover:border-sky-400/40 hover:bg-white/[0.06]"
                        @click="pairWith(player)"
                    >
                        <PlayerFace :player="player" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block break-words text-base font-semibold text-white">{{ player.name }}</span>
                            <span class="text-[11px] text-charcoal-400">
                                {{ player.wins }} win{{ player.wins === 1 ? '' : 's' }} · {{ player.gamesPlayed }} game{{ player.gamesPlayed === 1 ? '' : 's' }}
                            </span>
                        </span>
                        <svg class="size-4 shrink-0 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 13.5a4 4 0 015.7 0l2.3 2.3a4 4 0 01-5.7 5.7l-1-1M13.5 10.5a4 4 0 00-5.7 0l-2.3 2.3a4 4 0 005.7 5.7l1-1" />
                        </svg>
                    </button>
                </li>
            </ul>

            <p v-else class="rounded-xl border border-dashed border-white/10 px-4 py-5 text-center text-sm text-charcoal-300">
                No unpaired players waiting right now.
            </p>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="ghost" @click="pairOpen = false">Cancel</BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Scan to view this queue (QR + live on-deck board) -->
        <QueueShareModal
            v-model="qrOpen"
            :queue="queue"
            @toast="(message) => emit('toast', message)"
        />
    </article>
</template>
