/**
 * Per-queue Share & Snapshot helpers — deep links, CSV / PNG / PDF exports and
 * the Web Share API. Each queue's roster + results can be captured or shared
 * independently from its card.
 */
import { downloadBlob, downloadDataUrl, toCsv } from './format';

/** Deep link that opens the dashboard focused on one queue (history-mode router). */
export function queueLink(queueId) {
    return `${window.location.origin}/play?queue=${encodeURIComponent(queueId)}`;
}

function winRate(player) {
    return player.gamesPlayed ? Math.round((player.wins / player.gamesPlayed) * 100) : 0;
}

/** Wins desc, win rate as tie-breaker, then name — mirrors the card's board. */
function leaderboard(queue) {
    return [...queue.players].sort(
        (a, b) => b.wins - a.wins || winRate(b) - winRate(a) || a.name.localeCompare(b.name),
    );
}

function slugify(name) {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'queue';
}

/** Escape user-supplied text before it is injected into generated HTML. */
function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ------------------------------------------------------------------ *
 * CSV — leaderboard rows for this queue
 * ------------------------------------------------------------------ */
export function queueCsv(queue) {
    const rows = leaderboard(queue);

    return toCsv(
        ['Rank', 'Player', 'Wins', 'Games', 'Win %'],
        rows.map((player, index) => [
            index + 1,
            player.name,
            player.wins,
            player.gamesPlayed,
            `${winRate(player)}%`,
        ]),
    );
}

export function downloadQueueCsv(queue) {
    const csv = queueCsv(queue);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });

    downloadBlob(blob, `tarapickle-${slugify(queue.name)}.csv`);

    return rowsOf(queue).length;
}

function rowsOf(queue) {
    return leaderboard(queue);
}

/* ------------------------------------------------------------------ *
 * PNG — branded snapshot of the queue drawn on a canvas
 * ------------------------------------------------------------------ */
function truncateText(ctx, text, maxWidth) {
    let out = text;

    while (ctx.measureText(out).width > maxWidth && out.length > 1) {
        out = out.slice(0, -1);
    }

    return out.length === text.length ? out : `${out.slice(0, -1)}…`;
}

/** Status line for a court, e.g. "Court 1 · LIVE · 12:04" or "Court 2 · free". */
function courtLine(court, queue) {
    const onDeck = queue.players.filter(
        (player) => player.courtId === court.id && player.status !== 'waiting',
    );

    if (court.activeMatch) {
        return `${court.label} · LIVE · ${onDeck.map((p) => p.name.split(' ')[0]).join(' & ')}`;
    }

    if (onDeck.length) {
        return `${court.label} · on deck · ${onDeck.map((p) => p.name.split(' ')[0]).join(', ')}`;
    }

    return `${court.label} · free`;
}

export function queuePng(queue) {
    // Cap the canvas like the global export — a very long queue would
    // otherwise produce an unreasonably tall image.
    const allRows = rowsOf(queue);
    const capped = allRows.length > 40;
    const rows = allRows.slice(0, 40);
    const courts = queue.courts ?? [];
    const width = 1100;
    const headerHeight = 190;
    const courtBlockHeight = courts.length ? courts.length * 34 + 30 : 0;
    const rowHeight = 44;
    const footerHeight = 110;
    const height = headerHeight + courtBlockHeight + rows.length * rowHeight + footerHeight;

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
    ctx.font = "900 40px 'Bungee', 'Instrument Sans', sans-serif";
    ctx.fillText('Tara Pickle', 56, 82);
    ctx.fillStyle = '#ffd60a';
    ctx.fillText(truncateText(ctx, queue.name, 520), 56, 126);

    ctx.fillStyle = '#b1b1b8';
    ctx.font = '500 16px "Instrument Sans", sans-serif';
    ctx.fillText(
        `${new Date(queue.createdAt).toLocaleString([], { dateStyle: 'long', timeStyle: 'short' })}  ·  ${queue.players.length} players  ·  ${queue.courts.length} court${queue.courts.length === 1 ? '' : 's'}  ·  ${queue.open ? 'open' : 'closed'}`,
        56,
        156,
    );

    // Courts block
    if (courts.length) {
        ctx.fillStyle = 'rgba(11, 20, 38, 0.55)';
        ctx.fillRect(0, headerHeight, width, courtBlockHeight);
        ctx.fillStyle = '#d0d0d4';
        ctx.font = '600 14px "Instrument Sans", sans-serif';
        ctx.textAlign = 'left';

        courts.forEach((court, index) => {
            const y = headerHeight + 26 + index * 34;

            ctx.fillStyle = court.activeMatch ? '#ffd60a' : '#8b8b95';
            ctx.beginPath();
            ctx.arc(46, y - 6, 5, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#d0d0d4';
            ctx.fillText(truncateText(ctx, courtLine(court, queue), 900), 64, y);
        });
    }

    // Leaderboard header row
    const boardTop = headerHeight + courtBlockHeight;
    ctx.fillStyle = 'rgba(11, 20, 38, 0.55)';
    ctx.fillRect(0, boardTop, width, 44);
    ctx.fillStyle = '#8b8b95';
    ctx.font = '700 13px "Instrument Sans", sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('RANK', 56, boardTop + 28);
    ctx.fillText('PLAYER', 150, boardTop + 28);
    ctx.textAlign = 'right';
    ctx.fillText('WINS', 850, boardTop + 28);
    ctx.fillText('GAMES', 910, boardTop + 28);
    ctx.fillText('WIN %', 980, boardTop + 28);

    // Leaderboard rows
    rows.forEach((row, index) => {
        const y = boardTop + 44 + index * rowHeight;

        if (index % 2 === 0) {
            ctx.fillStyle = 'rgba(255, 255, 255, 0.03)';
            ctx.fillRect(0, y, width, rowHeight);
        }

        ctx.fillStyle = index === 0 ? '#ffd60a' : index === 1 ? '#d0d0d4' : index === 2 ? '#f59e0b' : '#8b8b95';
        ctx.font = '900 15px "Instrument Sans", sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(`#${index + 1}`, 56, y + 29);

        ctx.fillStyle = '#ffffff';
        ctx.font = '600 16px "Instrument Sans", sans-serif';
        ctx.fillText(truncateText(ctx, row.name, 380), 150, y + 29);

        ctx.fillStyle = '#ffd60a';
        ctx.font = '800 15px "Instrument Sans", sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(String(row.wins), 850, y + 29);

        ctx.fillStyle = '#b1b1b8';
        ctx.fillText(String(row.gamesPlayed), 910, y + 29);

        ctx.fillStyle = winRate(row) >= 50 ? '#34d399' : '#d0d0d4';
        ctx.fillText(`${winRate(row)}%`, 980, y + 29);
    });

    // Footer
    ctx.fillStyle = '#6f6f7a';
    ctx.font = '500 14px "Instrument Sans", sans-serif';
    ctx.textAlign = 'left';

    if (capped) {
        ctx.fillText(`… and ${allRows.length - rows.length} more players`, 56, height - 66);
    }

    ctx.fillText('Tara Pickle by Claire  ·  fair queues, cute critters, live stats', 56, height - 48);

    downloadDataUrl(canvas.toDataURL('image/png'), `tarapickle-${slugify(queue.name)}.png`);

    return rows.length;
}

/* ------------------------------------------------------------------ *
 * PDF — print-ready snapshot rendered in a hidden iframe, then printed.
 * The browser's "Save as PDF" target turns it into a shareable file.
 * ------------------------------------------------------------------ */
export function queuePdf(queue) {
    const rows = rowsOf(queue);
    const courts = queue.courts ?? [];

    const body = document.createElement('iframe');

    body.style.position = 'fixed';
    body.style.right = '0';
    body.style.bottom = '0';
    body.style.width = '0';
    body.style.height = '0';
    body.style.border = '0';

    document.body.appendChild(body);

    const doc = body.contentWindow.document;

    doc.open();
    doc.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Tara Pickle — ${queue.name}</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; background: #0b1426; color: #e6e6e8; }
    .wrap { max-width: 760px; margin: 0 auto; padding: 40px 32px; }
    .bar { height: 8px; background: #ffd60a; border-radius: 4px; margin-bottom: 28px; }
    h1 { margin: 0; font-size: 34px; letter-spacing: -0.02em; color: #fff; }
    h1 span { color: #ffd60a; }
    h2 { margin: 4px 0 24px; font-size: 24px; color: #ffd60a; }
    .meta { color: #8b8b95; font-size: 13px; margin-bottom: 28px; }
    .courts { display: grid; gap: 8px; margin-bottom: 28px; }
    .court { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid rgb(255 255 255 / 0.12); border-radius: 12px; background: rgb(255 255 255 / 0.04); font-size: 13px; }
    .dot { width: 9px; height: 9px; border-radius: 999px; background: #8b8b95; }
    .dot.live { background: #ffd60a; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { text-align: left; color: #8b8b95; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; padding: 8px 10px; border-bottom: 1px solid rgb(255 255 255 / 0.12); }
    td { padding: 9px 10px; border-bottom: 1px solid rgb(255 255 255 / 0.06); }
    .rank { font-weight: 900; }
    .r1 { color: #ffd60a; } .r2 { color: #d0d0d4; } .r3 { color: #f59e0b; } .rx { color: #8b8b95; }
    .w { color: #ffd60a; font-weight: 800; text-align: right; }
    .num { text-align: right; color: #b1b1b8; }
    footer { margin-top: 28px; color: #6f6f7a; font-size: 12px; }
    @media print { body { background: #0b1426; } }
</style>
</head>
<body>
    <div class="wrap">
        <div class="bar"></div>
        <h1>Tara<span>Pickle</span></h1>
        <h2>${esc(queue.name)}</h2>
        <p class="meta">${new Date(queue.createdAt).toLocaleString([], { dateStyle: 'long', timeStyle: 'short' })}  ·  ${queue.players.length} player${queue.players.length === 1 ? '' : 's'}  ·  ${courts.length} court${courts.length === 1 ? '' : 's'}  ·  ${queue.open ? 'open' : 'closed'}</p>
        <div class="courts">
            ${courts.map((court) => {
                const onDeck = queue.players.filter((p) => p.courtId === court.id && p.status !== 'waiting');
                const live = Boolean(court.activeMatch);
                return `<div class="court"><span class="dot${live ? ' live' : ''}"></span><b>${esc(court.label)}</b><span style="color:#8b8b95">·</span><span>${live ? 'LIVE' : onDeck.length ? 'on deck' : 'free'}${onDeck.length ? ' — ' + onDeck.map((p) => esc(p.name)).join(', ') : ''}</span></div>`;
            }).join('')}
        </div>
        <table>
            <thead><tr><th>Rank</th><th>Player</th><th style="text-align:right">Wins</th><th style="text-align:right">Games</th><th style="text-align:right">Win %</th></tr></thead>
            <tbody>
            ${rows.map((player, index) => `
                <tr>
                    <td class="rank ${index === 0 ? 'r1' : index === 1 ? 'r2' : index === 2 ? 'r3' : 'rx'}">#${index + 1}</td>
                    <td style="font-weight:600">${esc(player.name)}</td>
                    <td class="w">${player.wins}</td>
                    <td class="num">${player.gamesPlayed}</td>
                    <td class="num">${winRate(player)}%</td>
                </tr>`).join('')}
            </tbody>
        </table>
        <footer>Tara Pickle by Claire  ·  fair queues, cute critters, live stats</footer>
    </div>
</body>
</html>`);
    doc.close();

    let cleanedUp = false;

    const cleanup = () => {
        if (!cleanedUp) {
            cleanedUp = true;
            document.body.removeChild(body);
        }
    };

    body.contentWindow.onafterprint = cleanup;

    // Fallback: onafterprint is skipped when the dialog is cancelled (and on
    // some browsers), so never leak the hidden iframe.
    window.setTimeout(cleanup, 60_000);

    body.contentWindow.focus();

    // Give the iframe a tick to lay out before opening the print dialog.
    window.setTimeout(() => body.contentWindow.print(), 120);

    return rows.length;
}

/* ------------------------------------------------------------------ *
 * Share — Web Share API with a clipboard fallback
 * ------------------------------------------------------------------ */
export async function shareQueue(queue) {
    const url = queueLink(queue.id);
    const podium = rowsOf(queue)
        .slice(0, 3)
        .map((player, index) => `${index + 1}. ${player.name} — ${player.wins} win${player.wins === 1 ? '' : 's'}`)
        .join('\n');
    const text = `Tara Pickle — ${queue.name} 🐾\n${podium}\n\n${queue.players.length} players · ${queue.courts.length} court${queue.courts.length === 1 ? '' : 's'} on the session`;

    if (navigator.share) {
        try {
            await navigator.share({ title: `Tara Pickle — ${queue.name}`, text, url });

            return 'shared';
        } catch {
            // User cancelled — fall through to copy.
        }
    }

    try {
        await navigator.clipboard.writeText(`${text}\n${url}`);

        return 'copied';
    } catch {
        return null;
    }
}
