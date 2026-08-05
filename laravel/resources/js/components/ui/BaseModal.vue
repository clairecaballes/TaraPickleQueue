<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
    modelValue: Boolean,
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

function close() {
    emit('update:modelValue', false);
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

watch(
    () => props.modelValue,
    (open) => {
        document.body.classList.toggle('overflow-hidden', open);
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-to-class="opacity-0"
            appear
        >
            <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="fixed inset-0 bg-navy-950/70 backdrop-blur-sm" @click="closeOnBackdrop && close()" />

                <div class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-4">
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 translate-y-4 scale-[0.98]"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-to-class="opacity-0 translate-y-4 scale-[0.98]"
                        appear
                    >
                        <div
                            v-if="modelValue"
                            role="dialog"
                            aria-modal="true"
                            class="relative my-8 w-full rounded-t-2xl border border-white/10 bg-navy-900 shadow-2xl sm:rounded-2xl"
                            :class="maxWidth"
                            @click.stop
                        >
                            <div
                                v-if="title"
                                class="flex items-center justify-between border-b border-white/10 px-5 py-4"
                            >
                                <h2 class="text-lg font-bold text-white">{{ title }}</h2>
                                <button
                                    class="grid size-12 place-items-center rounded-full text-charcoal-300 transition hover:bg-white/10 hover:text-white"
                                    aria-label="Close"
                                    @click="close"
                                >
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                </button>
                            </div>

                            <div class="px-5 py-5">
                                <slot />
                            </div>

                            <div v-if="$slots.footer" class="border-t border-white/10 px-5 py-4">
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
