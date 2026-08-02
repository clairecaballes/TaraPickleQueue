<script setup>
import { useRouter } from 'vue-router';

import TaraPickleLogo from '../components/TaraPickleLogo.vue';

const router = useRouter();

const features = [
    { emoji: '⚡', label: 'Instant queues' },
    { emoji: '🎲', label: 'Fair randomizer' },
    { emoji: '🐾', label: 'Animal avatars' },
    { emoji: '🏆', label: 'Live leaderboard' },
];

/** Decorative floating pickleballs. */
const floatingBalls = Array.from({ length: 10 }, (_, i) => ({
    left: `${(i * 37 + 6) % 92}%`,
    top: `${(i * 53 + 8) % 78}%`,
    size: 16 + ((i * 17) % 34),
    delay: `${(i % 5) * 0.9}s`,
    duration: `${6 + (i % 4) * 2.5}s`,
    opacity: 0.08 + (i % 3) * 0.09,
}));

const dotSpots = [
    { left: '22%', top: '22%' },
    { left: '72%', top: '22%' },
    { left: '22%', top: '72%' },
    { left: '72%', top: '72%' },
];

function enter() {
    router.push('/play');
}
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 py-20 text-center">
        <!-- Court-line backdrop -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 bg-white/[0.06]" />
            <div class="absolute left-0 top-1/2 h-px w-full -translate-y-1/2 bg-white/[0.06]" />
            <div class="absolute left-1/2 top-1/2 size-48 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/[0.06]" />
            <div class="absolute left-0 top-1/2 h-px w-1/3 -translate-y-1/2 border-t-2 border-dashed border-white/[0.05]" />
            <div class="absolute right-0 top-1/2 h-px w-1/3 -translate-y-1/2 border-t-2 border-dashed border-white/[0.05]" />
        </div>

        <!-- Floating pickleballs -->
        <span
            v-for="(ball, i) in floatingBalls"
            :key="i"
            class="pointer-events-none absolute animate-float"
            :style="{
                left: ball.left,
                top: ball.top,
                animationDelay: ball.delay,
                animationDuration: ball.duration,
                opacity: ball.opacity,
            }"
            aria-hidden="true"
        >
            <span
                class="relative block rounded-full border border-volt-300/50 bg-volt-300/25"
                :style="{ width: `${ball.size}px`, height: `${ball.size}px` }"
            >
                <span
                    v-for="(spot, d) in dotSpots"
                    :key="d"
                    class="absolute size-[13%] rounded-full bg-navy-950/70"
                    :style="spot"
                />
            </span>
        </span>

        <!-- Content -->
        <div class="relative z-10 flex max-w-3xl flex-col items-center">
            <div class="animate-rise">
                <TaraPickleLogo
                    class="size-28 animate-float-slow drop-shadow-[0_20px_45px_rgb(255_214_10/0.35)] sm:size-36"
                />
            </div>

            <h1
                class="animate-rise mt-7 font-display text-5xl leading-none tracking-tight text-white [animation-delay:120ms] sm:text-7xl"
            >
                Tara<span class="text-volt-300 drop-shadow-[0_0_25px_rgb(255_214_10/0.5)]">Pickle</span>
            </h1>

            <p class="animate-rise mt-4 text-base font-medium text-charcoal-300 [animation-delay:220ms] sm:text-lg">
                Smart, Fair &amp; Simple Court Queue Management
            </p>

            <div class="animate-rise mt-10 [animation-delay:320ms]">
                <button
                    type="button"
                    class="animate-pulse-ring group inline-flex items-center gap-3 rounded-full bg-volt-300 px-10 py-4 text-lg font-black text-navy-950 transition-all duration-200 hover:scale-105 hover:bg-volt-200 active:scale-95"
                    @click="enter"
                >
                    Let's Play
                    <svg
                        class="size-5 transition-transform duration-200 group-hover:translate-x-1"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-5-5m5 5l-5 5" />
                    </svg>
                </button>
                <p class="mt-3 text-sm font-semibold tracking-wide text-charcoal-300">Developed by Claire</p>
            </div>

            <div
                class="animate-rise mt-14 grid w-full max-w-lg grid-cols-2 gap-3 [animation-delay:420ms] sm:grid-cols-4"
            >
                <div
                    v-for="feature in features"
                    :key="feature.label"
                    class="flex flex-col items-center gap-1.5 rounded-2xl border border-white/10 bg-white/[0.03] px-3 py-4 backdrop-blur-sm transition hover:-translate-y-0.5 hover:border-volt-300/30 hover:bg-white/[0.06]"
                >
                    <span class="text-2xl">{{ feature.emoji }}</span>
                    <span class="text-xs font-semibold text-charcoal-200">{{ feature.label }}</span>
                </div>
            </div>

            <p class="animate-rise mt-10 text-xs text-charcoal-400 [animation-delay:520ms]">
                No accounts · No uploads · Open to anyone on the page
            </p>
        </div>

        <!-- Global footer -->
        <footer class="absolute bottom-5 left-0 right-0 z-10 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Tara Pickle by Claire</p>
        </footer>
    </div>
</template>
