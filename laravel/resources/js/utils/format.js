/** 0 -> "1st", 1 -> "2nd", 2 -> "3rd", 3 -> "4th", ... */
export function ordinal(index) {
    const n = index + 1;
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;

    return `${n}${s[(v - 20) % 10] || s[v] || s[0]}`;
}

/** Seconds -> "12:34" (under an hour) or "1h 02m". */
export function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

/**
 * Elapsed time since a match start, ticking from the given now.
 * Accepts both ISO strings and numeric millisecond timestamps (Date.parse
 * of a raw number is NaN, which silently broke the live match timer).
 */
export function elapsedSince(start, now = Date.now()) {
    const startedAt = typeof start === 'number' ? start : Date.parse(start);

    return Math.max(0, (now - startedAt) / 1000);
}

/** Quote-aware CSV encoder: one row per array, escaped for commas/quotes. */
export function toCsv(headers, rows) {
    const escape = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;

    return [headers.map(escape).join(','), ...rows.map((row) => row.map(escape).join(','))].join('\n');
}

/** Trigger a browser download from a Blob. */
export function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
}

/** Trigger a browser download from a data URL (e.g. canvas PNG). */
export function downloadDataUrl(dataUrl, filename) {
    const anchor = document.createElement('a');

    anchor.href = dataUrl;
    anchor.download = filename;
    anchor.click();
}
