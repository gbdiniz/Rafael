# Project description

The technical map of Rafael. What the app is, and how a person uses it, is in the [README](../README.md).

## Key concepts

- **One user.** One login. No public signup, no sharing. The app is not world-readable. “First time” means this user has never opened the app before.
- **Appointment.** Something that happens at a start time. On a returning visit, Rafael recites today’s appointments and any earlier appointment that was never removed. A day is the user’s local calendar day.
- **Task.** Something to do, with an optional due date. Rafael talks about tasks when asked, not in the opening briefing.
- **Record.** Every saved item. Shared fields: title, created at, and the raw transcript of the turn that created or changed it. v1 creates, changes, and removes. Complete, snooze, and recurrence are later verbs on the same record.
- **First visit.** A greeting only. He does not recite an empty day.
- **Spoken yes.** A create, change, or removal is saved only after Rafael repeats it and the user agrees.
- **Questions stay on the records.** He answers about the user’s tasks and appointments. He is not a general chatbot.
- **Audio is not a memory.** Speech is transcribed and the file is deleted. What remains is the transcript and the structured record. His replies are not stored as audio.

## Tech stack

Web only. No SPA framework. No native shell in this phase.

- **Laravel** — app, auth, storage, records, queued transcription, replies, and later notifications
- **Livewire** — conversation state and records (server-driven UI)
- **Alpine.js** — click to talk, click to stop, local capture state, playback of his turn
- **Tailwind CSS** — the small Rafael kit (color, type, space, radius, elevation, motion durations). Material 3 is not in the stack
- **GSAP** — motion while he speaks and while the user records. GSAP owns that motion; Livewire owns data. A Livewire morph must not destroy an in-flight recording, so the recorder island stays in Alpine
- **HTML / CSS / JS** — no SPA framework
- **PWA** — manifest and service worker. A phone home-screen icon opens the same conversation. HTTPS is required for the PWA, the microphone, and speech output
- **Whisper-compatible API** — server-side speech-to-text via the separate [Rafael-Whisper](https://github.com/gbdiniz/Rafael-Whisper) repo (self-hosted faster-whisper). Rafael queues transcription on Redis.
- **Speech synthesis** — Rafael’s voice is Piper, voice `pt_BR-faber-medium`, in the separate Rafael-Piper repo (the same split as Whisper). Laravel only stores `PIPER_URL` and proxies a stream. His replies are not stored as audio.

Components in the kit:

- `TalkControl` — click to talk, click to stop, recording state
- `SecretaryTurn` — greeting, appointment briefing, an answer, or a change waiting for yes
- `AuthScreen` — login

Surfaces:

| Surface | Role |
| --- | --- |
| Login | Door. One user. |
| Conversation (`/`) | Home. Rafael speaks on entry. The user clicks the control to answer, ask, create, change, or remove. No inbox list on this screen. |

Authenticated routes cover the conversation, the records, and audio upload. Typed notes without a microphone are out of v1. A denied microphone, or a blocked speaker when he needs to talk, fails in words.

Audio is uploaded over HTTPS, transcribed, then deleted. His replies are spoken in the session and are not kept as audio files. Raw audio stays out of backups by default.

## Core workflows

**Open the app.** Rafael speaks first. The first visit is a greeting, spoken as written. Every later visit starts with today’s appointments and any earlier appointment that was never removed, spoken as written. Then he waits. If the browser blocks that audio, a tap on the field plays it and does not start the microphone. The bars stay the microphone.

**After a voice turn.** PHP still writes the line. A second completion rewrites it for the ear. The screen and the voice use that rewrite, and every fact in the PHP line stays. If the completion fails, both use the PHP line. If Piper fails, the text stays and he says he could not speak.

**How the audio moves.** Laravel puts the line in the session key `rafael.speech`. The browser POSTs to the authenticated speech route and cannot send the text. That route proxies Rafael-Piper `POST /synthesize/stream`. The body is a chunked WAV: a header with an unknown length, then PCM frames as Piper yields them. Stock Piper `POST /synthesize` is not this path. It returns one finished WAV. Nothing is written to Storage. A talk click resumes audio so a later reply can play. If the greeting is blocked, the field waits for a tap (`Ouvir`). The bars stay the microphone.

**Click to talk.**

```text
Click the control (Alpine)
        │
        ▼
Browser MediaRecorder (audio blob)
        │
        ▼
Laravel (auth required)
        │
        ▼
Whisper-compatible transcription API
        │
        ▼
Delete the audio file
        │
        ▼
He answers a question about tasks
or he repeats a create, change, or removal and waits for yes
        │
        ▼
Saved, changed, or removed record
```

**Ask about tasks.** The user asks. Rafael answers from the stored tasks, not from the open web.

**Create, change, or remove.** The user tells him what to do with a task or an appointment. A change can rewrite the title, the time, and the type. When the type flips, the time changes role: a start time becomes the due date, or a due date becomes the start time. If a task has no due date and would become an appointment, he asks for a start time before the yes. If more than one record matches, he names the matches and waits. He does not pick one. He repeats the result and waits for a yes. A no leaves the record as it was. Only the yes saves, changes, or removes it.

**Away from the app (not v1).** A scheduler reads appointment times and task due dates and fires Rafael’s own web notifications. Web Push versus the local Notification API is undecided. Hosting must be HTTPS. The exact appointment and task columns wait for the schema.
