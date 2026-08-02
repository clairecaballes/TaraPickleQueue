<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'info' }, // success | error | info | warning
    message: { type: String, default: '' },
    dismissible: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const styles = computed(() => ({
    success: 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200',
    error: 'border-red-400/30 bg-red-400/10 text-red-200',
    info: 'border-sky-400/30 bg-sky-400/10 text-sky-200',
    warning: 'border-volt-300/30 bg-volt-300/10 text-volt-200',
}[props.type]));
</script>

<template>
    <div
        v-if="message"
        role="alert"
        class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
        :class="styles"
    >
        <svg
            v-if="type === 'error'"
            class="mt-0.5 size-4 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="12" cy="12" r="10" />
            <path stroke-linecap="round" d="M12 8v4m0 4h.01" />
        </svg>
        <svg
            v-else-if="type === 'success'"
            class="mt-0.5 size-4 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="12" cy="12" r="10" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12.5l2.5 2.5 4.5-5" />
        </svg>
        <svg
            v-else
            class="mt-0.5 size-4 shrink-0"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="12" cy="12" r="10" />
            <path stroke-linecap="round" d="M12 8v4m0 4h.01" />
        </svg>

        <p class="flex-1 leading-snug">{{ message }}</p>

        <button
            v-if="dismissible"
            class="rounded p-0.5 opacity-70 transition hover:opacity-100"
            aria-label="Dismiss"
            @click="emit('close')"
        >
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>
</template>
