# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel (app, session auth, storage, records, queued transcription, Rafael’s replies, and later notifications), Livewire (conversation state and records), Alpine.js (click-to-talk, local capture state, and playback of his turn), Tailwind CSS, GSAP for speaking and capture motion, HTML/CSS/JS with no SPA framework, an installable PWA (manifest and service worker), a Whisper-compatible speech-to-text API, and speech synthesis for Rafael’s voice. Confirmed for this greenfield app. The synthesis provider is undecided.

## Users

Gabriel is the only user. Rafael is his personal secretary. Gabriel opens the app and Rafael speaks first. There is no second user, no household sharing, and no public signup.

## Product Purpose

Rafael is a personal secretary who starts the conversation. The first time Gabriel enters, Rafael only greets him. On later visits Rafael tells him the appointments he still has, then Gabriel can ask about his tasks and tell Rafael to add or remove a task or an appointment. v1 succeeds when that visit works, on desktop web and as an installed PWA. v1 speaks while Gabriel is in the app. Reminders that arrive while he is away wait until that conversation is trustworthy.

## Positioning

Rafael is the one who starts. The app is not only a voice recorder and not an inbox Gabriel scans first. Questions stay on Gabriel’s own tasks and appointments. A change is a draft until Rafael repeats it and Gabriel agrees. The microphone is click to start and click to stop, not always on. A calendar export can be a later door.

## Operating Context

Gabriel opens the conversation in a browser or from a phone home-screen icon (PWA). Rafael speaks immediately: a greeting on the first visit, the remaining appointments on every visit after that. Gabriel clicks to talk and clicks again to stop. The browser records that turn. Laravel requires his session. A Whisper-compatible API transcribes. The audio file is deleted. Rafael answers from the stored tasks, or repeats an add or a removal and waits for a yes. Authenticated routes cover the conversation, the records, and audio upload. PWA install, the microphone, and speech output require HTTPS.

## Capabilities and Constraints

v1 includes:

- One Laravel session login.
- Rafael speaks first when the page opens.
- First visit: a greeting only. No appointment briefing and no task list.
- Later visits: he tells the appointments Gabriel still has, then waits.
- Questions about Gabriel’s tasks, answered from those records.
- Add and remove a task or an appointment, saved only after Rafael repeats the change and Gabriel agrees.
- PWA plus in-page click-to-talk capture (MediaRecorder).
- Server transcription, then deletion of the audio. The product memory is the transcript and the structured record. Raw audio stays out of backups by default. Rafael’s spoken replies are not stored as audio.
- Appointment start time, so he can recite it. Task due date is optional.
- Shared record fields: title, created at, raw transcript of the turn that created or changed it.
- Interface copy and speech language: pt-BR, in both directions.
- A clear failure when microphone permission is denied, or when he cannot speak. Typed notes without a microphone are outside v1.
- GSAP owns speaking and capture motion. Livewire owns data. The recorder island stays stable so a Livewire update does not destroy an in-flight capture.

After v1:

- Rafael’s own web notifications while Gabriel is away (Web Push versus the local Notification API is undecided).
- Complete, snooze, recurrence, and search.
- Other locales, additional users, native Android or iOS, on-device speech-to-text, and Google Calendar export.

Undecided implementation, which does not change the product’s meaning:

- Which Whisper-compatible provider, and how the Laravel job is queued.
- Which voice speaks for Rafael, and whether that audio is synthesized on the server or in the browser.
- Hosting, beyond the HTTPS requirement.
- The exact appointment and task columns in the schema.

Terminology: a record is an appointment (something that happens at a start time) or a task (something to do, with an optional due date). A first visit is Gabriel’s first time opening the app. A question is about his tasks or appointments, not an open-ended chat.

## Brand Commitments

The product name is Rafael. Interface copy is pt-BR. The interface is a small Rafael kit (tokens and a short component list), owned by this product, and Material 3 is outside the stack.

The home is the conversation. Rafael speaks when the page opens. The bars icon is how Gabriel talks back: no label, click to start, click again to stop. The inbox list is not on this screen. The bookshelf, malote, space, and neumorphism directions are not the home. No logo is confirmed.

## Evidence on Hand

The product picture lives in `docs/rafael.md`. `README.md` points at that picture.

The repo has no logo, photography, testimonials, case studies, pricing, or customer proof. Future work must not invent them, and must not present away-from-app reminders, extra users, or calendar sync as behavior v1 already has.

## Product Principles

1. Rafael starts. Opening the app is his turn.
2. The first visit is a greeting. He does not recite an empty day.
3. Returning visits start with the appointments he still has. Tasks are answered when Gabriel asks.
4. Voice is a draft. He repeats an add or a removal and waits for a yes.
5. Questions stay on Gabriel’s records.
6. Audio is not a memory. Transcribe, delete the file, keep the transcript and the structured record.
7. Rafael owns reminders when they exist. Calendar export can be a later door.
