<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** [{ label, value, sub? }] */
    items: { type: Array, default: () => [] },
    color: { type: String, default: 'volt' }, // volt | sky | emerald | amber
    height: { type: Number, default: 150 },
    /** Show every Nth x-axis label (e.g. 2 for 24-hour charts). */
    labelEvery: { type: Number, default: 1 },
    format: { type: Function, default: (value) => value },
});

const colors = {
    volt: 'bg-volt-300',
    sky: 'bg-sky-400',
    emerald: 'bg-emerald-400',
    amber: 'bg-amber-400',
};

const max = computed(() => Math.max(1, ...props.items.map((item) => Number(item.value) || 0)));
</script>

<template>
    <div>
        <div class="flex items-end gap-1" :style="{ height: `${height}px` }">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="group flex h-full min-w-0 flex-1 flex-col justify-end"
                :title="`${item.label} — ${format(item.value)}`"
            >
                <div
                    class="w-full rounded-t transition-all duration-200 group-hover:brightness-125"
                    :class="colors[color]"
                    :style="{
                        height: `${Math.max(2, (Number(item.value) / max) * 100)}%`,
                        opacity: Number(item.value) > 0 ? 0.9 : 0.12,
                    }"
                />
            </div>
        </div>
        <div class="mt-1.5 flex gap-1">
            <span
                v-for="(item, index) in items"
                :key="index"
                class="min-w-0 flex-1 text-center text-[10px] font-semibold text-charcoal-400"
            >
                {{ index % labelEvery === 0 ? item.label : '' }}
            </span>
        </div>
    </div>
</template>
