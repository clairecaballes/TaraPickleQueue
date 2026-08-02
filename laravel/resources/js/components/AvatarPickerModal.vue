<script setup>
import { computed } from 'vue';

import { ANIMALS } from '../data/avatars';
import BaseModal from './ui/BaseModal.vue';

const props = defineProps({
    modelValue: Boolean,
    title: { type: String, default: 'Pick an avatar' },
    currentUrl: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'select']);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

function pick(animal) {
    emit('select', animal);
    open.value = false;
}

function hideBrokenImage(event) {
    event.target.closest('.avatar-cell')?.classList.add('img-broken');
}
</script>

<template>
    <BaseModal v-model="open" :title="title" max-width="max-w-2xl">
        <p class="mb-4 text-sm text-charcoal-300">
            No uploads needed — tap a critter and it's instantly your new look, everywhere.
        </p>

        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
            <button
                v-for="animal in ANIMALS"
                :key="animal.key"
                type="button"
                class="avatar-cell group flex flex-col items-center gap-1.5 rounded-2xl border p-2 pb-1.5 transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-volt-300"
                :class="
                    animal.url === currentUrl
                        ? 'border-volt-300/70 bg-volt-300/15 shadow-glow'
                        : 'border-white/10 bg-white/[0.03] hover:-translate-y-0.5 hover:border-volt-300/40 hover:bg-white/[0.07]'
                "
                @click="pick(animal)"
            >
                <span
                    class="relative grid size-14 place-items-center overflow-hidden rounded-full bg-navy-600/70 text-2xl ring-2"
                    :class="animal.url === currentUrl ? 'ring-volt-300/70' : 'ring-navy-950/70'"
                >
                    <span class="absolute inset-0 grid place-items-center leading-none">{{ animal.emoji }}</span>
                    <img
                        :src="animal.url"
                        :alt="animal.name"
                        loading="lazy"
                        class="absolute inset-0 size-full object-cover"
                        @error="hideBrokenImage"
                    />
                </span>
                <span class="text-[10px] font-semibold text-charcoal-200">{{ animal.name }}</span>
            </button>
        </div>

        <p class="mt-4 text-center text-xs text-charcoal-400">
            {{ ANIMALS.length }} free animal avatars · changes sync across the queue, court &amp; leaderboard instantly
        </p>
    </BaseModal>
</template>

<style scoped>
.avatar-cell.img-broken img {
    display: none;
}
</style>
