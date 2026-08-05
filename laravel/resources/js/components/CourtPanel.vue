<script setup>
import { computed, ref } from 'vue';

import { useTarapickleStore } from '../stores/tarapickle';
import { elapsedSince, formatDuration } from '../utils/format';
import PlayerFace from './PlayerFace.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import BaseModal from './ui/BaseModal.vue';

const props = defineProps({
    queue: { type: Object, required: true },
    court: { type: Object, required: true },
    now: { type: Number, default: () => Date.now() },
});

const emit = defineEmits(['toast', 'announce']);

const store = useTarapickleStore();

/* ------------------------------------------------------------------ *
 * Derived state for this court only — all read from the shared store.
 * ------------------------------------------------------------------ */
const match = computed(() => props.court.activeMatch);

/** Called (summoned) + confirmed players assigned to this court. */
const onDeck = computed(() =>
    props.queue.players.filter(
        (player) => player.courtId === props.court.id && (player.status === 'called' || player.status === 'active'),
    ),
);

const calledCount = computed(() => onDeck.value.filter((player) => player.status === 'called').length);
const confirmedCount = computed(() => onDeck.value.filter((player) => player.status === 'active').length);

/** Waiting players who are NOT paused — the pool a court can draw from. */
const waiting = computed(() => props.queue.players.filter((player) => player.status === 'waiting' && !player.paused));

const canRandomize = computed(() => !match.value && onDeck.value.length === 0 && waiting.value.length >= 4);

const randomizeHint = computed(() => {
    if (match.value) {
        return 'A match is live on this court — finish it before calling again.';
    }

    if (onDeck.value.length) {
        return 'Players are on deck — confirm, swap or send them back first.';
    }

    if (waiting.value.length < 4) {
        const missing = 4 - waiting.value.length;

        return `Add ${missing} more player${missing === 1 ? '' : 's'} to fill ${props.court.label}.`;
    }

    return '';
});

const matchElapsed = computed(() =>
    match.value ? formatDuration(elapsedSince(match.value.startedAt, props.now)) : '',
);

function playerById(id) {
    return props.queue.players.find((player) => player.id === id) ?? null;
}

function teamPlayers(team) {
    return team.playerIds.map(playerById).filter(Boolean);
}

/* ------------------------------------------------------------------ *
 * Actions
 * ------------------------------------------------------------------ */
function randomize() {
    const result = store.randomizeCourt(props.queue.id, props.court.id);

    if (!result) {
        return;
    }

    const names = result.players.map((player) => player.name).join(', ');

    // Per-court sound toggle — silent courts never announce their calls.
    if (props.court.announce) {
        emit('announce', `${props.court.label}: ${names} are On Deck!`);
    }

    emit('toast', `${props.court.label} on deck: ${names} — head to the court!`);
}

/** Per-court "Close Call Sound" toggle — flips this court's announcements. */
function toggleSound() {
    store.toggleCourtSound(props.queue.id, props.court.id);

    emit('toast', props.court.announce ? `${props.court.label} calls announced 🔊` : `${props.court.label} call sound is off.`);
}

/* ------------------------------------------------------------------ *
 * Close / cancel a court call
 * ------------------------------------------------------------------ */
const closing = ref(false);
let closingTimer = null;

function requestClose() {
    if (closing.value) {
        window.clearTimeout(closingTimer);
        closeCall();

        return;
    }

    closing.value = true;
    closingTimer = window.setTimeout(() => {
        closing.value = false;
    }, 3200);
}

/** Send every called / confirmed player on this court back to the line. */
function closeCall() {
    closing.value = false;
    const returned = store.cancelCourtCall(props.queue.id, props.court.id);

    if (!returned) {
        emit('toast', `${props.court.label} is already clear.`);

        return;
    }

    const names = returned.map((player) => player.name).join(', ');

    if (props.court.announce) {
        emit('announce', `${props.court.label} call closed — ${names} back in line.`);
    }

    emit('toast', `${props.court.label} call closed — ${names} back in line.`);
}

function confirm(player) {
    store.confirmPlayer(props.queue.id, player.id);

    if (store.findQueue(props.queue.id)?.courts.find((c) => c.id === props.court.id)?.activeMatch) {
        emit('toast', `${props.court.label} is full — match is live! 🎾`);
    }
}

function undo(player) {
    store.cancelCall(props.queue.id, player.id);
    emit('toast', `${player.name} is back in the line.`);
}

/* ------------------------------------------------------------------ *
 * Change Player — substitute or defer a called player
 * ------------------------------------------------------------------ */
const swapOpen = ref(false);
const swapTarget = ref(null);

const swapSubstitutes = computed(() => waiting.value.filter((player) => player.id !== swapTarget.value?.id));

function openSwap(player) {
    swapTarget.value = player;
    swapOpen.value = true;
}

function swapIn(substitute) {
    const ok = store.swapPlayer(props.queue.id, swapTarget.value.id, substitute.id);

    swapOpen.value = false;

    if (ok) {
        emit('toast', `${substitute.name} takes ${swapTarget.value.name}'s spot on ${props.court.label}.`);
    }
}

function deferSwap() {
    if (swapTarget.value) {
        undo(swapTarget.value);
    }

    swapOpen.value = false;
}

/* ------------------------------------------------------------------ *
 * Finish match — score keeper
 * ------------------------------------------------------------------ */
const finishOpen = ref(false);
const finishWinner = ref(null);
const finishScores = ref({ A: 11, B: 0 });

function openFinish() {
    finishWinner.value = null;
    finishScores.value = { A: 11, B: 0 };
    finishOpen.value = true;
}

const canFinish = computed(
    () => Boolean(finishWinner.value) && !(finishScores.value.A === finishScores.value.B && finishScores.value.A > 0),
);

function submitFinish() {
    const result = store.finishMatch(props.queue.id, props.court.id, {
        winner: finishWinner.value,
        scoreA: finishScores.value.A,
        scoreB: finishScores.value.B,
    });

    finishOpen.value = false;

    if (!result) {
        return;
    }

    emit(
        'toast',
        `Match done — ${result.winners.join(' & ')} take the W ${result.scoreA}–${result.scoreB}! 🏆`,
    );

    if (result.refilled.length) {
        const names = result.refilled.map((player) => player.name).join(', ');

        if (props.court.announce) {
            emit('announce', `${props.court.label}: ${names} are On Deck!`);
        }

        emit('toast', `${props.court.label} auto-filled — ${names} are on deck!`);
    }
}

function removeCourt() {
    if (store.removeCourt(props.queue.id, props.court.id)) {
        emit('toast', `${props.court.label} removed from the session.`);
    }
}
</script>

<template>
    <section
        class="rounded-2xl border bg-navy-950/40 p-3.5 transition sm:p-4"
        :class="match ? 'border-volt-300/30 shadow-glow' : 'border-white/10 hover:border-white/20'"
    >
        <!-- Court header -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <span
                class="inline-grid size-10 shrink-0 place-items-center rounded-lg text-base font-black"
                :class="match ? 'animate-pulse bg-volt-300 text-ink' : 'bg-charcoal-800/50 text-charcoal-50'"
            >
                {{ court.label.replace('Court ', '') }}
            </span>
            <div class="min-w-0 flex-1">
                <h4 class="break-words text-base font-black leading-snug tracking-tight text-white">{{ court.label }}</h4>
                <p class="text-xs text-charcoal-400">
                    {{ waiting.length }} ready · {{ onDeck.length }} on deck
                </p>
            </div>

            <span
                v-if="match"
                class="inline-flex items-center gap-1.5 font-mono text-xs font-bold text-volt-200"
            >
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" d="M12 7v5l3 2" />
                </svg>
                {{ matchElapsed }}
            </span>

            <Badge v-if="match" color="green" size="sm" dot>LIVE</Badge>

            <!-- Per-court close-call sound toggle -->
            <button
                class="grid size-12 place-items-center rounded-full transition"
                :class="
                    props.court.announce
                        ? 'text-volt-300 hover:bg-volt-300/10'
                        : 'text-charcoal-500 hover:bg-white/10 hover:text-charcoal-200'
                "
                :title="props.court.announce ? 'Mute this court\'s call announcements' : 'Turn on this court\'s call announcements'"
                :aria-label="props.court.announce ? 'Mute court call sound' : 'Turn on court call sound'"
                @click="toggleSound"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 10v4a2 2 0 002 2h2l4 4V4L8 8H6a2 2 0 00-2 2z" />
                    <path v-if="!props.court.announce" stroke-linecap="round" d="M16 9l4 6m0-6l-4 6" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5a5 5 0 010 7M18 6a8.5 8.5 0 010 12" />
                </svg>
            </button>

            <!-- Close / wrap up a pending call -->
            <button
                v-if="!match && onDeck.length"
                class="min-h-12 min-w-12 rounded-full px-3 py-1.5 text-sm font-bold transition"
                :class="
                    closing
                        ? 'bg-red-500 text-white shadow-[0_2px_10px_-2px_rgb(239_68_68/0.6)]'
                        : 'text-charcoal-400 hover:bg-red-400/10 hover:text-red-300'
                "
                :title="closing ? 'Tap again to send everyone back to the line' : 'Close this call — send players back to the line'"
                :aria-label="closing ? 'Confirm closing this call' : 'Close this call'"
                @click="requestClose"
            >
                <svg v-if="!closing" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
                {{ closing ? 'Close call?' : '' }}
            </button>

            <button
                v-if="!match && !onDeck.length"
                class="grid size-12 place-items-center rounded-full text-charcoal-500 transition hover:bg-red-400/10 hover:text-red-300"
                title="Remove this court"
                aria-label="Remove this court"
                @click="removeCourt"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <!-- Live match + score keeper -->
        <div v-if="match" class="animate-pop-in space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div
                    v-for="(team, i) in match.teams"
                    :key="team.key"
                    class="rounded-xl border px-3 py-2.5"
                    :class="i === 0 ? 'border-volt-300/30 bg-volt-300/[0.07]' : 'border-sky-400/30 bg-sky-400/[0.07]'"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-[10px] font-black uppercase tracking-wider"
                            :class="i === 0 ? 'text-volt-200' : 'text-sky-200'"
                        >
                            Team {{ team.key }}
                        </span>
                        <span class="text-lg font-black leading-none text-white">{{ team.score ?? 0 }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <div class="flex -space-x-1.5">
                            <PlayerFace v-for="player in teamPlayers(team)" :key="player.id" :player="player" size="sm" />
                        </div>
                        <p class="min-w-0 flex-1 break-words text-right text-xs font-bold leading-snug text-white">
                            {{ teamPlayers(team).map((p) => p.name.split(' ')[0]).join(' & ') }}
                        </p>
                    </div>
                </div>
            </div>

            <BaseButton variant="secondary" block size="sm" @click="openFinish">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                    />
                </svg>
                Game over — record the winner &amp; score
            </BaseButton>
        </div>

        <!-- On deck (called / confirmed) with Change Player -->
        <div v-else-if="onDeck.length" class="animate-pop-in space-y-2">
            <div class="flex items-center justify-between">
                <h5 class="text-[11px] font-bold uppercase tracking-wide text-charcoal-300">
                    On deck
                    <Badge color="volt" size="sm">{{ confirmedCount }}/{{ onDeck.length }} confirmed</Badge>
                </h5>
            </div>

            <TransitionGroup tag="ul" name="list" class="space-y-1.5">
                <li
                    v-for="player in onDeck"
                    :key="player.id"
                    class="flex flex-wrap items-center gap-2.5 rounded-xl border px-3 py-2 transition"
                    :class="
                        player.status === 'called'
                            ? 'border-volt-300/25 bg-volt-300/[0.06]'
                            : 'border-emerald-400/20 bg-emerald-400/[0.05]'
                    "
                >
                    <PlayerFace :player="player" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-sm font-semibold text-white">{{ player.name }}</p>
                        <p class="text-xs" :class="player.status === 'called' ? 'text-volt-200/80' : 'text-emerald-300/90'">
                            {{ player.status === 'called' ? 'Awaiting confirm' : 'Confirmed — waiting for 4' }}
                        </p>
                    </div>

                    <!-- Change Player: substitute or defer -->
                    <button
                        class="grid size-12 place-items-center rounded-full text-charcoal-400 transition hover:bg-sky-400/15 hover:text-sky-200"
                        title="Change player — substitute or defer"
                        aria-label="Change player"
                        @click="openSwap(player)"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </button>

                    <BaseButton v-if="player.status === 'called'" size="sm" class="animate-pulse-ring" @click="confirm(player)">
                        Ready
                    </BaseButton>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2 py-0.5 text-[10px] font-bold text-emerald-300"
                    >
                        <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        In
                    </span>
                </li>
            </TransitionGroup>
        </div>

        <!-- Call the next four -->
        <div v-else class="flex items-center gap-2">
            <BaseButton
                size="sm"
                class="group/rand"
                :disabled="!canRandomize"
                :title="randomizeHint"
                @click="randomize"
            >
                <svg
                    class="size-3.5 transition-transform duration-300 group-hover/rand:rotate-[25deg]"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <rect x="3" y="3" width="7" height="7" rx="1.5" />
                    <rect x="14" y="14" width="7" height="7" rx="1.5" />
                    <path stroke-linecap="round" d="M10 6.5h5a4 4 0 014 4V14m0-6v0" />
                </svg>
                Randomize Court (4)
            </BaseButton>
            <p v-if="randomizeHint" class="min-w-0 text-xs leading-tight text-charcoal-400">{{ randomizeHint }}</p>
        </div>

        <!-- Change Player modal -->
        <BaseModal v-model="swapOpen" :title="`Change player — ${swapTarget?.name ?? ''}`">
            <p class="mb-4 text-sm text-charcoal-300">
                Swap in a waiting player to take their spot on {{ court.label }}, or defer them back to the line.
            </p>

            <ul v-if="swapSubstitutes.length" class="max-h-64 space-y-2 overflow-y-auto pr-1">
                <li v-for="player in swapSubstitutes" :key="player.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-left transition hover:border-volt-300/40 hover:bg-white/[0.06]"
                        @click="swapIn(player)"
                    >
                        <PlayerFace :player="player" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block break-words text-base font-semibold text-white">{{ player.name }}</span>
                            <span class="text-[11px] text-charcoal-400">
                                {{ player.wins }} win{{ player.wins === 1 ? '' : 's' }} · {{ player.gamesPlayed }} game{{ player.gamesPlayed === 1 ? '' : 's' }}
                            </span>
                        </span>
                        <svg class="size-4 shrink-0 text-volt-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" />
                        </svg>
                    </button>
                </li>
            </ul>

            <p v-else class="rounded-xl border border-dashed border-white/10 px-4 py-5 text-center text-sm text-charcoal-300">
                No waiting players to swap in right now.
            </p>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="ghost" @click="swapOpen = false">Cancel</BaseButton>
                    <BaseButton variant="secondary" @click="deferSwap">Defer back to line</BaseButton>
                </div>
            </template>
        </BaseModal>

        <!-- Finish match / score keeper modal -->
        <BaseModal v-model="finishOpen" :title="`Game over on ${court.label} — who won?`">
            <p class="mb-4 text-sm text-charcoal-300">
                Tap the winning team and enter the final scores (e.g. 11 – 8). Winners get the W — everyone in the match
                gets a game played.
            </p>

            <div class="grid grid-cols-2 gap-3">
                <button
                    v-for="(team, i) in match?.teams ?? []"
                    :key="team.key"
                    type="button"
                    class="rounded-2xl border p-4 text-left transition-all duration-150"
                    :class="
                        finishWinner === team.key
                            ? i === 0
                                ? 'border-volt-300/70 bg-volt-300/15 shadow-glow'
                                : 'border-sky-400/70 bg-sky-400/15 shadow-[0_0_0_1px_rgb(56_189_248/0.45)]'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/25'
                    "
                    @click="finishWinner = team.key"
                >
                    <div class="mb-2.5 flex items-center justify-between">
                        <span
                            class="text-xs font-black uppercase tracking-wider"
                            :class="i === 0 ? 'text-volt-200' : 'text-sky-200'"
                        >
                            Team {{ team.key }}
                        </span>
                        <span
                            v-if="finishWinner === team.key"
                            class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide"
                            :class="i === 0 ? 'bg-volt-300 text-ink' : 'bg-sky-300 text-ink'"
                        >
                            Winner
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <PlayerFace v-for="player in teamPlayers(team)" :key="player.id" :player="player" size="sm" />
                    </div>
                    <label class="mt-3 block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-charcoal-400">Final score</span>
                        <input
                            v-model.number="finishScores[team.key]"
                            type="number"
                            min="0"
                            max="21"
                            class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-3 py-2 text-center text-lg font-black text-white transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                        />
                    </label>
                </button>
            </div>

            <p
                v-if="finishWinner && finishScores.A === finishScores.B && finishScores.A > 0"
                class="mt-3 text-xs text-volt-200"
            >
                Scores can't tie — adjust one of them (or tap the real winner).
            </p>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <BaseButton variant="ghost" @click="finishOpen = false">Cancel</BaseButton>
                    <BaseButton :disabled="!canFinish" @click="submitFinish">Record result</BaseButton>
                </div>
            </template>
        </BaseModal>
    </section>
</template>
