import { talkControl } from './talk-control';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('talkControl', talkControl);
});
