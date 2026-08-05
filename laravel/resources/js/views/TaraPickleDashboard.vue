<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';

import HowToModal from '../components/HowToModal.vue';
import QueueBoardPanel from '../components/QueueBoardPanel.vue';
import QueueCard from '../components/QueueCard.vue';
import ResultsPanel from '../components/ResultsPanel.vue';
import TaraPickleLogo from '../components/TaraPickleLogo.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import { useTarapickleStore } from '../stores/tarapickle';
import { announce, soundOnPreview } from '../utils/speech';
import { getTheme, toggleTheme } from '../utils/theme';

const store = useTarapickleStore();
const { sortedQueues } = storeToRefs(store);

const route = useRoute();

const newQueueName = ref('');
const createInput = ref(null);
const toast = ref('');
let toastTimer = null;

const tab = ref('queues'); // queues | board | results

const totalQueues = computed(() => store.queues.length);
const totalPlayers = computed(() =>
    store.queues.reduce((sum, queue) => sum + queue.players.length, 0),
);

const ttsEnabled = computed(() => store.settings.tts);

/** Outdoor Daylight high-contrast toggle. */
const daylight = ref(getTheme() === 'daylight');

function toggleThemeMode() {
    daylight.value = toggleTheme() === 'daylight';
}

function notify(message) {
    toast.value = message;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.value = '';
    }, 3200);
}

/** Court-call announcements (speech) + a friendly toast. */
function onAnnounce(message) {
    if (ttsEnabled.value) {
        announce(message);
    }
}

function toggleTts() {
    store.setTts(!ttsEnabled.value);
    notify(ttsEnabled.value ? 'Court-call sound is on 🔊' : 'Court-call sound is off.');

    if (ttsEnabled.value) {
        soundOnPreview();
    }
}

const howOpen = ref(false);

function createQueue() {
    const queue = store.createQueue(newQueueName.value);

    newQueueName.value = '';
    notify(`“${queue.name}” is ready — add your first players.`);
    tab.value = 'queues';
}

/* One live clock drives every court's match timer. */
const now = ref(Date.now());
let clock = null;

onMounted(() => {
    clock = window.setInterval(() => {
        now.value = Date.now();
    }, 1000);

    // Deep link: /play?queue=<id> (shared/QR'd queue) — open that session
    // and switch to the Queues tab so it is the one expanded queue.
    const queueId = route.query.queue;

    if (typeof queueId === 'string' && store.findQueue(queueId)) {
        store.expandQueue(queueId);
        tab.value = 'queues';
    }
});

onBeforeUnmount(() => window.clearInterval(clock));
</script>

<template>
    <div class="flex min-h-screen flex-col pb-10">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-950/85 backdrop-blur-md">
            <div class="mx-auto w-full max-w-6xl px-4">
                <div class="flex items-center justify-between py-3.5">
                    <div class="flex items-center gap-3">
                        <TaraPickleLogo class="size-9 drop-shadow-[0_4px_14px_rgb(255_214_10/0.35)]" />
                        <div>
                            <span class="block text-lg font-black leading-none tracking-tight text-white">TaraPickle</span>
                            <span class="text-[11px] text-charcoal-400">Open court · no accounts</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge color="gray" size="sm" class="hidden sm:inline-flex">
                            {{ totalQueues }} queue{{ totalQueues === 1 ? '' : 's' }}
                        </Badge>
                        <Badge color="volt" size="sm" class="hidden sm:inline-flex">
                            {{ totalPlayers }} player{{ totalPlayers === 1 ? '' : 's' }}
                        </Badge>

                        <!-- Outdoor Daylight toggle -->
                        <button
                            class="grid size-12 place-items-center rounded-full transition"
                            :class="
                                daylight
                                    ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                    : 'text-charcoal-400 hover:bg-white/10 hover:text-white'
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

                        <!-- Court-call sound toggle -->
                        <button
                            class="grid size-12 place-items-center rounded-full transition"
                            :class="
                                ttsEnabled
                                    ? 'bg-volt-300/15 text-volt-200 ring-1 ring-volt-300/40'
                                    : 'text-charcoal-400 hover:bg-white/10 hover:text-white'
                            "
                            :title="ttsEnabled ? 'Turn off court-call sound' : 'Turn on court-call sound (speaks player names)'"
                            aria-label="Toggle court-call sound"
                            @click="toggleTts"
                        >
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 10v4a2 2 0 002 2h2l4 4V4L8 8H6a2 2 0 00-2 2z" />
                                <path v-if="!ttsEnabled" stroke-linecap="round" d="M16 9l4 6m0-6l-4 6" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5a5 5 0 010 7M18 6a8.5 8.5 0 010 12" />
                            </svg>
                        </button>

                        <!-- How to use -->
                        <button
                            class="grid size-12 place-items-center rounded-full border border-white/10 text-base font-black text-charcoal-200 transition hover:border-volt-300/40 hover:bg-volt-300/10 hover:text-volt-200"
                            title="How to use TaraPickle"
                            aria-label="How to use TaraPickle"
                            @click="howOpen = true"
                        >
                            ?
                        </button>
                    </div>
                </div>

                <!-- Main navigation tabs -->
                <nav class="flex gap-3 pb-2" aria-label="Main navigation">
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition"
                        :class="
                            tab === 'queues'
                                ? 'bg-volt-300 text-ink shadow-[0_4px_14px_-2px_rgb(255_214_10/0.45)]'
                                : 'text-charcoal-300 hover:bg-white/10 hover:text-white'
                        "
                        @click="tab = 'queues'"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M4 15h16M9 4v16M15 4v16" />
                            <path stroke-linecap="round" d="M2 6v4M2 14v4M22 6v4M22 14v4" />
                        </svg>
                        Queues
                        <span
                            class="rounded-full px-1.5 text-[10px]"
                            :class="tab === 'queues' ? 'bg-navy-950/15 text-ink' : 'bg-white/10 text-charcoal-300'"
                        >
                            {{ totalQueues }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition"
                        :class="
                            tab === 'board'
                                ? 'bg-volt-300 text-ink shadow-[0_4px_14px_-2px_rgb(255_214_10/0.45)]'
                                : 'text-charcoal-300 hover:bg-white/10 hover:text-white'
                        "
                        @click="tab = 'board'"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h10M4 18h10" />
                            <circle cx="18" cy="18" r="3" />
                        </svg>
                        Queue Board
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition"
                        :class="
                            tab === 'results'
                                ? 'bg-volt-300 text-ink shadow-[0_4px_14px_-2px_rgb(255_214_10/0.45)]'
                                : 'text-charcoal-300 hover:bg-white/10 hover:text-white'
                        "
                        @click="tab = 'results'"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 20h12M8 16h8M9 12h6M10 8h4M12 4v16" />
                        </svg>
                        Results
                    </button>
                </nav>
            </div>
        </header>

        <!-- Toast -->
        <div class="pointer-events-none fixed inset-x-0 top-16 z-50 flex justify-center px-4">
            <Transition
                enter-active-class="transition-all duration-300"
                enter-from-class="opacity-0 -translate-y-3 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-opacity duration-200"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    class="pointer-events-auto rounded-full border border-volt-300/40 bg-navy-900/95 px-5 py-2.5 text-sm font-semibold text-white shadow-glow backdrop-blur"
                >
                    {{ toast }}
                </div>
            </Transition>
        </div>

        <main class="mx-auto w-full max-w-6xl flex-1 space-y-6 px-4 pt-6">
            <!-- Queues tab -->
            <template v-if="tab === 'queues'">
                <!-- Create queue -->
                <section
                    class="animate-pop-in rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.06] to-white/[0.02] p-4 shadow-card backdrop-blur-sm sm:p-5"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label
                                for="new-queue"
                                class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-charcoal-300"
                            >
                                New queue session
                            </label>
                            <input
                                id="new-queue"
                                ref="createInput"
                                v-model="newQueueName"
                                type="text"
                                maxlength="50"
                                placeholder="Queue name (optional) — e.g. Morning Court 1"
                                class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-3 text-base text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                                @keydown.enter.prevent="createQueue"
                            />
                            <p class="mt-1.5 text-[11px] text-charcoal-400">
                                Leave it blank and we'll call it Queue #{{ store.nextQueueNumber() }} automatically.
                            </p>
                        </div>
                        <BaseButton size="lg" class="shrink-0" @click="createQueue">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" d="M12 5v14m-7-7h14" />
                            </svg>
                            Create Queue
                        </BaseButton>
                    </div>
                </section>

                <!-- Queue cards — newest first -->
                <section v-if="sortedQueues.length" class="space-y-6">
                    <QueueCard
                        v-for="queue in sortedQueues"
                        :key="queue.id"
                        :queue="queue"
                        :now="now"
                        @toast="notify"
                        @announce="onAnnounce"
                    />
                </section>

                <!-- Empty state -->
                <section
                    v-else
                    class="flex flex-col items-center gap-5 rounded-2xl border border-dashed border-white/15 px-6 py-20 text-center"
                >
                    <TaraPickleLogo class="size-20 opacity-80" />
                    <div>
                        <h2 class="text-2xl font-black tracking-tight text-white">No queues yet</h2>
                        <p class="mt-1.5 text-sm text-charcoal-300">
                            Create a session and start adding players in seconds — no sign-ups, no fuss.
                        </p>
                    </div>
                    <BaseButton size="lg" @click="createInput?.focus()">Create your first queue</BaseButton>
                </section>
            </template>

            <!-- Queue Board tab -->
            <template v-else-if="tab === 'board'">
                <QueueBoardPanel @toast="notify" />
            </template>

            <!-- Results tab -->
            <template v-else>
                <ResultsPanel @toast="notify" />
            </template>
        </main>

        <!-- Global footer -->
        <footer class="mt-10 px-4">
            <div class="mx-auto w-full max-w-6xl border-t border-white/10 pt-6 pb-4 text-center">
                <p class="text-xs font-bold tracking-wide text-charcoal-300">Tara Pickle by Claire</p>
                <p class="mt-1 text-[11px] text-charcoal-500">
                    Fair queues, cute critters, live stats — everything auto-saves to this browser (localStorage).
                </p>
            </div>
        </footer>

        <!-- How to use modal -->
        <HowToModal v-model="howOpen" />
    </div>
</template>
