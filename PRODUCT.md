# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel (app, session auth, storage, queued transcription, and later notifications), Livewire (inbox, confirm card, categories), Alpine.js (hold-to-talk and local capture state), Tailwind CSS, GSAP for capture and confirm motion, HTML/CSS/JS with no SPA framework, an installable PWA (manifest and service worker), and a Whisper-compatible speech-to-text API. Confirmed for this greenfield app.

## Users

Gabriel is the only user. Rafael is his personal tool. He reaches for it when something in the day should become an appointment or a task: he speaks, confirms the draft, and finds the record in the inbox. There is no second user, no household sharing, and no public signup.

## Product Purpose

Rafael is a personal secretary. Speech becomes a confirmed appointment or task in a category Gabriel owns. The app’s promise is that it reminds him. v1 succeeds when he can log in, hold to talk, confirm an appointment or task in a category, and see it in the list, on desktop web and as an installed PWA. v1 captures and lists. Reminders wait until that inbox is trustworthy.

## Positioning

Voice is a draft. The confirm card is the commit, so a reminder never starts from an unreviewed transcript. One utterance becomes one record: a clock time defaults to an appointment, anything else defaults to a task, and Gabriel can flip the type before saving. Rafael keeps the records. A calendar export can be a later door. The product is a single-user web app with a press-and-hold capture, not an always-on microphone.

## Operating Context

Gabriel opens the capture page in a browser or from a phone home-screen icon (PWA). He holds the capture control. The browser records audio. Laravel requires his session. A Whisper-compatible API transcribes. The audio file is deleted. A confirm card asks him to check title, type, category, and time before the record joins the inbox. Categories are his. A few suggestions on first use are allowed, and none are mandatory. Authenticated routes cover capture, the list, and audio upload. PWA install and the microphone require HTTPS.

## Capabilities and Constraints

v1 includes:

- One Laravel session login.
- PWA plus in-page hold-to-talk capture (MediaRecorder).
- Server transcription, then deletion of the audio. The product memory is the transcript and the structured record. Raw audio stays out of backups by default.
- Confirm card: edit title, type (appointment or task), category (create inline), and time. Save or discard.
- Inbox list with a category filter.
- Appointment start time, collected in v1 and required once reminders exist. Task due date is optional.
- Shared record fields: title (from the transcript, editable on confirm), category, created at, raw transcript.
- Lightweight categories: create and rename from the confirm card and from a simple list.
- Interface copy and speech language: pt-BR.
- A clear failure when microphone permission is denied. Typed notes without a microphone are outside v1.
- GSAP owns capture and confirm motion. Livewire owns data. The recorder island stays stable so a Livewire update does not destroy an in-flight capture.

After v1:

- Rafael’s own web notifications (Web Push versus the local Notification API is undecided).
- Complete, snooze, recurrence, and search.
- Other locales, additional users, native Android or iOS, on-device speech-to-text, autosave, Google Calendar export, and chat.

Undecided implementation, which does not change the product’s meaning:

- Which Whisper-compatible provider, and how the Laravel job is queued.
- Hosting, beyond the HTTPS requirement.
- The exact appointment and task columns in the schema.

Terminology: a record is an appointment (something that happens at a start time) or a task (something to do, with an optional due date). A category is a label Gabriel creates, used to file and filter the inbox.

## Brand Commitments

The product name is Rafael. Interface copy is pt-BR. The interface is a small Rafael kit (tokens and a short component list), owned by this product, and Material 3 is outside the stack.

The home shows only the record control: a bars icon with no label. The inbox list is not on this screen. The bookshelf, malote, space, and neumorphism directions are not the home. No logo is confirmed.

## Evidence on Hand

The product picture lives in `docs/rafael.md` (grilling session, confirmed during init). `README.md` points at that picture.

The repo has no logo, photography, testimonials, case studies, pricing, or customer proof. Future work must not invent them, and must not present reminders, extra users, or calendar sync as behavior v1 already has.

## Product Principles

1. Voice is a draft. The confirm card is the commit. A wrong reminder is worse than one extra tap.
2. One utterance, one record. A clock time defaults to an appointment; otherwise the draft is a task. The type can be flipped before saving.
3. Gabriel owns the categories. First-use suggestions are optional.
4. Audio is not a memory. Transcribe, delete the file, keep the transcript and the structured record.
5. Rafael owns reminders when they exist. Calendar export can be a later door.
