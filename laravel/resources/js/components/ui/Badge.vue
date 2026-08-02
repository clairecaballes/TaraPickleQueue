<script setup>
import { computed } from 'vue';

const props = defineProps({
    color: { type: String, default: 'navy' }, // volt | navy | green | red | gray
    dot: Boolean,
    size: { type: String, default: 'md' }, // sm | md
});

const classes = computed(() => ({
    volt: 'bg-volt-300/15 text-volt-200 ring-volt-300/30',
    navy: 'bg-sky-400/10 text-sky-200 ring-sky-400/30',
    green: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30',
    red: 'bg-red-400/10 text-red-300 ring-red-400/30',
    gray: 'bg-white/10 text-charcoal-200 ring-white/15',
}[props.color]));

const dotClasses = computed(() => ({
    volt: 'bg-volt-300',
    navy: 'bg-sky-300',
    green: 'bg-emerald-400',
    red: 'bg-red-400',
    gray: 'bg-charcoal-300',
}[props.color]));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset"
        :class="[classes, size === 'sm' ? 'px-2 py-0.5 text-[10px] uppercase tracking-wide' : 'px-2.5 py-1 text-xs']"
    >
        <span v-if="dot" class="size-1.5 rounded-full" :class="dotClasses" />
        <slot />
    </span>
</template>
