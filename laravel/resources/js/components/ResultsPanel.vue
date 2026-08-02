<script setup>
import { computed } from 'vue';

import { useTarapickleStore } from '../stores/tarapickle';
import { downloadBlob, downloadDataUrl, toCsv } from '../utils/format';
import PlayerFace from './PlayerFace.vue';
import Badge from './ui/Badge.vue';
import BaseButton from './ui/BaseButton.vue';
import SkillChip from './ui/SkillChip.vue';

const emit = defineEmits(['toast']);

const store = useTarapickleStore();

function winRate(player) {
    return player.gamesPlayed ? Math.round((player.wins / player.gamesPlayed) * 100) : 0;
}

/** Every player across every queue, ranked by wins then win % then name. */
const rows = computed(() =>
    [...store.allPlayers]
        .sort(
            (a, b) =>
                b.wins - a.wins
                || winRate(b) - winRate(a)
                || a.name.localeCompare(b.name),
        )
        .map((player, index) => ({ ...player, rank: index + 1 })),
);

const totalMatches = computed(() =>
    store.queues.reduce((sum, queue) => sum + queue.players.reduce((s, p) => s + p.gamesPlayed, 0), 0),
);

function rankClass(rank) {
    if (rank === 1) return 'bg-volt-300 text-navy-950 shadow-[0_2px_10px_-2px_rgb(255_214_10/0.6)]';
    if (rank === 2) return 'bg-charcoal-200 text-navy-950';
    if (rank === 3) return 'bg-amber-500 text-navy-950';
    return 'bg-white/10 text-charcoal-300';
}

/* ------------------------------------------------------------------ *
 * Export: CSV
 * ------------------------------------------------------------------ */
function exportCsv() {
    if (!rows.value.length) {
        emit('toast', 'Nothing to export yet — play a few matches first.');

        return;
    }

    const csv = toCsv(
        ['Rank', 'Player', 'Queue', 'Skill', 'Wins', 'Games', 'Win %'],
        rows.value.map((row) => [
            row.rank,
            row.name,
            row.queueName,
            row.skill ?? '',
            row.wins,
            row.gamesPlayed,
            `${winRate(row)}%`,
        ]),
    );

    downloadBlob(new Blob([csv], { type: 'text/csv;charset=utf-8' }), 'tarapickle-results.csv');
    emit('toast', 'Results downloaded as CSV 🎉');
}

/* ------------------------------------------------------------------ *
 * Export: PNG (branded leaderboard card drawn on a canvas)
 * ------------------------------------------------------------------ */
const COLUMNS = [
    { label: 'Rank', x: 56, align: 'left' },
    { label: 'Player', x: 150, align: 'left' },
    { label: 'Queue', x: 470, align: 'left' },
    { label: 'Skill', x: 690, align: 'left' },
    { label: 'W', x: 850, align: 'right' },
    { label: 'G', x: 910, align: 'right' },
    { label: 'Win %', x: 980, align: 'right' },
];

function truncateText(ctx, text, maxWidth) {
    let out = text;

    while (ctx.measureText(out).width > maxWidth && out.length > 1) {
        out = out.slice(0, -1);
    }

    return out.length === text.length ? out : `${out.slice(0, -1)}…`;
}

function exportPng() {
    if (!rows.value.length) {
        emit('toast', 'Nothing to export yet — play a few matches first.');

        return;
    }

    const data = rows.value.slice(0, 40);
    const width = 1100;
    const headerHeight = 210;
    const rowHeight = 46;
    const footerHeight = 120;
    const height = headerHeight + data.length * rowHeight + footerHeight;

    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, height);

    gradient.addColorStop(0, '#12203a');
    gradient.addColorStop(1, '#0b1426');

    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, width, height);

    // Volt accent bar + pickleball dot
    ctx.fillStyle = '#ffd60a';
    ctx.fillRect(0, 0, width, 8);

    ctx.beginPath();
    ctx.arc(width - 70, 70, 26, 0, Math.PI * 2);
    ctx.fillStyle = '#ffd60a';
    ctx.fill();
    ctx.fillStyle = '#0b1426';
    for (const [dx, dy] of [[0, -9], [0, 9], [-9, 0], [9, 0]]) {
        ctx.beginPath();
        ctx.arc(width - 70 + dx, 70 + dy, 4, 0, Math.PI * 2);
        ctx.fill();
    }

    // Title
    ctx.fillStyle = '#ffffff';
    ctx.font = "900 42px 'Bungee', 'Instrument Sans', sans-serif";
    ctx.fillText('Tara Pickle', 56, 84);
    ctx.fillStyle = '#ffd60a';
    ctx.fillText('Results', 56, 130);

    ctx.fillStyle = '#b1b1b8';
    ctx.font = '500 17px "Instrument Sans", sans-serif';
    ctx.fillText(
        `${new Date().toLocaleString([], { dateStyle: 'long', timeStyle: 'short' })}  ·  ${rows.value.length} players  ·  ${totalMatches.value} games played`,
        56,
        162,
    );

    // Column header row
    ctx.fillStyle = 'rgba(11, 20, 38, 0.55)';
    ctx.fillRect(0, headerHeight - 44, width, 44);
    ctx.fillStyle = '#8b8b95';
    ctx.font = '700 13px "Instrument Sans", sans-serif';

    for (const column of COLUMNS) {
        ctx.textAlign = column.align === 'right' ? 'right' : 'left';
        ctx.fillText(column.label.toUpperCase(), column.x, headerHeight - 15);
    }

    // Body rows
    data.forEach((row, index) => {
        const y = headerHeight + index * rowHeight;

        if (index % 2 === 0) {
            ctx.fillStyle = 'rgba(255, 255, 255, 0.03)';
            ctx.fillRect(0, y, width, rowHeight);
        }

        ctx.fillStyle = index === 0 ? '#ffd60a' : index === 1 ? '#d0d0d4' : index === 2 ? '#f59e0b' : '#8b8b95';
        ctx.font = '900 15px "Instrument Sans", sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(`#${row.rank}`, COLUMNS[0].x, y + 30);

        ctx.fillStyle = '#ffffff';
        ctx.font = '600 16px "Instrument Sans", sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(truncateText(ctx, row.name, 300), COLUMNS[1].x, y + 30);

        ctx.fillStyle = '#b1b1b8';
        ctx.font = '500 14px "Instrument Sans", sans-serif';
        ctx.fillText(truncateText(ctx, row.queueName, 200), COLUMNS[2].x, y + 30);

        ctx.fillStyle = row.skill === 'Advanced' ? '#fbbf24' : row.skill === 'Beginner' ? '#34d399' : row.skill ? '#ffd60a' : '#8b8b95';
        ctx.textAlign = 'left';
        ctx.fillText(row.skill ?? '—', COLUMNS[3].x, y + 30);

        ctx.fillStyle = '#ffd60a';
        ctx.font = '800 15px "Instrument Sans", sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(String(row.wins), COLUMNS[4].x, y + 30);

        ctx.fillStyle = '#b1b1b8';
        ctx.fillText(String(row.gamesPlayed), COLUMNS[5].x, y + 30);

        ctx.fillStyle = winRate(row) >= 50 ? '#34d399' : '#d0d0d4';
        ctx.fillText(`${winRate(row)}%`, COLUMNS[6].x, y + 30);
    });

    // Footer
    ctx.fillStyle = '#6f6f7a';
    ctx.font = '500 14px "Instrument Sans", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('Tara Pickle by Claire  ·  fair queues, cute critters, live stats', 56, height - 52);

    downloadDataUrl(canvas.toDataURL('image/png'), 'tarapickle-results.png');
    emit('toast', 'Results downloaded as PNG 🎉');
}

/* ------------------------------------------------------------------ *
 * Share (Web Share API / clipboard fallback)
 * ------------------------------------------------------------------ */
async function shareResults() {
    if (!rows.value.length) {
        emit('toast', 'Nothing to share yet — play a few matches first.');

        return;
    }

    const podium = rows.value
        .slice(0, 3)
        .map((row) => `${row.rank}. ${row.name} — ${row.wins} win${row.wins === 1 ? '' : 's'}`)
        .join('\n');
    const text = `Tara Pickle results 🏆\n${podium}\n\n${rows.value.length} players · ${totalMatches.value} games`;

    if (navigator.share) {
        try {
            await navigator.share({ title: 'Tara Pickle results', text, url: window.location.href });

            return;
        } catch {
            // User cancelled — fall through to copy.
        }
    }

    try {
        await navigator.clipboard.writeText(`${text}\n${window.location.href}`);
        emit('toast', 'Results summary + link copied to clipboard 📋');
    } catch {
        emit('toast', 'Could not share from this browser.');
    }
}

</script>

<template>
    <div class="space-y-5">
        <!-- Action bar -->
        <section class="flex flex-wrap items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4 shadow-card">
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-black tracking-tight text-white">Session results</h2>
                <p class="text-xs text-charcoal-300">
                    {{ rows.length }} player{{ rows.length === 1 ? '' : 's' }} · {{ totalMatches }} game{{ totalMatches === 1 ? '' : 's' }} played · ranked live
                </p>
            </div>

            <BaseButton variant="secondary" size="sm" @click="exportCsv">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4" />
                </svg>
                CSV
            </BaseButton>

            <BaseButton variant="secondary" size="sm" @click="exportPng">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h2l2-2h6l2 2h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm9 8a3 3 0 100-6 3 3 0 000 6z" />
                </svg>
                PNG
            </BaseButton>

            <BaseButton variant="secondary" size="sm" @click="shareResults">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 12v6a2 2 0 002 2h12a2 2 0 002-2v-6M16 6l-4-4m0 0L8 6m4-4v12" />
                </svg>
                Share
            </BaseButton>
        </section>

        <!-- Leaderboard -->
        <section class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] shadow-card">
            <div class="flex items-center justify-between border-b border-white/10 bg-navy-950/40 px-4 py-3 sm:px-5">
                <h3 class="text-sm font-black tracking-tight text-white">🏆 Overall leaderboard</h3>
                <Badge color="volt" size="sm">{{ rows.length }} ranked</Badge>
            </div>

            <div class="overflow-x-auto">
                <table v-if="rows.length" class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b border-white/10 bg-navy-950/30 text-[10px] uppercase tracking-wider text-charcoal-400">
                            <th class="px-4 py-2.5 text-left font-semibold">#</th>
                            <th class="px-2 py-2.5 text-left font-semibold">Player</th>
                            <th class="px-2 py-2.5 text-left font-semibold">Queue</th>
                            <th class="px-2 py-2.5 text-left font-semibold">Skill</th>
                            <th class="px-2 py-2.5 text-right font-semibold">Wins</th>
                            <th class="px-2 py-2.5 text-right font-semibold">Games</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Win %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="row in rows"
                            :key="`${row.queueId}-${row.id}`"
                            class="transition hover:bg-white/[0.03]"
                        >
                            <td class="px-4 py-2.5">
                                <span
                                    class="grid size-6 place-items-center rounded-full text-[11px] font-black"
                                    :class="rankClass(row.rank)"
                                >
                                    {{ row.rank }}
                                </span>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <PlayerFace :player="row" size="sm" />
                                    <span class="truncate text-sm font-semibold text-white">{{ row.name }}</span>
                                </div>
                            </td>
                            <td class="px-2 py-2.5 text-xs text-charcoal-300">{{ row.queueName }}</td>
                            <td class="px-2 py-2.5"><SkillChip :skill="row.skill" /></td>
                            <td class="px-2 py-2.5 text-right font-black text-volt-300">{{ row.wins }}</td>
                            <td class="px-2 py-2.5 text-right text-xs text-charcoal-300">{{ row.gamesPlayed }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <span
                                    class="text-xs font-bold"
                                    :class="winRate(row) >= 50 ? 'text-emerald-300' : 'text-charcoal-200'"
                                >
                                    {{ winRate(row) }}%
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="px-5 py-14 text-center text-sm text-charcoal-300">
                    No results yet — play a match to light up the board, then download or share the standings.
                </p>
            </div>
        </section>

    </div>
</template>
