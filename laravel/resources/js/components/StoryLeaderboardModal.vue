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
 *
 * Design language: dark glassmorphism backdrops punched up with crisp white
 * graphic lines — white corner brackets, white-outline rank badges, white
 * divider rules and high-contrast type — so the card pops when posted to an
 * Instagram Story.
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

/**
 * Rank badge: the podium keeps its volt/silver/bronze fill but every badge
 * gains a sharp white outline ring so ranks read instantly against the dark
 * glass card — white-outline aesthetic for the rest of the field.
 */
function rankBadgeStyle(rank) {
    const podium = rank <= 3;

    return {
        width: '84px',
        height: '84px',
        fontFamily: 'Bungee, sans-serif',
        fontSize: '40px',
        color: podium ? '#0b1426' : '#ffffff',
        background: podium ? rankColor(rank) : 'transparent',
        border: '4px solid #ffffff',
        boxShadow: podium ? '0 0 0 5px rgb(255 255 255 / 0.22)' : '0 0 0 5px rgb(255 255 255 / 0.1)',
    };
}

const cardRef = ref(null);
const exporting = ref(false);

const FILE_NAME = 'pickle-ta-bai-story-leaderboard.png';

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

        downloadDataUrl(dataUrl, FILE_NAME);
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
        const file = new File([blob], FILE_NAME, { type: 'image/png' });

        if (navigator.canShare?.({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'Pickle Ta Bai! — Court Leaderboard',
                text: `${props.title} 🏆 ${cardRows.value.length} ranked · ${props.totalMatches} games played`,
            });

            return;
        }

        downloadDataUrl(dataUrl, FILE_NAME);
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
            Shows rank, wins and win rate; skill ratings are left off for a clean social look. Crisp white
            line accents keep the standings sharp when posted.
        </p>

        <!-- Preview — the real card is 1080×1920, scaled 0.25× for the modal -->
        <div class="mx-auto w-[270px]">
            <div
                class="overflow-hidden rounded-2xl border border-white/10 shadow-2xl"
                style="aspect-ratio: 9 / 16"
            >
                <div
                    ref="cardRef"
                    class="story-card relative flex flex-col overflow-hidden"
                    style="width: 1080px; height: 1920px; transform: scale(0.25); transform-origin: top left"
                >
                    <!-- White graphic corner brackets -->
                    <div
                        class="absolute"
                        style="top: 36px; left: 36px; width: 110px; height: 110px; border-top: 4px solid rgb(255 255 255 / 0.9); border-left: 4px solid rgb(255 255 255 / 0.9); border-top-left-radius: 26px"
                    />
                    <div
                        class="absolute"
                        style="top: 36px; right: 36px; width: 110px; height: 110px; border-top: 4px solid rgb(255 255 255 / 0.9); border-right: 4px solid rgb(255 255 255 / 0.9); border-top-right-radius: 26px"
                    />
                    <div
                        class="absolute"
                        style="bottom: 36px; left: 36px; width: 110px; height: 110px; border-bottom: 4px solid rgb(255 255 255 / 0.9); border-left: 4px solid rgb(255 255 255 / 0.9); border-bottom-left-radius: 26px"
                    />
                    <div
                        class="absolute"
                        style="bottom: 36px; right: 36px; width: 110px; height: 110px; border-bottom: 4px solid rgb(255 255 255 / 0.9); border-right: 4px solid rgb(255 255 255 / 0.9); border-bottom-right-radius: 26px"
                    />

                    <!-- Brand header (shrink-0 so rows get the flexible space) -->
                    <div
                        class="relative flex shrink-0 flex-col items-center"
                        style="padding: 96px 80px 0; background: linear-gradient(180deg, #152238 0%, #0b1426 100%)"
                    >
                        <!-- White court-line motif: center line + service circle -->
                        <div
                            class="absolute"
                            style="left: 50%; top: 40px; width: 2px; height: 150px; background: linear-gradient(180deg, rgb(255 255 255 / 0.5), rgb(255 255 255 / 0.06)); transform: translateX(-50%)"
                        />
                        <div
                            class="absolute"
                            style="left: 50%; top: 115px; width: 96px; height: 96px; border-radius: 999px; border: 3px solid rgb(255 255 255 / 0.22); transform: translate(-50%, -50%)"
                        />

                        <div class="relative flex items-center gap-6">
                            <div
                                class="grid shrink-0 place-items-center rounded-2xl"
                                style="width: 96px; height: 96px; background: #ffd60a; border: 4px solid #ffffff; box-shadow: 0 0 0 5px rgb(255 255 255 / 0.18)"
                            >
                                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#0b1426" stroke-width="2">
                                    <circle cx="8" cy="6" r="3" />
                                    <circle cx="16" cy="8" r="2.5" />
                                    <circle cx="8" cy="18" r="2.5" />
                                    <path stroke-linecap="round" d="M10.5 6h3.5a3 3 0 013 3v0M12 18h3a2 2 0 002-2v-6" />
                                </svg>
                            </div>
                            <div>
                                <p style="color: #ffffff; font-family: Bungee, sans-serif; font-size: 58px; line-height: 1.08; letter-spacing: -0.01em">
                                    Pickle Ta <span style="color: #ffd60a">Bai!</span>
                                </p>
                                <div
                                    style="height: 6px; width: 100%; border-radius: 999px; background: linear-gradient(90deg, #ffd60a 0%, rgb(255 255 255 / 0.25) 100%); margin-top: 10px"
                                />
                                <p style="color: #c6c9d4; font-size: 24px; font-weight: 600; margin-top: 12px">
                                    Fair queues · live stats · cute critters
                                </p>
                            </div>
                        </div>

                        <!-- Banner — dark glass + sharp white border -->
                        <div
                            class="mt-12 w-full rounded-3xl"
                            style="border: 3px solid rgb(255 255 255 / 0.85); background: linear-gradient(180deg, rgb(255 255 255 / 0.08), rgb(255 255 255 / 0.03)); padding: 28px 40px"
                        >
                            <p style="color: #ffffff; font-family: Bungee, sans-serif; font-size: 72px; line-height: 1.08">
                                {{ title }}
                            </p>
                            <p style="color: #ffffff; font-size: 28px; font-weight: 800; margin-top: 12px">
                                {{ dateLabel }} · {{ cardRows.length }} ranked · {{ totalMatches }} games
                            </p>
                        </div>
                    </div>

                    <!-- Rows (flex-1 spreads them across the 9:16 canvas) -->
                    <div
                        class="flex min-h-0 flex-1 flex-col justify-between"
                        style="background: linear-gradient(180deg, #0b1426 0%, #0e1931 100%); padding: 0 80px"
                    >
                        <div
                            v-for="row in cardRows"
                            :key="`${row.queueId}-${row.id}`"
                            class="flex items-center gap-8"
                            style="border-bottom: 3px solid rgb(255 255 255 / 0.22); padding: 20px 0"
                        >
                            <span class="grid shrink-0 place-items-center rounded-full" :style="rankBadgeStyle(row.rank)">
                                {{ row.rank }}
                            </span>
                            <span
                                class="grid shrink-0 place-items-center rounded-full"
                                style="width: 84px; height: 84px; font-size: 42px; background: rgb(255 255 255 / 0.08); border: 3px solid rgb(255 255 255 / 0.85)"
                            >
                                {{ row.avatarEmoji ?? '🐾' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p style="color: #ffffff; font-size: 42px; font-weight: 800; line-height: 1.15; overflow-wrap: anywhere">
                                    {{ row.name }}
                                </p>
                            </div>
                            <div class="text-right" style="flex-shrink: 0">
                                <p style="color: #ffffff; font-family: Bungee, sans-serif; font-size: 44px; line-height: 1">
                                    {{ row.wins }}
                                    <span style="color: #b8becb; font-family: 'Instrument Sans', sans-serif; font-size: 24px; font-weight: 700">W</span>
                                </p>
                                <p style="color: #d4d8e0; font-size: 26px; font-weight: 700; margin-top: 8px">
                                    {{ winRate(row) }}% W/L
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Social CTA + footer (shrink-0) -->
                    <div
                        class="flex shrink-0 flex-col items-center"
                        style="background: #0b1426; padding: 40px 80px 60px"
                    >
                        <div
                            class="flex items-center gap-6 rounded-full"
                            style="border: 3px solid #ffffff; background: rgb(255 255 255 / 0.04); padding: 22px 48px"
                        >
                            <span style="color: #ffffff; font-size: 32px; font-weight: 900">Follow @pickletabai</span>
                            <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#ffd60a" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-5-5m5 5l-5 5" />
                            </svg>
                        </div>
                        <p style="color: #8b93a3; font-size: 24px; font-weight: 600; margin-top: 26px">
                            Pickle Ta Bai! by Claire · fair queues, live stats
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
