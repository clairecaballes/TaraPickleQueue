<script setup>
defineProps({
    player: { type: Object, required: true }, // { id, name, avatarUrl, avatarEmoji }
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    editable: Boolean,
});

const emit = defineEmits(['edit']);

/** If the photo fails to load, fall back to the emoji underneath it. */
function hideBrokenImage(event) {
    event.target.style.display = 'none';
}
</script>

<template>
    <span
        class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-navy-600/70 ring-2 ring-navy-950/70"
        :class="{
            'size-7': size === 'sm',
            'size-9': size === 'md',
            'size-12': size === 'lg',
            'size-16': size === 'xl',
        }"
        :title="editable ? `${player.name} — tap to change avatar` : player.name"
    >
        <!-- Emoji fallback (visible while the photo loads or if it errors) -->
        <span
            class="absolute inset-0 grid place-items-center leading-none"
            :class="{
                'text-sm': size === 'sm',
                'text-lg': size === 'md',
                'text-2xl': size === 'lg',
                'text-3xl': size === 'xl',
            }"
        >
            {{ player.avatarEmoji ?? '🐾' }}
        </span>

        <img
            :src="player.avatarUrl"
            :alt="player.name"
            loading="lazy"
            class="absolute inset-0 size-full object-cover"
            @error="hideBrokenImage"
        />

        <!-- Subtle camera affordance when editable -->
        <button
            v-if="editable"
            type="button"
            class="absolute inset-0 grid cursor-pointer place-items-center bg-navy-950/55 text-volt-200 opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none"
            :aria-label="`Change ${player.name}'s avatar`"
            @click.stop="emit('edit', player)"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.66-.9l.82-1.2A2 2 0 0110.16 4h3.68a2 2 0 011.75 1l.82 1.2a2 2 0 001.66.9H19a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                />
                <circle cx="12" cy="13" r="3.5" />
            </svg>
        </button>

        <!-- Always-visible corner badge on touch devices / when not hovering -->
        <span
            v-if="editable"
            class="absolute -bottom-0.5 -right-0.5 grid size-4.5 place-items-center rounded-full bg-volt-300 text-navy-950 shadow"
            aria-hidden="true"
        >
            <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.66-.9l.82-1.2A2 2 0 0110.16 4h3.68a2 2 0 011.75 1l.82 1.2a2 2 0 001.66.9H19a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                />
                <circle cx="12" cy="13" r="3.5" />
            </svg>
        </span>
    </span>
</template>
