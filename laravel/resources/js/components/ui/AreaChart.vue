<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** [{ label, value }] */
    items: { type: Array, default: () => [] },
    height: { type: Number, default: 160 },
    color: { type: String, default: '#ffd60a' },
    /** Unique gradient id — required when more than one chart is on the page. */
    gradientId: { type: String, default: 'tp-area' },
});

const viewWidth = 600;
const padX = 10;
const padTop = 14;
const padBottom = 8;

const chartH = computed(() => props.height);

const max = computed(() => Math.max(1, ...props.items.map((item) => Number(item.value) || 0)));

const points = computed(() => {
    const count = Math.max(2, props.items.length);

    return props.items.map((item, index) => ({
        x: padX + (index / (count - 1)) * (viewWidth - padX * 2),
        y: padTop + (1 - (Number(item.value) || 0) / max.value) * (chartH.value - padTop - padBottom),
    }));
});

const linePath = computed(() =>
    points.value.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x},${point.y}`).join(' '),
);

const areaPath = computed(() => {
    const last = points.value[points.value.length - 1];

    return `${linePath.value} L ${last.x},${chartH.value - padBottom} L ${points.value[0].x},${chartH.value - padBottom} Z`;
});
</script>

<template>
    <div>
        <svg
            :viewBox="`0 0 ${viewWidth} ${chartH}`"
            class="h-auto w-full"
            :style="{ maxHeight: `${height}px` }"
            role="img"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.35" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                :d="linePath"
                fill="none"
                :stroke="color"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
                vector-effect="non-scaling-stroke"
            />
            <circle
                v-for="(point, index) in points"
                :key="index"
                :cx="point.x"
                :cy="point.y"
                r="3"
                :fill="color"
            >
                <title>{{ items[index].label }} — {{ items[index].value }}</title>
            </circle>
        </svg>
    </div>
</template>
