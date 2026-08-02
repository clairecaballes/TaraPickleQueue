<script setup>
import { computed } from 'vue';

const props = defineProps({
    player: { type: Object, required: true }, // { id, name, avatar, skill_rating }
    size: { type: String, default: 'md' }, // sm | md | lg
    showRing: { type: Boolean, default: true },
});

const initials = computed(() => {
    const name = props.player?.name?.trim() ?? '?';
    const parts = name.split(/\s+/).filter(Boolean);

    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
});

/** Skill ring: 4.0+ volt, 3.0+ light, below charcoal. */
const ring = computed(() => {
    if (!props.showRing) {
        return '';
    }

    const skill = Number(props.player?.skill_rating);

    if (skill >= 4.0) {
        return 'ring-2 ring-volt-300/80';
    }

    if (skill >= 3.0) {
        return 'ring-2 ring-sky-300/70';
    }

    return 'ring-2 ring-charcoal-500/60';
});

const sizeClass = computed(() => {
    if (props.size === 'sm') {
        return 'size-7 text-[10px]';
    }

    if (props.size === 'lg') {
        return 'size-12 text-sm';
    }

    return 'size-9 text-xs';
});
</script>

<template>
    <span
        class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-navy-600/70 font-bold text-white"
        :class="[sizeClass, ring]"
        :title="player?.name"
    >
        <img v-if="player?.avatar" :src="player.avatar" :alt="player?.name ?? 'player'" class="size-full object-cover" />
        <span v-else>{{ initials }}</span>
    </span>
</template>
