# Rafael

Personal secretary. Rafael speaks first. He greets you the first time you open the app. After that, he tells you the appointments you still have, then you can ask him about your tasks and tell him to add or remove a task or an appointment. The product is web-only.

This document is the whole-app picture. It is not a ticket list and not an implementation spec.

## Who it is for

Rafael is a **personal tool for one person** (you). There is no multi-user product, no public signup, no household sharing.

The UI and the speech language are **pt-BR**. Rafael speaks pt-BR, and you answer in pt-BR.

## What it is

A conversation with your secretary, not a recorder with a list under it:

- You open the site. Rafael starts.
- The first time, he only greets you. He does not recite an empty day.
- On later visits he tells you the appointments you still have, then waits.
- You ask about the tasks you have. He answers from those records, not from the open web.
- You tell him to add or remove a task or an appointment. He repeats the change and waits for a yes before it is saved.
- You click to talk, and click again to stop. The microphone is not always on.

Reminders that arrive when you are not in the app are part of the **whole app**, not of **v1**. v1 is the conversation when you enter. Notifications come after that conversation is trustworthy.

## Principles

1. **Rafael starts.** Opening the app is his turn. You do not have to press anything to hear him.
2. **First visit is a greeting.** No appointments, no tasks, no tour of features.
3. **Returning visits start with appointments.** Tasks are there when you ask. He does not dump both lists unasked.
4. **Voice is a draft.** A change is saved only after he repeats it and you agree. A wrong removal is worse than one extra turn.
5. **Questions stay on your records.** He can talk about your tasks and appointments. He is not a general chatbot.
6. **Audio is not a memory.** Your speech is transcribed on the server and the file is deleted. What remains is the transcript and the structured record. His spoken replies are not stored as audio.
7. **Rafael owns reminders** when they exist. Not Google Calendar. Calendar export can be a later door, not the core.
8. **Small design system, real product.** Tokens and a short component kit, not a DS project that delays the secretary.

## How a visit works

```text
[PWA or / ]
      │
      ▼
Rafael speaks first
      │
      ├── first visit → greeting only, then he waits
      │
      └── later visit → he tells the appointments you still have, then he waits
                │
                ▼
        You click to talk (Alpine)
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
        Delete audio file
                │
                ▼
        He answers a question about your tasks
        or he repeats an add/remove and waits for yes
                │
                ▼
        Saved or removed record
```

Later (not v1): a scheduler reads appointment times and task due dates and fires **Rafael’s own** web notifications while you are away.

## Domain

### Record

Every saved item is a **record** with a type:

| Type | Meaning | Time |
| --- | --- | --- |
| **Appointment** | Something that happens | Start time. This is what he recites when you return. |
| **Task** | Something you must do | Optional due date. He talks about these when you ask. |

Shared fields: title, created at, raw transcript of the turn that created or changed it.

v1 adds and removes. Complete, snooze, and recurrence are later verbs on the same record.

### User

Exactly one login. Laravel session auth. The app is not world-readable. “First time” means this user has never opened the app before.

## Surfaces

Web only. Laravel TALL. Installable as a **PWA** so a phone home-screen icon opens the same conversation.

| Surface | Role |
| --- | --- |
| Login | Door. One user. |
| Conversation (`/`) | Home. Rafael speaks on entry. You click the control to answer, ask, add, or remove. This is what the PWA opens. There is no inbox list on this screen. |

Typed notes without a mic are **out of v1**. Mic permission failure should still fail clearly, in words, including when he was the one who needed to speak and the speaker is blocked.

## v1 vs later

**v1 is true when:** you log in, Rafael greets you the first time, and on later visits he tells you your appointments, answers questions about your tasks, and adds or removes a task or an appointment after you agree — on desktop web and as an installed PWA.

| In v1 | After v1 |
| --- | --- |
| Login (one user) | Invite anyone else |
| Rafael speaks first (greeting, then appointment briefing) | Native Android/iOS |
| Questions about your tasks | Questions outside your records |
| Add and remove tasks and appointments, after a spoken yes | Autosave, recurrence, complete, snooze, search |
| Click to talk, click to stop | Always-on mic |
| MediaRecorder → Laravel → Whisper → delete audio | On-device speech-to-text |
| He speaks the reply (speech synthesis) | Other locales |
| pt-BR | Google Calendar, other people in the thread |
| PWA | Fire Rafael web notifications / push |

The whole app’s promise remains: **it reminds you**. v1 only refuses to remind you while you are away. While you are in the app, he already tells you what is on the books.

## Stack

Chosen stack (web only):

- **Laravel** — app, auth, storage, records, queued transcription, Rafael’s replies, later notifications
- **Livewire** — conversation state and records (server-driven UI)
- **Alpine.js** — click-to-talk, local capture state, playback of his turn
- **Tailwind CSS** — visual system (tokens in the theme)
- **GSAP** — motion while he speaks and while you record
- **HTML / CSS / JS** — no SPA framework
- **PWA** — manifest + service worker, HTTPS required
- **Whisper-compatible API** — server-side speech-to-text
- **Speech synthesis** — server or browser voice for Rafael’s turns (provider undecided)

Laravel is the backend because the product is a web app, not an Android client. There is no mobile native shell in this phase.

### Motion vs Livewire

GSAP owns the speaking and capture motion. Livewire owns data. Do not let Livewire morph destroy an in-flight recording; keep the recorder island stable (Alpine) and morph the conversation around it.

## Design system

A **small Rafael kit** on Tailwind, not stock “whatever utility won”:

**Foundations:** color, type, space, radius, elevation, motion durations (GSAP-friendly).

**Components (short list):**

- `TalkControl` — click to talk, click to stop; recording state
- `SecretaryTurn` — his greeting, the appointment briefing, an answer, or a change waiting for yes
- `AuthScreen` — login

Material 3 is not in this stack. The skin is Rafael’s.

UI copy: pt-BR.

## Auth, privacy, voice

- Simple Laravel login, **one user**.
- Authenticated routes only for the conversation, the records, and audio upload.
- Your audio is uploaded over HTTPS, transcribed, then **deleted**. The product memory is text + structure, not recordings.
- His replies are spoken in the session. They are not kept as audio files.
- No raw audio in backups by default.
- PWA, microphone, and speech output need a real HTTPS host (not bare HTTP).

## What this is not

- Not a silent voice recorder.
- Not an inbox you scan before he has spoken.
- Not a calendar replacement in v1 (and not a Google Calendar wrapper later).
- Not a multi-user SaaS.
- Not an always-on microphone.
- Not a general chatbot.
- Not a native Android app (that idea was dropped).
- Not a full design-system library for other products.

## Open implementation details (not product decisions)

These do not change the app’s meaning; they wait for build:

- Which Whisper-compatible provider, and how the Laravel job is queued
- Which voice speaks for Rafael, and whether that is server audio or the browser’s speech synthesis
- Hosting (must be HTTPS)
- Exact appointment and task fields in the schema
- Web Push vs local Notification API when away-from-app reminders ship
