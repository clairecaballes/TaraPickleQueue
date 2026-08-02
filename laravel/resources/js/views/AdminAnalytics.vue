<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import http, { errorMessage } from '../api/http';
import AreaChart from '../components/ui/AreaChart.vue';
import Badge from '../components/ui/Badge.vue';
import BarChart from '../components/ui/BarChart.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PlayerAvatar from '../components/ui/PlayerAvatar.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const loading = ref(true);
const error = ref('');
const data = ref(null);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const { data: payload } = await http.get('/admin/analytics');
        data.value = payload;
    } catch (err) {
        error.value = errorMessage(err, 'Could not load analytics.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);

async function logout() {
    await auth.logout();
    router.replace('/login');
}

/* ------------------------------------------------------------------ *
 * Formatting helpers
 * ------------------------------------------------------------------ */
function minutesLabel(minutes) {
    const value = Number(minutes) || 0;

    if (value < 60) {
        return `${Math.round(value)} min`;
    }

    const hours = value / 60;

    return hours >= 10 ? `${Math.round(hours)} h` : `${hours.toFixed(1)} h`;
}

const overview = computed(() => data.value?.overview ?? null);
const matches = computed(() => data.value?.matches ?? null);
const queue = computed(() => data.value?.queue ?? null);
const regions = computed(() => data.value?.regions ?? []);
const trends = computed(() => data.value?.trends ?? []);
const peakHours = computed(() => data.value?.peak_hours ?? []);
const playHours = computed(() => data.value?.matches?.play_hours ?? []);

const statCards = computed(() => {
    if (!overview.value || !matches.value || !queue.value) {
        return [];
    }

    return [
        { label: 'Unique visitors (all time)', value: String(overview.value.total_visitors), icon: '👥' },
        { label: 'New this month', value: String(overview.value.new_this_month), icon: '✨' },
        { label: 'Returning this month', value: String(overview.value.returning_this_month), icon: '🔁' },
        { label: 'Avg. session', value: minutesLabel(overview.value.avg_session_minutes), icon: '⏱️' },
        { label: 'Page views (month)', value: String(overview.value.page_views), icon: '👁️' },
        { label: 'Online now', value: String(overview.value.active_sessions_now), icon: '🟢' },
    ];
});

const sessionCards = computed(() => {
    if (!matches.value || !queue.value) {
        return [];
    }

    return [
        { label: 'Matches played', value: String(matches.value.matches_played), icon: '🎾' },
        { label: 'Matches this month', value: String(matches.value.matches_this_month), icon: '🗓️' },
        { label: 'Cumulative playing time', value: minutesLabel(matches.value.total_play_minutes), icon: '⏳' },
        { label: 'Avg. match length', value: minutesLabel(matches.value.avg_match_minutes), icon: '📏' },
        { label: 'Queue time (dwell)', value: minutesLabel(queue.value.queue_dwell_minutes + queue.value.live_queue_minutes), icon: '🚶' },
        { label: 'Total active session time', value: minutesLabel(queue.value.total_active_minutes), icon: '🔥' },
    ];
});

const maxVisitors = computed(() => Math.max(1, ...regions.value.map((region) => region.visitors)));
</script>

<template>
    <div class="min-h-screen pb-16">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-white/10 bg-navy-950/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5">
                <div class="flex items-center gap-2.5">
                    <button
                        class="grid size-9 place-items-center rounded-xl bg-volt-300"
                        title="Back to court control"
                        aria-label="Back to court control"
                        @click="router.push('/admin')"
                    >
                        <svg class="size-5 text-navy-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div>
                        <span class="block text-lg font-black tracking-tight text-white">Analytics</span>
                        <span class="text-[11px] text-charcoal-300">Visitors · traffic · session stats</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Badge color="volt" size="sm" dot>Admin</Badge>
                    <PlayerAvatar :player="auth.user" size="sm" />
                    <button
                        class="rounded-full p-2 text-charcoal-300 transition hover:bg-white/10 hover:text-white"
                        title="Sign out"
                        aria-label="Sign out"
                        @click="logout"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4M10 5V3h10v18H10v-2" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 pt-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-white">Visitor &amp; session analytics</h1>
                    <p class="text-sm text-charcoal-300">
                        Monthly visitors, geography, traffic trends and court session stats — last 30 days.
                    </p>
                </div>
                <BaseButton variant="secondary" size="sm" :loading="loading" @click="load">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.5 9a7 7 0 0111.8-2.3M18.5 15a7 7 0 01-11.8 2.3" />
                    </svg>
                    Refresh
                </BaseButton>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="flex items-center gap-3 rounded-xl border border-red-400/30 bg-red-400/10 px-4 py-3 text-sm text-red-200"
            >
                <span class="font-semibold">Analytics unavailable:</span> {{ error }}
                <BaseButton size="sm" variant="secondary" class="ml-auto" @click="load">Retry</BaseButton>
            </div>

            <!-- Loading skeleton -->
            <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="i in 6" :key="i" class="h-28 animate-pulse rounded-2xl border border-white/10 bg-white/[0.04]" />
            </div>

            <template v-else-if="data">
                <!-- Visitors overview -->
                <section>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-charcoal-300">Visitors</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in statCards"
                            :key="card.label"
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 shadow-card transition hover:border-volt-300/25"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xl">{{ card.icon }}</span>
                                <span class="text-2xl font-black tracking-tight text-white">{{ card.value }}</span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-charcoal-300">{{ card.label }}</p>
                        </div>
                    </div>
                </section>

                <!-- Session statistics -->
                <section>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-charcoal-300">Session statistics</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="card in sessionCards"
                            :key="card.label"
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 shadow-card transition hover:border-volt-300/25"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xl">{{ card.icon }}</span>
                                <span class="text-2xl font-black tracking-tight text-volt-200">{{ card.value }}</span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-charcoal-300">{{ card.label }}</p>
                        </div>
                    </div>
                </section>

                <!-- Charts -->
                <section class="grid gap-5 lg:grid-cols-2">
                    <!-- Traffic trend -->
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-card">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-white">Traffic trend</h3>
                                <p class="text-xs text-charcoal-300">Visits per day · last 30 days</p>
                            </div>
                            <Badge color="volt" size="sm">{{ trends.reduce((sum, day) => sum + day.visits, 0) }} visits</Badge>
                        </div>
                        <AreaChart
                            :items="trends.map((day) => ({ label: day.label, value: day.visits }))"
                            gradient-id="tp-area-traffic"
                        />
                    </div>

                    <!-- Regions -->
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-card">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-white">Visitors by region</h3>
                                <p class="text-xs text-charcoal-300">Country · region · last 30 days</p>
                            </div>
                            <Badge color="gray" size="sm">{{ regions.length }} regions</Badge>
                        </div>

                        <div v-if="regions.length" class="space-y-2.5">
                            <div
                                v-for="region in regions"
                                :key="region.label"
                                class="group"
                            >
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="truncate font-semibold text-white">{{ region.label }}</span>
                                    <span class="shrink-0 font-black text-volt-200">
                                        {{ region.visitors }} <span class="font-medium text-charcoal-400">visitor{{ region.visitors === 1 ? '' : 's' }}</span>
                                    </span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-white/5">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-volt-400 to-volt-300 transition-all duration-500 group-hover:brightness-125"
                                        :style="{ width: `${(region.visitors / maxVisitors) * 100}%` }"
                                    />
                                </div>
                            </div>
                        </div>

                        <p v-else class="rounded-xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-charcoal-300">
                            No visits recorded yet — tracking starts on the next page load.
                        </p>
                    </div>

                    <!-- Peak hours -->
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-card">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-white">Peak visiting hours</h3>
                                <p class="text-xs text-charcoal-300">Sessions started per hour of day</p>
                            </div>
                        </div>
                        <BarChart
                            :items="peakHours.map((hour) => ({ label: hour.label, value: hour.visits }))"
                            color="sky"
                            :height="140"
                            :label-every="2"
                        />
                    </div>

                    <!-- Popular playing hours -->
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-card">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-black text-white">Popular playing hours</h3>
                                <p class="text-xs text-charcoal-300">Matches started per hour of day</p>
                            </div>
                        </div>
                        <BarChart
                            :items="playHours.map((hour) => ({ label: hour.label, value: hour.matches }))"
                            color="emerald"
                            :height="140"
                            :label-every="2"
                        />
                    </div>
                </section>

                <p class="text-center text-[11px] text-charcoal-500">
                    Visitor sessions are tracked anonymously via a cookie — no personal data is collected. Generated
                    {{ new Date(data.generated_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}.
                </p>
            </template>

            <div
                v-else-if="!loading && !error"
                class="rounded-2xl border border-dashed border-white/15 px-6 py-16 text-center text-sm text-charcoal-300"
            >
                No analytics yet.
            </div>
        </main>

        <footer class="mt-10 px-4 pb-4 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Tara Pickle by Claire</p>
        </footer>
    </div>
</template>
