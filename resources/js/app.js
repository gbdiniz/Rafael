import { speechPlayback } from './speech-playback';
import { talkControl } from './talk-control';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('speechPlayback', speechPlayback);
    window.Alpine.data('talkControl', talkControl);
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}
