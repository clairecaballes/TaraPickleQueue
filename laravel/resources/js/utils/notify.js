/**
 * Attention feedback for "your turn" moments — a short ascending chime via the
 * Web Audio API (no audio assets needed), a vibration pattern on supporting
 * mobile browsers, and a browser notification when permission is granted.
 */
export function playSummon() {
    try {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('TaraPickle', {
                body: 'You’re up! Head to the court.',
                icon: '/favicon.ico',
            });
        }
    } catch {
        // Browser notifications are best-effort.
    }

    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;

        if (!AudioContext) {
            return;
        }

        const ctx = new AudioContext();

        // Three quick ascending notes — unmistakable "you're up".
        [[880, 0], [1046, 0.14], [1318, 0.28]].forEach(([frequency, at]) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.value = frequency;

            gain.gain.setValueAtTime(0.0001, ctx.currentTime + at);
            gain.gain.exponentialRampToValueAtTime(0.28, ctx.currentTime + at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + at + 0.16);

            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + at);
            osc.stop(ctx.currentTime + at + 0.18);
        });

        window.setTimeout(() => ctx.close(), 900);
    } catch {
        // Audio is best-effort — never break the UI over it.
    }
}

/** Haptic feedback on supporting devices. */
export function vibrate(pattern = [200]) {
    if ('vibrate' in navigator) {
        navigator.vibrate(pattern);
    }
}
