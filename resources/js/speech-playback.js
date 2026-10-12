let audioContext = null;

export function unlockAudio() {
    const context = sharedAudioContext();

    if (context && context.state === 'suspended') {
        return context.resume();
    }

    return Promise.resolve();
}

export function speechPlayback({ streamUrl, csrfToken, speakOnLoad }) {
    return {
        streamUrl,
        csrfToken,
        speakOnLoad,
        waitingForTap: false,
        blocked: false,
        failure: 'Não foi possível falar. A mensagem está na tela.',
        playTimer: null,
        abortController: null,
        sources: [],

        schedulePlay() {
            clearTimeout(this.playTimer);
            this.playTimer = setTimeout(() => {
                this.startPlayback();
            }, 0);
        },

        async tap() {
            if (! this.waitingForTap) {
                return;
            }

            await unlockAudio();
            this.waitingForTap = false;
            await this.startPlayback();
        },

        async startPlayback() {
            this.stopPlayback();
            this.blocked = false;

            const context = sharedAudioContext();

            if (! context) {
                this.blocked = true;

                return;
            }

            if (context.state === 'suspended') {
                try {
                    await context.resume();
                } catch {
                    this.waitingForTap = true;

                    return;
                }
            }

            if (context.state === 'suspended') {
                this.waitingForTap = true;

                return;
            }

            this.waitingForTap = false;
            this.abortController = new AbortController();

            let response;

            try {
                response = await fetch(this.streamUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        Accept: 'audio/wav',
                    },
                    signal: this.abortController.signal,
                });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.blocked = true;

                return;
            }

            if (! response.ok || ! response.body) {
                this.blocked = true;

                return;
            }

            try {
                await playWavStream(response.body, context, this.abortController.signal, this.sources);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                this.blocked = true;
            }
        },

        stopPlayback() {
            this.abortController?.abort();
            this.abortController = null;

            this.sources.forEach((source) => {
                try {
                    source.stop();
                } catch {
                    // Already stopped.
                }
            });

            this.sources = [];
        },
    };
}

function sharedAudioContext() {
    const Context = window.AudioContext || window.webkitAudioContext;

    if (! Context) {
        return null;
    }

    if (! audioContext) {
        audioContext = new Context();
    }

    return audioContext;
}

async function playWavStream(body, context, signal, sources) {
    const reader = body.getReader();
    let buffered = new Uint8Array(0);
    let header = null;
    let nextTime = context.currentTime;

    while (! signal.aborted) {
        const { done, value } = await reader.read();

        if (value) {
            buffered = concatBytes(buffered, value);
        }

        if (! header) {
            header = parseWavHeader(buffered);

            if (header) {
                buffered = buffered.slice(header.dataOffset);
            }
        }

        if (header) {
            const frameSize = header.blockAlign;
            const usable = buffered.length - (buffered.length % frameSize);

            if (usable > 0) {
                nextTime = schedulePcm(context, sources, nextTime, buffered.slice(0, usable), header);
                buffered = buffered.slice(usable);
            }
        }

        if (done) {
            break;
        }
    }
}

function concatBytes(left, right) {
    const merged = new Uint8Array(left.length + right.length);
    merged.set(left);
    merged.set(right, left.length);

    return merged;
}

function parseWavHeader(bytes) {
    if (bytes.length < 44) {
        return null;
    }

    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let offset = 12;

    while (offset + 8 <= bytes.length) {
        const id = String.fromCharCode(
            bytes[offset],
            bytes[offset + 1],
            bytes[offset + 2],
            bytes[offset + 3],
        );
        const size = view.getUint32(offset + 4, true);

        if (id === 'fmt ') {
            if (offset + 24 > bytes.length) {
                return null;
            }

            const channels = view.getUint16(offset + 10, true);
            const sampleRate = view.getUint32(offset + 12, true);
            const blockAlign = view.getUint16(offset + 20, true);

            offset += 8 + size;
            const dataAt = findDataChunk(bytes, view, offset);

            if (! dataAt) {
                return null;
            }

            return {
                channels,
                sampleRate,
                blockAlign,
                dataOffset: dataAt,
            };
        }

        offset += 8 + size;
    }

    return null;
}

function findDataChunk(bytes, view, offset) {
    while (offset + 8 <= bytes.length) {
        const id = String.fromCharCode(
            bytes[offset],
            bytes[offset + 1],
            bytes[offset + 2],
            bytes[offset + 3],
        );

        if (id === 'data') {
            return offset + 8;
        }

        const size = view.getUint32(offset + 4, true);
        offset += 8 + size;
    }

    return null;
}

function schedulePcm(context, sources, nextTime, pcmBytes, header) {
    const channels = header.channels;
    const samples = Math.floor(pcmBytes.byteLength / 2 / channels);

    if (samples <= 0) {
        return nextTime;
    }

    const audioBuffer = context.createBuffer(channels, samples, header.sampleRate);
    const view = new DataView(pcmBytes.buffer, pcmBytes.byteOffset, pcmBytes.byteLength);

    for (let channel = 0; channel < channels; channel++) {
        const output = audioBuffer.getChannelData(channel);

        for (let index = 0; index < samples; index++) {
            output[index] = view.getInt16((index * channels + channel) * 2, true) / 32768;
        }
    }

    const source = context.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(context.destination);
    const startAt = Math.max(nextTime, context.currentTime + 0.02);
    source.start(startAt);
    sources.push(source);

    return startAt + audioBuffer.duration;
}
