/**
 * Skill rating tags — used for balanced matchmaking (pairing High + Low),
 * shown on player cards, the queue board and the results leaderboard.
 */
export const SKILLS = [
    {
        value: 'Beginner',
        emoji: '🌱',
        label: 'Beginner',
        chip: 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/30',
        dot: 'bg-emerald-400',
    },
    {
        value: 'Intermediate',
        emoji: '⚡',
        label: 'Intermediate',
        chip: 'bg-volt-300/10 text-volt-200 ring-volt-300/30',
        dot: 'bg-volt-300',
    },
    {
        value: 'Advanced',
        emoji: '🔥',
        label: 'Advanced',
        chip: 'bg-amber-500/10 text-amber-300 ring-amber-500/30',
        dot: 'bg-amber-400',
    },
];

/** 1 = Beginner, 2 = Intermediate, 3 = Advanced. Unrated players count as 2. */
export function skillValue(skill) {
    return { Beginner: 1, Intermediate: 2, Advanced: 3 }[skill] ?? 2;
}

export function skillMeta(skill) {
    return SKILLS.find((entry) => entry.value === skill) ?? null;
}

/** Reusable tailwind classes for an inline skill chip. */
export function skillChipClass(skill) {
    return skillMeta(skill)?.chip ?? 'bg-white/10 text-charcoal-200 ring-white/15';
}
