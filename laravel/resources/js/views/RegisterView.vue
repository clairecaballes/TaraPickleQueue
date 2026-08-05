<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import { errorMessage } from '../api/http';
import AlertBanner from '../components/ui/AlertBanner.vue';
import Badge from '../components/ui/Badge.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseCard from '../components/ui/BaseCard.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const phone = ref('');
const skillRating = ref(3);
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');

/** Pickleball ratings run 1.0–5.0 in 0.5 steps (DUPR-style). */
const ratingLabel = computed(() => skillRating.value.toFixed(1));

async function submit() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        await auth.register({
            name: name.value,
            email: email.value,
            phone: phone.value || null,
            skill_rating: skillRating.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });

        router.replace(auth.isAdmin ? '/admin' : '/');
    } catch (err) {
        const data = err?.response?.data;

        // Laravel validation bag — surface the first field error, or the message.
        error.value =
            (data?.errors && Object.values(data.errors)[0]?.[0]) ||
            data?.message ||
            errorMessage(err, 'Unable to create your account.');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 grid size-14 place-items-center rounded-2xl bg-volt-300">
                    <svg class="size-8 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="8" cy="6" r="3" />
                        <circle cx="16" cy="8" r="2.5" />
                        <circle cx="8" cy="18" r="2.5" />
                        <path stroke-linecap="round" d="M10.5 6h3.5a3 3 0 013 3v0M12 18h3a2 2 0 002-2v-6" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-white">Join the club</h1>
            </div>

            <BaseCard padding="lg">
                <AlertBanner type="error" :message="error" @close="error = ''" />

                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                            Full name
                        </label>
                        <input
                            id="name"
                            v-model="name"
                            type="text"
                            required
                            autocomplete="name"
                            placeholder="Alex Rivera"
                            class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                                Phone
                            </label>
                            <input
                                id="phone"
                                v-model="phone"
                                type="tel"
                                autocomplete="tel"
                                placeholder="+1 555 0123"
                                class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                            />
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="skill" class="text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                                Skill rating
                            </label>
                            <Badge color="volt">{{ ratingLabel }}</Badge>
                        </div>
                        <input
                            id="skill"
                            v-model.number="skillRating"
                            type="range"
                            min="1"
                            max="5"
                            step="0.5"
                            class="w-full accent-volt-300"
                        />
                        <div class="mt-1 flex justify-between text-[10px] text-charcoal-500">
                            <span>1.0 · Beginner</span>
                            <span>5.0 · Pro</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                                Password
                            </label>
                            <input
                                id="password"
                                v-model="password"
                                type="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="8+ characters"
                                class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                            />
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-charcoal-300">
                                Confirm password
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="passwordConfirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Repeat it"
                                class="w-full rounded-xl border border-white/10 bg-navy-950/60 px-4 py-2.5 text-sm text-white placeholder-charcoal-500 transition focus:border-volt-300/60 focus:outline-none focus:ring-2 focus:ring-volt-300/20"
                            />
                        </div>
                    </div>

                    <BaseButton type="submit" block size="lg" :loading="loading">
                        Create account
                    </BaseButton>
                </form>

                <p class="mt-5 text-center text-sm text-charcoal-300">
                    Already playing with us?
                    <RouterLink to="/login" class="font-semibold text-volt-300 transition hover:text-volt-200">
                        Sign in
                    </RouterLink>
                </p>
            </BaseCard>
        </div>

        <footer class="absolute bottom-4 left-0 right-0 text-center">
            <p class="text-xs font-bold tracking-wide text-charcoal-400">Tara Pickle by Claire</p>
        </footer>
    </div>
</template>
