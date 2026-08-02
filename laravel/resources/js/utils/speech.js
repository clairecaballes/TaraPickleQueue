/**
 * Text-to-speech "court call" alerts via the Web Speech API.
 * Falls back silently on browsers without speechSynthesis support.
 */
export function announce(text) {
    if (typeof window === 'undefined' || !('speechSynthesis' in window)) {
        return;
    }

    // Cancel any in-flight announcement so calls are never queued up stale.
    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);

    utterance.rate = 1;
    utterance.pitch = 1.05;
    utterance.volume = 1;

    window.speechSynthesis.speak(utterance);
}

/** Quick, human-friendly preview used when the toggle is switched on. */
export function soundOnPreview() {
    announce('Sound on — court calls will be announced.');
}
