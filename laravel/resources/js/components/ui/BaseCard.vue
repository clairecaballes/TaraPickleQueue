<script setup>
defineProps({
    padding: { type: String, default: 'md' }, // none | sm | md | lg
    hoverable: Boolean,
    as: { type: String, default: 'div' },
});
</script>

<template>
    <component
        :is="as"
        class="rounded-2xl border border-white/10 bg-white/[0.04] shadow-card backdrop-blur-sm transition-all duration-200"
        :class="[
            padding === 'none' && 'p-0',
            padding === 'sm' && 'p-3',
            padding === 'md' && 'p-4 sm:p-5',
            padding === 'lg' && 'p-6 sm:p-7',
            hoverable && 'hover:-translate-y-0.5 hover:border-volt-300/30 hover:bg-white/[0.06] hover:shadow-glow',
        ]"
    >
        <slot />
    </component>
</template>
