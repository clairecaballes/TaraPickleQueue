<script setup>
import { computed, ref } from 'vue';

import { toPng } from 'html-to-image';
import { downloadDataUrl } from '../utils/format';
import BaseButton from './ui/BaseButton.vue';
import BaseModal from './ui/BaseModal.vue';

/**
 * 9:16 (1080×1920) leaderboard story card, exportable as a PNG via
 * html-to-image. Rows show Rank, Player, Wins and W/L% — no skill columns —
 * with a shareable header banner, timestamp and social CTA.
 */
const props = defineProps({
    modelValue: Boolean,
    /** Ranked rows: { rank, name, wins, gamesPlayed, avatarEmoji, queueName } */
    rows: { type: Array, default: () => [] },
    totalMatches: { type: Number, default: 0 },
    title: { type: String, default: 'Court Leaderboard' },
});

const emit = defineEmits(['update:modelValue', 'toast']);

const CARD_W = 1080;
const CARD_H = 1920;

/** Top 10 fits the 9:16 canvas comfortably at a readable size. */
const cardRows = computed(() => props.rows.slice(0, 10));

const dateLabel = computed(() =>
    new Date().toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }),
);

function winRate(row) {
    return row.gamesPlayed ? Math.round((row.wins / row.gamesPlayed) * 100) : 0;
}

function rankColor(rank) {
    if (rank === 1) return '#ffd60a';
    if (rank === 2) return '#d0d0d4';
    if (rank === 3) return '#f59e0b';
    return '#8b8b95';
}

const cardRef = ref(null);
const exporting = ref(false);

async function exportPng() {
    if (!cardRef.value || exporting.value) {
        return;
    }

    exporting.value = true;

    try {
        // The card is rendered pre-scaled for the preview; force the cloned
        // node back to its natural 1080×1920 size for the capture.
        const dataUrl = await toPng(cardRef.value, {
            width: CARD_W,
            height: CARD_H,
            pixelRatio: 1,
            style: { transform: 'none', 'transform-origin': 'top left' },
            cacheBust: true,
        });

        downloadDataUrl(dataUrl, 'tarapickle-story-leaderboard.png');
        emit('toast', 'Story leaderboard downloaded as PNG 🎉');
    } catch {
        emit('toast', 'Could not export the story card in this browser.');
    } finally {
        exporting.value = false;
    }
}

async function shareStory() {
    if (!cardRef.value || exporting.value) {
        return;
    }

    exporting.value = true;

    try {
        const dataUrl = await toPng(cardRef.value, {
            width: CARD_W,
            height: CARD_H,
            pixelRatio: 1,
            style: { transform: 'none', 'transform-origin': 'top left' },
            cacheBust: true,
        });

        const blob = await (await fetch(dataUrl)).blob();
        const file = new File([blob], 'tarapickle-story-leaderboard.png', { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'Tara Pickle — Court Leaderboard',
                text: `${props.title} 🏆 ${cardRows.value.length} ranked · ${props.totalMatches} games played`,
            });

            return;
        }

        downloadDataUrl(dataUrl, 'tarapickle-story-leaderboard.png');
        emit('toast', 'Sharing unsupported here — story downloaded instead 🎉');
    } catch (err) {
        if (err?.name !== 'AbortError') {
            emit('toast', 'Could not share the story card in this browser.');
        }
    } finally {
        exporting.value = false;
    }
}
</script>

<template>
    <BaseModal
        :model-value="modelValue"
        :title="`Share “${title}” as a story`"
        max-width="max-w-2xl"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <p class="mb-4 text-sm text-charcoal-300">
            9:16 story card, sized for Instagram — download the PNG or share it straight from your phone.
            Shows rank, wins and win rate; skill ratings are left off for a clean social look.
        </p>

        <!-- Preview — the real card is 1080×1920, scaled 0.25× for the modal -->
        <div class="mx-auto w-[270px]">
            <div
                class="overflow-hidden rounded-2xl border border-white/10 shadow-2xl"
                style="aspect-ratio: 9 / 16"
            >
                <div
                    ref="cardRef"
                    class="story-card flex flex-col overflow-hidden"
                    style="width: 1080px; height: 1920px; transform: scale(0.25); transform-origin: top left"
                >
                    <!-- Brand header (shrink-0 so rows get the flexible space) -->
                    <div
                        class="flex shrink-0 flex-col items-center"
                        style="padding: 84px 80px 0; background: linear-gradient(180deg, #12203a 0%, #0b1426 100%)"
                    >
                        <div class="flex items-center gap-6">
                            <div
                                class="grid place-items-center rounded-2xl"
                                style="width: 96px; height: 96px; background: #ffd60a"
                            >
                                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#0b1426" stroke-width="2">
                                    <circle cx="8" cy="6" r="3" />
                                    <circle cx="16" cy="8" r="2.5" />
                                    <circle cx="8" cy="18" r="2.5" />
                                    <path stroke-linecap="round" d="M10.5 6h3.5a3 3 0 013 3v0M12 18h3a2 2 0 002-2v-6" />
                                </svg>
                            </div>
                            <div>
                                <p style="color: #ffffff; font-family: Bungee, sans-serif; font-size: 64px; line-height: 1.1; letter-spacing: -0.01em">
                                    Tara<span style="color: #ffd60a">Pickle</span>
                                </p>
                                <p style="color: #8b8b95; font-size: 26px; font-weight: 600; margin-top: 8px">
                                    Fair queues · cute critters · live stats
                                </p>
                            </div>
                        </div>

                        <!-- Banner -->
                        <div
                            class="mt-14 w-full rounded-3xl border"
                            style="border-color: rgb(255 214 10 / 0.5); background: rgb(255 214 10 / 0.1); padding: 34px 40px"
                        >
                            <p style="color: #ffffff; font-family: Bungee, sans-serif; font-size: 76px; line-height: 1.08">
                                {{ title }}
                            </p>
                            <p style="color: #ffd60a; font-size: 30px; font-weight: 800; margin-top: 14px">
                                {{ dateLabel }} · {{ cardRows.length }} ranked · {{ totalMatches }} games
                            </p>
                        </div>
                    </div>

                    <!-- Rows (flex-1 spreads them across the 9:16 canvas) -->
                    <div
                        class="flex min-h-0 flex-1 flex-col justify-between"
                        style="background: #0b1426; padding: 0 80px"
                    >
                        <div
                            v-for="row in cardRows"
                            :key="`${row.queueId}-${row.id}`"
                            class="flex items-center gap-8"
                            style="border-bottom: 2px solid rgb(255 255 255 / 0.08); padding: 22px 0"
                        >
                            <span
                                class="grid shrink-0 place-items-center rounded-full"
                                :style="{ width: '84px', height: '84px', fontFamily: 'Bungee, sans-serif', fontSize: '40px', color: '#0b1426', background: rankColor(row.rank) }"
                            >
                                {{ row.rank }}
                            </span>
                            <span
                                class="grid place-items-center rounded-full"
                                style="width: 84px; height: 84px; flex-shrink: 0; font-size: 44px; background: rgb(255 255 255 / 0.08)"
                            >
                                {{ row.avatarEmoji ?? '🐾' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p style="color: #ffffff; font-size: 44px; font-weight: 800; line-height: 1.15; overflow-wrap: anywhere">
                                    {{ row.name }}
                                </p>
                            </div>
                            <div class="text-right" style="flex-shrink: 0">
                                <p style="color: #ffd60a; font-family: Bungee, sans-serif; font-size: 46px; line-height: 1">
                                    {{ row.wins }}
                                    <span style="color: #8b8b95; font-family: 'Instrument Sans', sans-serif; font-size: 26px; font-weight: 700">W</span>
                                </p>
                                <p style="color: #b1b1b8; font-size: 28px; font-weight: 700; margin-top: 8px">
                                    {{ winRate(row) }}% W/L
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Social CTA + footer (shrink-0) -->
                    <div
                        class="flex shrink-0 flex-col items-center"
                        style="background: #0b1426; padding: 44px 80px 52px"
                    >
                        <div
                            class="flex items-center gap-6 rounded-full"
                            style="background: #ffd60a; padding: 26px 52px"
                        >
                            <span style="color: #0b1426; font-size: 34px; font-weight: 900">Follow @tarapickle</span>
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0b1426" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-5-5m5 5l-5 5" />
                            </svg>
                        </div>
                        <p style="color: #6f6f7a; font-size: 26px; font-weight: 600; margin-top: 28px">
                            Tara Pickle by Claire · fair queues, cute critters, live stats
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3">
                <BaseButton variant="ghost" @click="$emit('update:modelValue', false)">Close</BaseButton>
                <BaseButton variant="secondary" :loading="exporting" @click="shareStory">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v6a2 2 0 002 2h12a2 2 0 002-2v-6M16 6l-4-4m0 0L8 6m4-4v12" />
                    </svg>
                    Share
                </BaseButton>
                <BaseButton :loading="exporting" @click="exportPng">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4" />
                    </svg>
                    Download PNG
                </BaseButton>
            </div>
        </template>
    </BaseModal>
</template>
