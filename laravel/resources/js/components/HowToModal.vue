<script setup>
import BaseModal from './ui/BaseModal.vue';

defineProps({
    modelValue: Boolean,
});

defineEmits(['update:modelValue']);

const steps = [
    {
        icon: '👋',
        tone: 'text-volt-200 bg-volt-300/10 ring-volt-300/30',
        title: 'Add players',
        body: 'Type a name into "Add to Queue" and hit enter — each guest gets a cute animal avatar instantly. Tag skill levels (Beginner / Intermediate / Advanced) for balanced matchmaking.',
    },
    {
        icon: '🗂️',
        tone: 'text-sky-200 bg-sky-400/10 ring-sky-400/30',
        title: 'Manage queue tabs',
        body: 'Use the Queues / Queue Board / Results tabs up top. Queue cards are accordions — expanding one neatly collapses the rest, so long lists stay tidy. The Queue Board shows every player in line with position, avatar, skill and status. "End Queue" closes a session to new entries while you manage active courts.',
    },
    {
        icon: '🎾',
        tone: 'text-emerald-300 bg-emerald-400/10 ring-emerald-400/30',
        title: 'Assign courts',
        body: 'Each session runs up to 4 courts at once. Hit "Randomize Court (4)" to fairly call the next four — fewest games go first. When a match ends, that court auto-fills with the next four while the others keep playing.',
    },
    {
        icon: '⏱️',
        tone: 'text-amber-300 bg-amber-500/10 ring-amber-500/30',
        title: 'Score matches',
        body: 'A live timer starts automatically when four players hit the court. When the rally ends, tap "Game over", pick the winning team and enter the final score (e.g. 11 – 8). Each court has its own Close button to wrap up a pending call, plus a sound toggle for its announcements.',
    },
    {
        icon: '🏆',
        tone: 'text-volt-200 bg-volt-300/10 ring-volt-300/30',
        title: 'Track stats',
        body: 'The leaderboard updates wins, games and win % live. The Results tab ranks everyone across sessions. Every queue card has its own Share, Snapshot (PNG / PDF / CSV) and "Scan to View" buttons, so each session\'s board can be shared or captured independently — the QR opens a live view with who\'s on deck.',
    },
    {
        icon: '💾',
        tone: 'text-charcoal-200 bg-white/10 ring-white/15',
        title: 'Never lose progress',
        body: 'Everything — queue tabs, rosters, multi-court states, open/closed statuses and rankings — saves to this browser automatically. Refresh or close the page and it all comes right back.',
    },
];

const quickTips = [
    '🔗 Fixed Pair keeps two players on the same team.',
    '☕ "Taking a break" skips a player without removing their stats.',
    '🔊 Turn on court-call sound to hear player names announced — mute per court.',
    '🔄 Use the swap icon on a called player to substitute or defer them.',
    '🪗 Open one queue to auto-collapse the others.',
    '📸 Each queue has its own share, snapshot & scan-to-view buttons.',
];
</script>

<template>
    <BaseModal :model-value="modelValue" title="How to use Pickle Ta Bai!" max-width="max-w-2xl" @update:model-value="$emit('update:modelValue', $event)">
        <p class="mb-5 text-sm text-charcoal-300">
            Fair queues, live courts and cute critters — here's the whole flow in a minute.
        </p>

        <ol class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <li
                v-for="(step, index) in steps"
                :key="step.title"
                class="flex gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:border-white/20 hover:bg-white/[0.05]"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl text-lg ring-1 ring-inset"
                    :class="step.tone"
                >
                    {{ step.icon }}
                </span>
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-bold text-white">
                        <span class="font-mono text-[10px] text-charcoal-400">{{ index + 1 }}</span>
                        {{ step.title }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-charcoal-300">{{ step.body }}</p>
                </div>
            </li>
        </ol>

        <div class="mt-5 rounded-2xl border border-volt-300/20 bg-volt-300/[0.06] px-4 py-3.5">
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-volt-200">Quick tips</p>
            <ul class="grid grid-cols-1 gap-1.5 text-xs text-charcoal-200 sm:grid-cols-2">
                <li v-for="tip in quickTips" :key="tip" class="flex items-start gap-1.5">
                    <span class="mt-px">{{ tip }}</span>
                </li>
            </ul>
        </div>
    </BaseModal>
</template>
