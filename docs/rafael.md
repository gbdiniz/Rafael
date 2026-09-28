# Rafael

Personal secretary. You speak, Rafael turns that into an **appointment** or a **task** in a **category** you own. Later it reminds you. Right now the product is web-only.

This document is the whole-app picture from the grilling session. It is not a ticket list and not an implementation spec.

## Who it is for

Rafael is a **personal tool for one person** (you). There is no multi-user product, no public signup, no household sharing.

The UI and the speech language are **pt-BR**.

## What it is

A voice-first inbox of your life:

- You press and hold to talk.
- Rafael transcribes.
- You confirm what it became (appointment or task, category, time).
- It lands in a list you can open from the browser or from a phone home-screen icon (PWA).

Reminders are part of the **whole app**, not of **v1**. v1 captures and lists. Notifications come after the inbox is trustworthy.

## Principles

1. **Voice is a draft.** The confirm card is the commit. Wrong reminders are worse than one extra tap.
2. **One utterance, one record.** If it has a clock time, default to appointment; otherwise task. You can flip the type before saving.
3. **You own categories.** There is no fixed taxonomy. A few suggestions on first use are fine; none are mandatory.
4. **Audio is not a memory.** Record → transcribe on the server → delete the file. Keep the transcript and the structured record.
5. **Rafael owns reminders** when they exist. Not Google Calendar. Calendar export can be a later door, not the core.
6. **Small design system, real product.** Tokens and a short component kit, not a DS project that delays the secretary.

## How a capture works

```text
[PWA or / ]
      │
      ▼
Hold capture control (Alpine + GSAP)
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
Confirm card (title, type, category, time)
      │
      ▼
Saved record in the inbox list
```

Later (not v1): a scheduler reads appointment times / task due dates and fires **Rafael’s own** web notifications.

## Domain

### Record

Every saved item is a **record** with a type:

| Type | Meaning | Time |
| --- | --- | --- |
| **Appointment** | Something that happens | Start (required once reminders exist; collected already in v1) |
| **Task** | Something you must do | Optional due date |

Shared fields: title (from transcript, editable on confirm), category, created at, raw transcript.

v1 does not need complete / snooze / recurrence. Those are later verbs on the same record.

### Category

A label you create (name; color is part of the small DS). Used to file and to filter the inbox.

### User

Exactly one login. Laravel session auth. The app is not world-readable.

## Surfaces

Web only. Laravel TALL. Installable as a **PWA** so a phone home-screen icon opens the same capture page.

| Surface | Role |
| --- | --- |
| Login | Door. One user. |
| Capture + inbox (`/`) | Home. Big hold-to-talk control, list underneath, category filter. This is what the PWA opens. |
| Confirm card | Overlay/sheet after transcription. Edit title, type, category (create inline), time. Save or discard. |
| Category manager | Lightweight: create/rename from the confirm card and from a simple list. Not a CMS. |

Typed notes without a mic are **out of v1** (not chosen). Mic permission failure should still fail clearly.

## v1 vs later

**v1 is true when:** you log in, hold to talk, confirm an appointment or task in a category, and see it in the list — on desktop web and as an installed PWA.

| In v1 | After v1 |
| --- | --- |
| Login (one user) | Invite anyone else |
| PWA + in-page capture | Native Android/iOS |
| MediaRecorder → Laravel → Whisper → delete audio | On-device STT |
| Confirm card | Autosave |
| Inbox list + category filter | Recurrence, complete, snooze, search |
| Store time on the record | Fire Rafael web notifications / push |
| Small DS + GSAP on capture/confirm | Motion on every list interaction |
| pt-BR | Other locales |
| — | Google Calendar, chat, always-on mic |

The whole app’s promise remains: **it reminds you**. v1 only refuses to remind until capture is solid.

## Stack

Chosen stack (web only):

- **Laravel** — app, auth, storage, queued transcription, later notifications
- **Livewire** — inbox, confirm card, categories (server-driven UI)
- **Alpine.js** — hold-to-talk gesture, local capture state
- **Tailwind CSS** — visual system (tokens in the theme)
- **GSAP** — capture and confirm motion
- **HTML / CSS / JS** — no SPA framework
- **PWA** — manifest + service worker, HTTPS required
- **Whisper-compatible API** — server-side speech-to-text (OpenAI Whisper or equivalent)

Laravel is the backend because the product is now a web app, not an Android client. There is no mobile native shell in this phase.

### Motion vs Livewire

GSAP owns capture and confirm choreography. Livewire owns data. Do not let Livewire morph destroy an in-flight capture animation; keep the recorder island stable (Alpine) and morph the list/card around it.

## Design system

A **small Rafael kit** on Tailwind, not stock “whatever utility won”:

**Foundations:** color, type, space, radius, elevation, motion durations (GSAP-friendly).

**Components (short list):**

- `CaptureControl` — hold to talk, recording and processing states
- `ConfirmCard` — the commit
- `RecordRow` — inbox line (type, title, category, time)
- `CategoryChip` — filter and filing
- `EmptyInbox` — first-run
- `AuthScreen` — login

Material 3 is not in this stack. The skin is Rafael’s.

UI copy: pt-BR.

## Auth, privacy, voice

- Simple Laravel login, **one user**.
- Authenticated routes only for capture, list, and audio upload.
- Audio is uploaded over HTTPS, transcribed, then **deleted**. The product memory is text + structure, not recordings.
- No raw audio in backups by default.
- PWA and microphone require a real HTTPS host (not bare HTTP).

## What this is not

- Not a calendar replacement in v1 (and not a Google Calendar wrapper later).
- Not a multi-user SaaS.
- Not an always-on microphone.
- Not a native Android app (that idea was dropped).
- Not a full design-system library for other products.

## Open implementation details (not product decisions)

These do not change the app’s meaning; they wait for build:

- Which Whisper-compatible provider and how the Laravel job is queued
- Hosting (must be HTTPS)
- Exact appointment/task fields in the schema
- Web Push vs local Notification API when reminders ship
