import gsap from 'gsap';

export function talkControl({ uploadUrl, csrfToken }) {
    return {
        recording: false,
        mediaRecorder: null,
        chunks: [],
        barTimeline: null,
        bars: [],

        toggle() {
            if (this.recording) {
                this.stop();
            } else {
                this.start();
            }
        },

        async start() {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(stream);
            this.chunks = [];

            this.mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) {
                    this.chunks.push(event.data);
                }
            });

            this.mediaRecorder.start();
            this.recording = true;
            this.animateBars(true);
        },

        async stop() {
            if (!this.mediaRecorder) {
                return;
            }

            const recorder = this.mediaRecorder;

            await new Promise((resolve) => {
                recorder.addEventListener('stop', resolve, { once: true });

                if (recorder.state === 'recording') {
                    recorder.requestData();
                    recorder.stop();
                }

                recorder.stream.getTracks().forEach((track) => track.stop());
            });

            this.recording = false;
            this.animateBars(false);
            this.mediaRecorder = null;

            const blob = new Blob(this.chunks, { type: 'audio/webm' });

            if (blob.size === 0) {
                return;
            }
            const formData = new FormData();
            formData.append('audio', blob, 'recording.webm');

            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            this.$wire.trackVoiceTurn(data.uuid);
        },

        initBars(bars) {
            if (!bars) {
                return;
            }

            this.bars = [...bars.querySelectorAll('[data-talk-bar]')];
            this.barTimeline = gsap.timeline({ paused: true, repeat: -1 });

            this.bars.forEach((bar, index) => {
                this.barTimeline.to(
                    bar,
                    {
                        scaleY: 1.6,
                        duration: 0.35 + index * 0.05,
                        yoyo: true,
                        repeat: 1,
                        ease: 'sine.inOut',
                    },
                    index * 0.08,
                );
            });
        },

        animateBars(listening) {
            if (!this.barTimeline) {
                return;
            }

            if (listening) {
                this.barTimeline.restart();
            } else {
                this.barTimeline.pause(0);
                gsap.set(this.bars, { scaleY: 1 });
            }
        },
    };
}
