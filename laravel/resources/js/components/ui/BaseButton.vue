<script setup>
defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | danger | ghost
    size: { type: String, default: 'md' }, // sm | md | lg
    type: { type: String, default: 'button' }, // button | submit
    loading: Boolean,
    disabled: Boolean,
    block: Boolean,
});

const emit = defineEmits(['click']);
</script>

<template>
    <button
        class="inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-volt-300 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950 disabled:cursor-not-allowed disabled:opacity-50 active:scale-[0.97]"
        :class="[
            variant === 'primary' && 'bg-volt-300 text-ink shadow-[0_4px_14px_-2px_rgb(255_214_10/0.45)] hover:bg-volt-200',
            variant === 'secondary' && 'border border-white/15 bg-white/5 text-white hover:border-volt-300/50 hover:bg-white/10',
            variant === 'danger' && 'bg-red-500/90 text-white hover:bg-red-500',
            variant === 'ghost' && 'text-charcoal-200 hover:bg-white/10 hover:text-white',
            /* Minimum 48px touch targets (12 = 48px); sm stays compact but tall. */
            size === 'sm' && 'min-h-12 px-4 py-2 text-sm',
            size === 'md' && 'min-h-12 px-5 py-2.5 text-base',
            size === 'lg' && 'min-h-14 px-6 py-3 text-base',
            block && 'w-full',
        ]"
        :type="type"
        :disabled="disabled || loading"
        @click="emit('click', $event)"
    >
        <svg
            v-if="loading"
            class="size-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <slot v-else />
    </button>
</template>
