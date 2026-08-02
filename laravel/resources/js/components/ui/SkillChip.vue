<script setup>
import { computed } from 'vue';

import { skillMeta } from '../../utils/skills';

const props = defineProps({
    skill: { type: String, default: null },
});

const meta = computed(() => skillMeta(props.skill));
</script>

<template>
    <span
        v-if="meta"
        class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold ring-1 ring-inset"
        :class="meta.chip"
    >
        <span>{{ meta.emoji }}</span>
        <span class="whitespace-nowrap">{{ meta.label }}</span>
    </span>
    <span v-else class="text-[11px] text-charcoal-500">—</span>
</template>
