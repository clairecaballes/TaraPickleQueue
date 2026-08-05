<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';

import { useTarapickleStore } from '../stores/tarapickle';
import PlayerFace from './PlayerFace.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import SkillChip from './ui/SkillChip.vue';

const emit = defineEmits(['toast']);

const store = useTarapickleStore();
const { sortedQueues } = storeToRefs(store);

const selectedQueueId = ref(null); // null = all sessions

const visibleQueues = computed(() =>
    selectedQueueId.value
        ? sortedQueues.value.filter((queue) => queue.id === selectedQueueId.value)
        : sortedQueues.value,
);

function playerStatus(player, queue) {
    if (player.status === 'called') {
        return { label: 'On deck', cls: 'bg-volt-300/15 text-volt-200 ring-volt-300/30', dot: 'bg-volt-300' };
    }

    if (player.status === 'active') {
        const court = queue.courts.find((c) => c.id === player.courtId);

        if (court?.activeMatch) {
            return { label: 'Playing', cls: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30', dot: 'bg-emerald-400' };
        }

        return { label: 'Confirmed', cls: 'bg-sky-400/10 text-sky-200 ring-sky-400/30', dot: 'bg-sky-300' };
    }

    if (player.paused) {
        return { label: 'Taking a break', cls: 'bg-white/10 text-charcoal-200 ring-white/15', dot: 'bg-charcoal-300' };
    }

    return { label: 'Waiting', cls: 'bg-white/5 text-charcoal-200 ring-white/10', dot: 'bg-charcoal-500' };
}

/** 1-based position inside the waiting line (paused players keep their spot). */
function positionOf(queue, player) {
    const waitingOrder = queue.players.filter((p) => p.status === 'waiting');

    return waitingOrder.findIndex((p) => p.id === player.id) + 1;
}

function isWaiting(player) {
    return player.status === 'waiting';
}

function togglePause(queue, player) {
    store.togglePause(queue.id, player.id);
    emit('toast', player.paused ? `${player.name} is taking a break ☕` : `${player.name} is back in rotation.`);
}

function remove(queue, player) {
    store.removePlayer(queue.id, player.id);
    emit('toast', `${player.name} was removed from ${queue.name}.`);
}

function toggleQueueOpen(queue) {
    store.toggleQueueOpen(queue.id);
    emit('toast', queue.open ? `${queue.name} is open for new players.` : `${queue.name} is closed — no new entries.`);
}

function selectQueue(id) {
    selectedQueueId.value = id;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Session picker -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wide text-charcoal-300">Session</span>
            <button
                type="button"
                class="min-h-12 rounded-full border px-4 py-2 text-sm font-semibold transition"
                :class="
                    selectedQueueId === null
                        ? 'border-volt-300/60 bg-volt-300/15 text-volt-200'
                        : 'border-white/10 bg-white/[0.03] text-charcoal-200 hover:border-white/25 hover:text-white'
                "
                @click="selectQueue(null)"
            >
                All sessions
            </button>
            <button
                v-for="queue in sortedQueues"
                :key="queue.id"
                type="button"
                class="inline-flex min-h-12 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                :class="
                    selectedQueueId === queue.id
                        ? 'border-volt-300/60 bg-volt-300/15 text-volt-200'
                        : 'border-white/10 bg-white/[0.03] text-charcoal-200 hover:border-white/25 hover:text-white'
                "
                @click="selectQueue(queue.id)"
            >
                {{ queue.name }}
                <span
                    class="rounded-full px-2 py-0.5 text-[10px]"
                    :class="selectedQueueId === queue.id ? 'bg-volt-300/20 text-volt-100' : 'bg-white/10 text-charcoal-200'"
                >
                    {{ queue.players.filter((p) => p.status === 'waiting' && !p.paused).length }}
                </span>
            </button>
        </div>

        <template v-if="visibleQueues.length">
            <section
                v-for="queue in visibleQueues"
                :key="queue.id"
                class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] shadow-card"
            >
                <!-- Queue header with End Queue control -->
                <header class="flex flex-wrap items-center gap-3 border-b border-white/10 bg-navy-950/40 px-4 py-3 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <h3 class="flex flex-wrap items-center gap-2 break-words text-lg font-black leading-snug tracking-tight text-white">
                            {{ queue.name }}
                            <Badge v-if="!queue.open" color="gray" size="sm">Closed</Badge>
                            <Badge
                                v-if="queue.courts.some((court) => court.activeMatch)"
                                color="green"
                                size="sm"
                                dot
                            >
                                LIVE
                            </Badge>
                        </h3>
                        <p class="text-xs text-charcoal-400">
                            {{ queue.players.length }} players · {{ queue.courts.length }} court{{ queue.courts.length === 1 ? '' : 's' }}
                        </p>
                    </div>

                    <BaseButton
                        variant="ghost"
                        size="sm"
                        :title="queue.open ? 'Close the queue to new players' : 'Reopen the queue for new players'"
                        @click="toggleQueueOpen(queue)"
                    >
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path v-if="queue.open" stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 018 0v3m1 0h1a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6a2 2 0 012-2h13z" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0l2-2m-2 2l-2-2M8 11V8a4 4 0 018 0v3M6 11h12a2 2 0 012 2v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5a2 2 0 012-2z" />
                        </svg>
                        {{ queue.open ? 'End Queue' : 'Reopen' }}
                    </BaseButton>
                </header>

                <!-- Player list — table on wider screens, stacked rows on phones
                     so names wrap and nothing truncates down to 320px. -->
                <template v-if="queue.players.length">
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-white/10 bg-navy-950/30 text-[10px] uppercase tracking-wider text-charcoal-400">
                                    <th class="px-4 py-2.5 text-left font-semibold">Pos</th>
                                    <th class="px-2 py-2.5 text-left font-semibold">Player</th>
                                    <th class="px-2 py-2.5 text-left font-semibold">Skill</th>
                                    <th class="px-2 py-2.5 text-left font-semibold">Status</th>
                                    <th class="px-2 py-2.5 text-right font-semibold">Record</th>
                                    <th class="px-4 py-2.5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr
                                    v-for="player in queue.players"
                                    :key="player.id"
                                    class="transition hover:bg-white/[0.03]"
                                    :class="player.paused ? 'opacity-60' : ''"
                                >
                                    <td class="px-4 py-2.5">
                                        <span
                                            v-if="isWaiting(player)"
                                            class="inline-grid size-7 place-items-center rounded-full text-[11px] font-black"
                                            :class="
                                                positionOf(queue, player) === 1
                                                    ? 'bg-volt-300 text-ink shadow-[0_2px_10px_-2px_rgb(255_214_10/0.5)]'
                                                    : 'bg-white/10 text-charcoal-200'
                                            "
                                        >
                                            {{ positionOf(queue, player) }}
                                        </span>
                                        <span v-else class="text-xs font-bold text-charcoal-500">—</span>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <PlayerFace :player="player" size="sm" />
                                            <div class="min-w-0">
                                                <p class="break-words text-sm font-semibold text-white">{{ player.name }}</p>
                                                <p class="text-[10px] text-charcoal-400">
                                                    {{ queue.name }}
                                                    <span v-if="player.fixedPairId" class="text-sky-300">· 🔗 paired</span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <SkillChip :skill="player.skill" />
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset"
                                            :class="playerStatus(player, queue).cls"
                                        >
                                            <span class="size-1.5 rounded-full" :class="playerStatus(player, queue).dot" />
                                            {{ playerStatus(player, queue).label }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-2.5 text-right">
                                        <span class="text-xs font-bold text-white">{{ player.wins }}W</span>
                                        <span class="text-xs text-charcoal-400"> · {{ player.gamesPlayed }}G</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-3">
                                            <button
                                                class="grid size-12 place-items-center rounded-full transition"
                                                :class="
                                                    player.paused
                                                        ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                                        : 'text-charcoal-500 hover:bg-volt-300/10 hover:text-volt-200'
                                                "
                                                :title="player.paused ? `Resume ${player.name}` : `Pause ${player.name}`"
                                                :disabled="!isWaiting(player)"
                                                @click="togglePause(queue, player)"
                                            >
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path v-if="player.paused" stroke-linecap="round" stroke-linejoin="round" d="M12 8v5l3 2" />
                                                    <circle v-else-if="!player.paused" cx="12" cy="12" r="9" />
                                                    <path v-else stroke-linecap="round" d="M12 7v5l3 2" />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="isWaiting(player)"
                                                class="grid size-12 place-items-center rounded-full text-charcoal-500 transition hover:bg-red-400/10 hover:text-red-300"
                                                title="Remove from queue"
                                                :disabled="!isWaiting(player)"
                                                @click="remove(queue, player)"
                                            >
                                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile card rows (below 768px) -->
                    <ul class="divide-y divide-white/5 md:hidden">
                        <li
                            v-for="player in queue.players"
                            :key="player.id"
                            class="flex flex-wrap items-center gap-3 px-4 py-3"
                            :class="player.paused ? 'opacity-60' : ''"
                        >
                            <span
                                v-if="isWaiting(player)"
                                class="grid size-11 shrink-0 place-items-center rounded-full text-sm font-black"
                                :class="
                                    positionOf(queue, player) === 1
                                        ? 'bg-volt-300 text-ink shadow-[0_2px_10px_-2px_rgb(255_214_10/0.5)]'
                                        : 'bg-white/10 text-charcoal-200'
                                "
                            >
                                {{ positionOf(queue, player) }}
                            </span>
                            <span v-else class="grid size-11 shrink-0 place-items-center rounded-full text-sm font-bold text-charcoal-500">—</span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <PlayerFace :player="player" size="sm" />
                                    <div class="min-w-0">
                                        <p class="break-words text-base font-semibold text-white">{{ player.name }}</p>
                                        <p class="break-words text-[11px] text-charcoal-400">
                                            {{ queue.name }}
                                            <span v-if="player.fixedPairId" class="text-sky-300">· 🔗 paired</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset"
                                        :class="playerStatus(player, queue).cls"
                                    >
                                        <span class="size-1.5 rounded-full" :class="playerStatus(player, queue).dot" />
                                        {{ playerStatus(player, queue).label }}
                                    </span>
                                    <SkillChip :skill="player.skill" />
                                    <span class="ml-auto text-xs font-bold text-white">{{ player.wins }}W · {{ player.gamesPlayed }}G</span>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <button
                                    class="grid size-12 place-items-center rounded-full transition"
                                    :class="
                                        player.paused
                                            ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                            : 'text-charcoal-500 hover:bg-volt-300/10 hover:text-volt-200'
                                    "
                                    :title="player.paused ? `Resume ${player.name}` : `Pause ${player.name}`"
                                    :disabled="!isWaiting(player)"
                                    @click="togglePause(queue, player)"
                                >
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path v-if="player.paused" stroke-linecap="round" stroke-linejoin="round" d="M12 8v5l3 2" />
                                        <circle v-else-if="!player.paused" cx="12" cy="12" r="9" />
                                        <path v-else stroke-linecap="round" d="M12 7v5l3 2" />
                                    </svg>
                                </button>
                                <button
                                    v-if="isWaiting(player)"
                                    class="grid size-12 place-items-center rounded-full text-charcoal-500 transition hover:bg-red-400/10 hover:text-red-300"
                                    title="Remove from queue"
                                    :disabled="!isWaiting(player)"
                                    @click="remove(queue, player)"
                                >
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </template>

                <p v-else class="px-5 py-10 text-center text-sm text-charcoal-300">
                    No players in {{ queue.name }} yet — add some from the Queues tab.
                </p>
            </section>
        </template>

        <!-- Empty state -->
        <section
            v-else
            class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-white/15 px-6 py-16 text-center"
        >
            <span class="grid size-14 place-items-center rounded-2xl bg-white/5 text-3xl">🗂️</span>
            <div>
                <h3 class="text-lg font-black text-white">No queues yet</h3>
                <p class="text-sm text-charcoal-300">Create a session on the Queues tab and players will show up here.</p>
            </div>
        </section>
    </div>
</template>
