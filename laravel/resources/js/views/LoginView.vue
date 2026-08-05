<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { errorMessage } from '../api/http';
import AlertBanner from '../components/ui/AlertBanner.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

const isAdminTarget = computed(() => {
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '';

    return redirect.startsWith('/admin');
});

const headingText = computed(() => (isAdminTarget.value ? 'Admin sign in' : 'Welcome back'));
const subtitleText = computed(() =>
    isAdminTarget.value ? 'Manage courts, queue, and matches.' : 'Dink on. Wait less. Play more.'
);
const buttonText = computed(() => (isAdminTarget.value ? 'Sign in to admin' : 'Sign in'));

async function submit() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await auth.login({ email: email.value, password: password.value });

        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        const target = response?.user?.is_admin || response?.user?.isAdmin || auth.isAdmin
            ? '/admin'
            : redirect || '/play';

        router.replace(target);
    } catch (err) {
        const data = err?.response?.data;

        error.value =
            (data?.errors && Object.values(data.errors)[0]?.[0]) ||
            data?.message ||
            errorMessage(err, 'Unable to sign in.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 grid size-14 place-items-center rounded-2xl bg-volt-300 shadow-[0_8px_30px_-6px_rgb(255_214_10/0.5)]"
                >
                    <svg class="size-8 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="8" cy="6" r="3" />
                        <circle cx="16" cy="8" r="2.5" />
                        <circle cx="8" cy="18" r="2.5" />
                        <path stroke-linecap="round" d="M10.5 6h3.5a3 3 0 013 3v0M12 18h3a2 2 0 002-2v-6" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-white">TaraPickle</h1>
                <p class="mt-1 text-sm text-charcoal-300">{{ subtitleText }}</p>
            </div>

            <BaseCard padding="lg">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">{{ headingText }}</h2>
                    <span
                        v-if="isAdminTarget"
                        class="rounded-full border border-volt-300/40 bg-volt-300/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-volt-200"
                    >
                        Admin access
                    </span>
                </div>

                <AlertBanner type="error" :message="error" @close="error = ''" />

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                            Email
                        </label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="you@example.com"
                            class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                        />
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                            Password
                        </label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                        />
                    </div>

                    <BaseButton type="submit" block size="lg" :loading="loading">
                        {{ buttonText }}
                    </BaseButton>
                </form>

                <p class="mt-5 text-center text-sm text-charcoal-300">
                    New to the club?
                    <RouterLink to="/register" class="font-semibold text-volt-300 transition hover:text-volt-200">
                        Create an account
                    </RouterLink>
                </p>
            </BaseCard>
        </div>

        <footer class="absolute bottom-4 left-0 right-0 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Tara Pickle by Claire</p>
        </footer>
    </div>
</template>
