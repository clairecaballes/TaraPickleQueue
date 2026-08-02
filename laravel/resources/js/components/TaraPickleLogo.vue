<script setup>
// Crossed paddles + perforated pickleball — the TaraPickle brand mark.
// Inherits its rendered size from the wrapping element (class="size-*").
</script>

<template>
    <svg viewBox="0 0 64 64" fill="none" aria-hidden="true">
        <!-- Paddle A -->
        <g transform="rotate(40 32 32)">
            <rect x="28.5" y="25" width="7" height="21" rx="3.5" fill="#ffffff" stroke="#0b1426" stroke-width="2.5" />
            <ellipse cx="32" cy="14" rx="9.5" ry="12" fill="#ffffff" stroke="#0b1426" stroke-width="2.5" />
            <line x1="30.2" y1="30.5" x2="33.8" y2="30.5" stroke="#0b1426" stroke-width="1.6" stroke-linecap="round" />
            <line x1="30.2" y1="34.5" x2="33.8" y2="34.5" stroke="#0b1426" stroke-width="1.6" stroke-linecap="round" />
        </g>
        <!-- Paddle B -->
        <g transform="rotate(-40 32 32)">
            <rect x="28.5" y="25" width="7" height="21" rx="3.5" fill="#ffffff" stroke="#0b1426" stroke-width="2.5" />
            <ellipse cx="32" cy="14" rx="9.5" ry="12" fill="#ffffff" stroke="#0b1426" stroke-width="2.5" />
            <line x1="30.2" y1="30.5" x2="33.8" y2="30.5" stroke="#0b1426" stroke-width="1.6" stroke-linecap="round" />
            <line x1="30.2" y1="34.5" x2="33.8" y2="34.5" stroke="#0b1426" stroke-width="1.6" stroke-linecap="round" />
        </g>
        <!-- Ball with perforations -->
        <circle cx="32" cy="32" r="10" fill="#ffd60a" stroke="#0b1426" stroke-width="2.5" />
        <g fill="#0b1426">
            <circle cx="32" cy="26.8" r="1.5" />
            <circle cx="32" cy="37.2" r="1.5" />
            <circle cx="26.8" cy="32" r="1.5" />
            <circle cx="37.2" cy="32" r="1.5" />
            <circle cx="28.7" cy="28.7" r="1.5" />
            <circle cx="35.3" cy="35.3" r="1.5" />
        </g>
    </svg>
</template>
