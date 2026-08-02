<script setup>
import { computed } from 'vue';

import { queueLink } from '../utils/snapshot';
import PlayerFace from './PlayerFace.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseModal from './ui/BaseModal.vue';

const props = defineProps({
    modelValue: Boolean,
    queue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue', 'toast']);

/** Deep link to this exact queue — the QR encodes it. */
const url = computed(() => queueLink(props.queue.id));

const qrImageUrl = computed(
    () =>
        `https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=10&data=${encodeURIComponent(url.value)}`,
);

/** Every court's live state — called / confirmed players, match status. */
const courtRows = computed(() =>
    (props.queue.courts ?? []).map((court) => {
        const onDeck = props.queue.players.filter(
            (player) => player.courtId === court.id && player.status !== 'waiting',
        );
        const names = onDeck.map((player) => player.name.split(' ')[0]).join(', ');

        return {
            court,
            onDeck,
            names,
            live: Boolean(court.activeMatch),
        };
    }),
);

const waiting = computed(() => props.queue.players.filter((player) => player.status === 'waiting'));

async function copyLink() {
    try {
        await navigator.clipboard.writeText(url.value);
        emit('toast', 'Queue link copied 📋');
    } catch {
        emit('toast', 'Could not copy the link.');
    }
}
</script>

<template>
    <BaseModal
        :model-value="modelValue"
        :title="`Scan to view ${queue.name}`"
        max-width="max-w-2xl"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div class="grid gap-5 sm:grid-cols-[auto_1fr]">
            <!-- QR -->
            <div class="flex flex-col items-center gap-3">
                <div class="rounded-2xl border border-white/10 bg-white p-3 shadow-card">
                    <img
                        :src="qrImageUrl"
                        :alt="`QR code for ${queue.name}`"
                        class="size-52 rounded-xl sm:size-56"
                        loading="lazy"
                    />
                </div>
                <p class="max-w-[240px] text-center text-[11px] leading-relaxed text-charcoal-300">
                    Point a phone camera here to open this session's live board — players on the
                    sidelines can check who's on deck and their spot in line.
                </p>
                <BaseButton size="sm" variant="secondary" class="w-full" @click="copyLink">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="11" height="11" rx="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15V5a2 2 0 012-2h10" />
                    </svg>
                    Copy link
                </BaseButton>
            </div>

            <!-- Live queue dashboard -->
            <div class="min-w-0 space-y-4">
                <!-- Courts / on deck -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-charcoal-300">
                            Courts · on deck
                        </h4>
                        <Badge color="gray" size="sm">{{ courtRows.length }} court{{ courtRows.length === 1 ? '' : 's' }}</Badge>
                    </div>

                    <ul class="space-y-2">
                        <li
                            v-for="row in courtRows"
                            :key="row.court.id"
                            class="rounded-xl border px-3 py-2.5"
                            :class="row.live ? 'border-volt-300/40 bg-volt-300/[0.07]' : 'border-white/10 bg-white/[0.02]'"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-grid size-6 shrink-0 place-items-center rounded-md text-[11px] font-black text-navy-950"
                                    :class="row.live ? 'animate-pulse bg-volt-300' : 'bg-white/15 text-white'"
                                >
                                    {{ row.court.label.replace('Court ', '') }}
                                </span>
                                <span class="truncate text-sm font-bold text-white">{{ row.court.label }}</span>
                                <Badge v-if="row.live" color="green" size="sm" dot>LIVE</Badge>
                                <Badge v-else-if="row.onDeck.length" color="volt" size="sm" dot>
                                    {{ row.onDeck.length }} on deck
                                </Badge>
                                <Badge v-else color="gray" size="sm">free</Badge>
                            </div>
                            <p v-if="row.names" class="mt-1.5 text-xs font-semibold text-volt-100">
                                🎾 {{ row.names }}
                            </p>
                        </li>
                    </ul>

                    <p v-if="!courtRows.length" class="rounded-xl border border-dashed border-white/10 px-3 py-4 text-center text-xs text-charcoal-400">
                        No courts in this session yet.
                    </p>
                </div>

                <!-- Waiting line -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-charcoal-300">
                            In line
                        </h4>
                        <Badge color="gray" size="sm">{{ waiting.length }} waiting</Badge>
                    </div>

                    <ul v-if="waiting.length" class="max-h-44 space-y-1.5 overflow-y-auto pr-1">
                        <li
                            v-for="(player, index) in waiting"
                            :key="player.id"
                            class="flex items-center gap-2.5 rounded-lg bg-white/[0.03] px-2.5 py-1.5"
                        >
                            <span
                                class="grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-black"
                                :class="index === 0 ? 'bg-volt-300 text-navy-950' : 'bg-white/10 text-charcoal-200'"
                            >
                                {{ index + 1 }}
                            </span>
                            <PlayerFace :player="player" size="sm" />
                            <span class="min-w-0 flex-1 truncate text-xs font-semibold text-white">
                                {{ player.name }}
                            </span>
                            <span
                                v-if="player.paused"
                                class="rounded-full bg-white/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-charcoal-300"
                            >
                                BRB
                            </span>
                        </li>
                    </ul>

                    <p v-else class="rounded-xl border border-dashed border-white/10 px-3 py-4 text-center text-xs text-charcoal-400">
                        The line is empty — everyone is playing or the session is closed.
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3">
                <BaseButton variant="ghost" @click="$emit('update:modelValue', false)">Close</BaseButton>
            </div>
        </template>
    </BaseModal>
</template>
