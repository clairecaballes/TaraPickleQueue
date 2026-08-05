/**
 * Outdoor Daylight theme — a high-contrast, sunlight-readable palette.
 *
 * The light theme is implemented by setting `data-theme="daylight"` on
 * <html>; `resources/css/app.css` remaps the Tailwind design tokens inside
 * that scope, so every component re-themes automatically.
 */
const THEME_KEY = 'tarapickle:theme';
const DAYLIGHT = 'daylight';

/** Current theme: 'dark' (default) or 'daylight'. */
export function getTheme() {
    return localStorage.getItem(THEME_KEY) === DAYLIGHT ? DAYLIGHT : 'dark';
}

/** Apply a theme to the document root (safe to call before mount). */
export function applyTheme(theme) {
    document.documentElement.dataset.theme = theme === DAYLIGHT ? DAYLIGHT : 'dark';
}

/** Restore the persisted theme once on app boot. */
export function initTheme() {
    applyTheme(getTheme());
}

/** Persist + apply a theme and return the new value. */
export function setTheme(theme) {
    const next = theme === DAYLIGHT ? DAYLIGHT : 'dark';

    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);

    return next;
}

/** Flip between dark and daylight. */
export function toggleTheme() {
    return setTheme(getTheme() === DAYLIGHT ? 'dark' : DAYLIGHT);
}
