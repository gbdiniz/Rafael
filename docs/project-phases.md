# Project phases

Build order for Rafael v1. Sources: [user stories](user-stories.md), [project description](project-description.md), [database schema](database-schema.md).

An agent implements one numbered phase at a time (Phase 2.1, Phase 5.3). Do not start a phase whose dependencies are still open. Do not add tables the schema rejected: no `appointments` / `tasks` split, no `record_proposals`, no notification tables, no roles.

Checked items are already in the repo. Everything else is still to build.

## How to build

Read the skill that matches the change before writing code. There is no existing Livewire component, so Livewire 4 defaults apply.

| Change | Skill |
|---|---|
| PHP, Eloquent, migrations, jobs, HTTP, config | `.agents/skills/laravel-best-practices` |
| Livewire pages, `wire:` directives, Alpine inside a Livewire page | `.agents/skills/livewire-development` |
| Tests | `.agents/skills/testing-best-practices` |
| Tailwind on a Blade or Livewire view | `.agents/skills/tailwindcss-development` |

- **Actions.** Create, change, remove, briefing, and answer are action classes with `handle()`. The Livewire page calls the action. No repository layer.
- **Livewire 4.** The conversation is one full-page single-file component: `php artisan make:livewire pages::conversation`, which creates `resources/views/pages/⚡conversation.blade.php`. Register it with `Route::livewire()`. Do not use `--class` or `--mfc`. There is no `config/livewire.php` yet, so keep the default ⚡ filename prefix. `AuthScreen`, `SecretaryTurn`, and `TalkControl` are Blade or Alpine pieces inside that page, not extra Livewire components. Alpine ships with Livewire 4: do not add an Alpine package. The recorder island is `wire:ignore` so a morph cannot destroy a recording. Lists use `wire:key`. Show `wire:loading` while a turn is transcribing. Validate inside the page action the same way a form request would.
- **Boundaries.** Whisper, speech synthesis, and turn interpretation are contracts, bound in `AppServiceProvider`. Constructor-inject them. Everything else stays concrete.
- **HTTP.** A guest on a browser route is redirected to login. The audio upload uses a form request with array rules. One user, so no policy matrix.
- **Eloquent.** Local scopes for on-the-books, today, and missed. Every list has an explicit `orderBy`, with `id` as the tie-breaker. Cast `kind` and `status` with backed enums. `$fillable` only for attributes the app mass-assigns. Do not set `$guarded = []`.
- **Migrations.** `php artisan make:migration`. `foreignId()->constrained()` plus the schema delete behavior (`restrictOnDelete` on `user_id`, `nullOnDelete` on `last_voice_turn_id`). Do not add a second index on a column the foreign key already indexes. Do not edit the shipped Laravel migrations. Runtime database is **MySQL**; Pest keeps SQLite `:memory:` in `phpunit.xml`.
- **Jobs.** Transcription is `ShouldQueue`. `$timeout` stays under Redis `retry_after`. Back off transient transcriber failures only. `failed()` marks the turn `failed` and deletes the audio. A missing turn returns without recreating the row.
- **HTTP client.** The transcriber sets `connectTimeout()` and `timeout()`, and retries only transient failures.
- **Schedule.** The prune command is `daily()->withoutOverlapping()`.
- **Config.** Provider URL, timeouts, and the timezone default live in `config/`, not in `env()` outside config files.
- **Tailwind v4.** `@import "tailwindcss"` and `@theme` in `resources/css/app.css`. No `tailwind.config.js`. No deprecated v3 utilities. Space siblings with `gap`. No Material 3.
- **Strings.** Portuguese text uses `Str::` / `mb_*`.

## How to test

Pest. Create files with `php artisan make:test --pest {name}` and `--unit` for a unit test. Do not pass `--phpunit`. `tests/Pest.php` already binds `Tests\TestCase` and `LazilyRefreshDatabase` for `tests/Feature`. Do not add `pestphp/pest-plugin-browser`.

- Feature test for every request and every Livewire action. A unit test only for logic that does not use the framework. Drive the page with `Livewire::test('pages::conversation')`.
- Use `it()` for behavior, written as a verb phrase (`it('redirects a guest to login when opening the conversation')`). Use `test()` only for a declarative fact. One style per file. No `Given` / `When` / `Then`.
- Use `describe()` only when one file covers separate lifecycle actions. Do not wrap a single flow in `describe()`.
- `expect()` for a value. `assertModelExists` / `assertSoftDeleted` for a model. Named HTTP assertions (`assertRedirect`, `assertNotFound`, `assertOk`), not `assertStatus`. Do not assert ok before `assertSee`. On a write, assert the response, the database, and any job that was or was not dispatched.
- Create factory rows inside the test. Do not create them in `beforeEach()`. Prefer a named factory state. Create those rows before `Event::fake()` or `Queue::fake()` without a class list.
- Datasets (`->with()`) only when setup and assertions stay identical.
- Freeze time with `travelTo()`. Do not call `Carbon::setTestNow()`. Fakes go inside the test, not in `beforeEach()`. `Storage::fake('local')`. `Http::preventStrayRequests()` and fake the exact transcriber URL. `Queue::fake([TranscribeVoiceTurn::class])` only when the test asserts dispatch; job tests run the job. Mock a contract with `use function Pest\Laravel\mock`.
- Do not test an enum cast by echoing it, and do not test a foreign-key engine rule the app never exercises.
- Do not delete `tests/Feature/ExampleTest.php`. Rewrite it in place when `/` stops being the welcome page.
- `phpunit.xml` keeps cache `array`, queue `sync`, session `array`, and database `sqlite` `:memory:`. Do not assert that runtime drivers are Redis or MySQL.

---

## Phase 1 — Foundation

Already in the skeleton. Do not redo these migrations.

### Phase 1.1 — Laravel app boots

- [x] Laravel 13 app, Vite, Tailwind 4, MySQL runtime. `GET /` still returns the welcome view.

**Feature tests (already present):**

- `tests/Feature/ExampleTest.php` — `it('returns a successful response')` asserts the home page is ok.

Rewrite this test in Phase 4.1. Do not delete the file.

### Phase 1.2 — Framework tables

- [x] `users` (name, email, password, `email_verified_at`, remember token, timestamps), `password_reset_tokens`, `sessions`.
- [x] `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks` kept. Cache and the queue do not use the `jobs` / `cache` tables at runtime.

**Feature tests:** none. These migrations already ran. Do not drop them.

### Phase 1.3 — Redis for cache and queue

- [x] `.env` and `.env.example`: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=database`, `REDIS_CLIENT=phpredis`.
- [x] `composer.json` requires `ext-redis`. No Predis, no Horizon, no Compose file.

**Feature tests:** none. `phpunit.xml` overrides cache, queue, and session.

### Phase 1.4 — Schema specification

- [x] [database-schema.md](database-schema.md) is the source for columns, indexes, and delete behavior.

**Feature tests:** none.

### Phase 1.5 — Livewire 4 and Pest

- [x] `livewire/livewire` ^4.4, `pestphp/pest` ^5, and `pestphp/pest-plugin-laravel` are required.
- [x] `tests/Pest.php` binds the Laravel test case and `LazilyRefreshDatabase` for feature tests. `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are Pest `it()` files.
- [ ] No Livewire page exists yet. That work is Phase 4.

**Feature tests:** none beyond Phase 1.1.

---

## Phase 2 — Books and voice turns

Depends on Phase 1. Schema only. No conversation UI.

Migration order: alter `users`, then `voice_turns`, then `records`.

### Phase 2.1 — User calendar columns

- [x] Add `users.timezone` (string 64, default `America/Sao_Paulo`) and `users.conversation_opened_at` (nullable timestamp).
- [x] Leave `email_verified_at` unused. Do not enable `MustVerifyEmail`.
- [x] Do not update timezone from the browser. Mirror the default in config.
- [x] `UserFactory` defaults `timezone` to `America/Sao_Paulo`.

**Stories:** US-02, US-03. **Schema:** `users`.

**Feature tests:** `tests/Feature/Models/UserTest.php`

- `it('persists America/Sao_Paulo as the timezone on a new user')`
- `it('leaves conversation_opened_at null on a new user')`
- `it('refuses to delete a user who still owns a record')`
- `it('refuses to delete a user who still owns a voice turn')`
- `it('refuses to delete a user whose only record is soft-deleted')`
- `it('updates record and voice turn user ids when the user id changes')`

A rejected delete throws `QueryException` and leaves the user and the child rows in place. An id change on the user is followed by `records.user_id` and `voice_turns.user_id`. Pest runs these against SQLite `:memory:`; migrations must declare the same rules MySQL enforces at runtime.

### Phase 2.2 — Enums and models

- [x] Backed string enums `RecordKind` (`appointment`, `task`) and `VoiceTurnStatus` (`uploaded`, `transcribing`, `completed`, `failed`).
- [x] One `Record` model and one `VoiceTurn` model. No `Appointment` or `Task` model.
- [x] Relationships with return types: `User::records()`, `User::voiceTurns()`, `Record::user()`, `Record::lastVoiceTurn()`, `VoiceTurn::user()`, `VoiceTurn::records()`.
- [x] Local scopes on `Record`: `appointments`, `tasks`, and briefing scopes that take the user timezone and a frozen now. Soft deletes already hide removed rows.
- [x] Factory states: `Record::factory()->appointment()`, `->task()`, `->withoutDueDate()`, `->deleted()`. `VoiceTurn::factory()->uploaded()`, `->completed()`, `->failed()`.

**Schema:** §7, §10.

**Feature tests:** covered by Phase 2.3, Phase 2.4, and Phase 4.3. Do not add a test that only casts an enum back to itself.

### Phase 2.3 — `voice_turns`

- [x] Migration, model, factory. Columns and the non-foreign indexes from the schema (`uuid` unique, status default `uploaded`, locale default `pt-BR`, `created_at` indexed for the prune).
- [x] `user_id` uses `foreignId()->constrained()->restrictOnDelete()->cascadeOnUpdate()`. No extra index on `user_id`.

**Schema:** `voice_turns`.

**Feature tests:** `tests/Feature/Models/VoiceTurnTest.php`

- `it('stores an uploaded turn with a unique uuid for its user')`
- `it('rejects a second turn that reuses the same uuid')`
- `it('clears the record link and keeps the transcript when the voice turn is deleted')`
- `it('rejects a voice turn whose user does not exist')`

A missing user throws `QueryException` and inserts no turn. Deleting the turn nulls `records.last_voice_turn_id` and leaves `last_transcript` and the record in place.

### Phase 2.4 — `records`

- [x] Migration, `Record` with `SoftDeletes`, factory. `kind`, `title`, nullable `scheduled_at`, required `last_transcript`, nullable `last_voice_turn_id`.
- [x] Check: an appointment requires `scheduled_at`; a task may omit it.
- [x] `user_id` `restrictOnDelete()->cascadeOnUpdate()`. `last_voice_turn_id` `nullOnDelete()->cascadeOnUpdate()`.
- [x] Composite index `(user_id, kind, deleted_at, scheduled_at)`. Add `(user_id, deleted_at)` only if a real query needs it after that composite. No unique index on `title`.
- [x] The action sets `user_id` from the authenticated user. Do not mass-assign it from the request.

**Schema:** `records`.

**Feature tests:** `tests/Feature/Models/RecordTest.php`

- `it('refuses to persist an appointment without a scheduled time')`
- `it('persists a task that has no due time')`
- `it('allows two records to share a title')`
- `it('keeps the record transcript and clears the turn link when the voice turn is deleted')`
- `it('hides a soft-deleted record from the default query')`
- `it('keeps the user and the voice turn when the record is force-deleted')`
- `it('rejects a record user id that does not exist')`
- `it('rejects a last voice turn id that does not exist')`
- `it('follows the voice turn id when that turn id changes')`

The delete-turn case is the behavior prune and the job rely on. `assertModelExists` on the record, `expect($record->last_transcript)->toBe(...)`, and `expect($record->last_voice_turn_id)->toBeNull()`. A rejected foreign key throws `QueryException` and leaves the existing rows in place. Force-deleting a record does not delete its user or its voice turn. An id change on the voice turn is followed by `records.last_voice_turn_id`.

### Phase 2.5 — Local day

- [x] One class the briefing scope uses: local today and “before today” from `users.timezone`, compared with UTC `scheduled_at`.
- [x] A date with no clock time becomes midnight in that timezone, then UTC. No date column.

**Stories:** US-03. **Schema:** `scheduled_at` decision.

**Feature tests:** `tests/Feature/Records/LocalDayTest.php`. Each test calls `travelTo()`.

- `it('stores a date-only due as midnight in America/Sao_Paulo')`
- `it('counts an appointment earlier today as today')`
- `it('counts an appointment before local midnight as missed')`

---

## Phase 3 — One login

Depends on Phase 2.1. **US-01.**

### Phase 3.1 — Session login, no signup

- [ ] Login route and Blade `AuthScreen`. No registration route and no signup link.
- [ ] The web cannot create users.
- [ ] Replace the seeder’s `Test User` / `test@example.com` with one local account named Gabriel. The password comes from config fed by the environment, not from a committed secret.
- [ ] Tailwind v4 on the login view: kit tokens in `@theme`, `gap` for stacking.

**Feature tests:** `tests/Feature/Auth/LoginTest.php`

- `it('renders the login screen to a guest')`
- `it('redirects to the conversation after valid credentials')`
- `it('stays on the login screen when the credentials are wrong')`
- `it('returns not found for the registration url')`
- `it('does not offer signup on the login screen')`

Assert `assertNotFound()` for the missing registration URL. Assert the Portuguese validation message when the credentials are wrong.

### Phase 3.2 — Authenticated doors

- [ ] `/`, record reads, and audio upload use the `auth` middleware.
- [ ] Guests are redirected to the named login route.

**Feature tests:** `tests/Feature/Auth/AuthenticatedRoutesTest.php`

- `it('redirects a guest to login when opening the conversation')`
- `it('redirects a guest to login when uploading audio')`
- `it('opens the conversation for a signed-in user')`

There is no second user, so there is no cross-tenant test and no policy matrix.

---

## Phase 4 — Rafael speaks first

Depends on Phase 2 and Phase 3. **US-02, US-03.** No inbox list.

### Phase 4.1 — Conversation is home

- [ ] `php artisan make:livewire pages::conversation` and `Route::livewire()` for `/`. Remove the welcome view from that route.
- [ ] Rewrite `tests/Feature/ExampleTest.php` in place so it no longer expects the welcome page to return ok.
- [ ] `SecretaryTurn` is a Blade component rendered by the page. `TalkControl` arrives in Phase 5.
- [ ] Copy is pt-BR. Set this surface’s locale to `pt_BR`.
- [ ] Order any record query with an explicit column plus `id`.

**Feature tests:** `tests/Feature/Conversation/HomeTest.php` via `Livewire::test('pages::conversation')`

- `it('renders the conversation instead of the welcome page for a signed-in user')`
- `it('does not render a record list on the conversation')`
- `it('renders the conversation copy in Portuguese')`

### Phase 4.2 — First visit is a greeting

- [ ] `OpenConversation` action: when `conversation_opened_at` is null, the turn is a greeting only (no appointments, no tasks, no product tour), then it sets `conversation_opened_at` once.
- [ ] A later visit does not clear that timestamp and does not greet as a first visit.

**Feature tests:** `tests/Feature/Conversation/FirstVisitTest.php`

- `it('renders a greeting and no records on the first open')`
- `it('does not render a product tour on the first open')`
- `it('sets conversation_opened_at on the first open')`
- `it('does not render the first-visit greeting on the second open')`

`expect($user->conversation_opened_at)->not->toBeNull()` and `assertModelExists` on the user. Do not assert ok before `assertSee`.

### Phase 4.3 — Today and what is still hanging

- [ ] `Briefing` action uses the Phase 2.5 scopes. After the first visit it includes every on-the-books appointment whose start falls on local today, including times already passed, and every on-the-books appointment whose start is before local today.
- [ ] It omits future days, tasks, and soft-deleted appointments.
- [ ] He waits after the briefing. An empty day does not recite tasks.

**Feature tests:** `tests/Feature/Conversation/BriefingTest.php`. Each test calls `travelTo()` and uses factory states.

- `it('includes every appointment that starts today on a return visit')`
- `it('includes an appointment that started earlier today')`
- `it('includes missed appointments on a return visit')`
- `it('omits future appointments from the return visit')`
- `it('omits tasks from the return visit')`
- `it('omits a soft-deleted appointment from the return visit')`
- `it('uses America/Sao_Paulo when that is the user timezone')`

---

## Phase 5 — Click to talk

Depends on Phase 3.2 and Phase 2.3. **US-04.** Alpine, already bundled with Livewire, owns capture. GSAP owns the listening motion. Do not install Alpine again.

### Phase 5.1 — Talk control

- [ ] Alpine `TalkControl` inside the page: first click starts, second click stops. The bars show listening.
- [ ] `mouseleave` and blur do not stop a recording.
- [ ] The recorder island is `wire:ignore`.
- [ ] Tailwind v4 utilities only. No Material 3.

Pest cannot drive `MediaRecorder`. These tests assert the server-rendered contract.

**Feature tests:** `tests/Feature/Voice/TalkControlTest.php`

- `it('exposes start and stop clicks on the talk control')`
- `it('does not stop the talk control on mouse leave')`
- `it('marks the recorder island with wire:ignore')`
- `it('marks the listening state for the bars')`

### Phase 5.2 — Authenticated upload

- [ ] `StoreVoiceTurnRequest` validates the audio (required file, allowed mime types, max size) with array rules.
- [ ] `StoreVoiceTurn` creates a `voice_turns` row in `uploaded` and stores the file on the private `local` disk. The public disk never receives the bytes.
- [ ] Dispatch `TranscribeVoiceTurn` for that turn only. The upload does not create a `records` row.
- [ ] The page shows `wire:loading` while that turn is in flight.

**Feature tests:** `tests/Feature/Voice/AudioUploadTest.php`

- `it('creates an uploaded turn and stores the file for a signed-in user')`
- `it('rejects an upload that has no file')`
- `it('does not write the uploaded audio to the public disk')`
- `it('dispatches transcription for that turn')`
- `it('does not create a record when audio is uploaded')`

Use `Storage::fake('local')` and `Queue::fake([TranscribeVoiceTurn::class])`. `assertModelExists` on the turn. The guest redirect stays in Phase 3.2.

### Phase 5.3 — Transcribe, then delete the file

- [ ] `Transcriber` contract, bound to an HTTP implementation with connect and response timeouts. Tests bind a fake.
- [ ] `TranscribeVoiceTurn`: `$timeout` under `retry_after`, `$tries` and `$backoff` for transient failures only.
- [ ] Success sets `completed`, stores `transcript`, deletes the file, nulls `audio_path` and `disk`, sets `audio_deleted_at`.

**Feature tests:** `tests/Feature/Jobs/TranscribeVoiceTurnTest.php`. Run the job. Do not `Queue::fake` it.

- `it('stores the transcript and deletes the file when transcription completes')`
- `it('does not call an unfaked host during transcription')`

`Http::preventStrayRequests()` in the HTTP-client test. The fake-contract test does not hit HTTP.

### Phase 5.4 — Transcription failure

- [ ] A permanent failure and `failed()` after retries set `status` to `failed`, a short `error_message`, and still delete the file.
- [ ] The conversation reads that failure from the turn. It does not read `failed_jobs`.

**Feature tests:** `tests/Feature/Jobs/TranscribeVoiceTurnTest.php`

- `it('deletes the file and stores a short error when transcription fails')`
- `it('marks the turn failed after the job is exhausted')`

**Feature tests:** `tests/Feature/Conversation/TranscriptionFailureTest.php`

- `it('shows the turn error message on the conversation')`

### Phase 5.5 — Missing turn

- [ ] If the row is gone when the job runs, `handle()` returns. It does not insert a turn and does not throw.

**Feature tests:**

- `it('returns when the turn is already gone')` in `tests/Feature/Jobs/TranscribeVoiceTurnTest.php`

### Phase 5.6 — Replies are not audio files

- [ ] Nothing in the success path writes a reply file or a reply row.

**Feature tests:** `tests/Feature/Voice/ReplyAudioTest.php`

- `it('does not store a reply file when transcription completes')`

---

## Phase 6 — Questions stay on the books

Depends on Phase 4.3 and Phase 5.3. **US-05.**

### Phase 6.1 — Interpret a transcript without the open web

- [ ] `TurnInterpreter` contract: question, mutation draft, yes, no, or not-about-the-books. Bound in the provider. Tests bind a fake.
- [ ] `Http::preventStrayRequests()` so an off-books question cannot call a search or chat host.
- [ ] Off-books: pt-BR refusal, no `records` write.

**Feature tests:** `tests/Feature/Conversation/AskTest.php`

- `it('does not change records when the question is off the books')`
- `it('renders a Portuguese refusal when the question is off the books')`
- `it('makes no http call for an off-books question')`

Assert the refusal and the untouched books. Do not assert which fake class the container resolved.

### Phase 6.2 — Answer about tasks when asked

- [ ] `AnswerQuestion` reads on-the-books tasks through the `tasks` scope, including a due when `scheduled_at` is set. Soft-deleted tasks stay out.
- [ ] The ask sets `consumed_at` on the voice turn and does not insert a record.
- [ ] The opening briefing still omits tasks.

**Feature tests:** `tests/Feature/Conversation/AskTasksTest.php`

- `it('renders the stored tasks when asked about tasks')`
- `it('omits a soft-deleted task from the answer')`
- `it('does not create a record when answering a task question')`
- `it('still omits tasks from the return-visit briefing')`

### Phase 6.3 — Answer about appointments when asked

- [ ] An appointment question may include future appointments the briefing hid. Soft-deleted appointments stay out.
- [ ] Order by `scheduled_at`, then `id`.

**Feature tests:** `tests/Feature/Conversation/AskAppointmentsTest.php`

- `it('includes a future appointment when asked about appointments')`
- `it('omits a soft-deleted appointment from the answer')`

---

## Phase 7 — Change the books only after yes

Depends on Phase 6.1. **US-07, US-08, US-09, US-10.**

The pending proposal is public state on the conversation page, kept in the SQL session by Livewire. Do not add a proposals table. A no, or leaving without yes, does not write.

Actions: `CreateRecord`, `ChangeRecord`, `RemoveRecord`, each with `handle()`. They set `user_id` from the authenticated user. They store `last_transcript` from the describing utterance, not from the yes.

### Phase 7.1 — Proposal is not a row

- [ ] `SecretaryTurn` repeats the proposal and waits. `records` is unchanged until yes.

**Feature tests:** `tests/Feature/Records/ProposalTest.php`

- `it('does not insert a record before yes')`
- `it('leaves the existing record unchanged when the answer is no')`
- `it('leaves the record unchanged when the proposal is left without yes')`

Assert with `assertDatabaseCount` or `expect()` on the original attributes. Do not add a test that a `record_proposals` table is missing.

### Phase 7.2 — Create after yes

- [ ] Appointment needs a title and a start time. Task needs a title; due is optional.
- [ ] The repeat includes title, time, and type. Yes calls `CreateRecord`.
- [ ] `last_voice_turn_id` points at the describing turn when that turn still exists.

**Feature tests:** `tests/Feature/Records/CreateRecordTest.php`

- `it('creates an appointment with a title and a start time after yes')`
- `it('does not create an appointment that has no start time')`
- `it('creates a task with no due date after yes')`
- `it('creates a task with a due date after yes')`
- `it('renders the title, time, and type before yes')`
- `it('stores the describing utterance as last_transcript, not the yes')`

The two task cases stay separate: the assertions differ.

### Phase 7.3 — Change title, time, or type

- [ ] Yes updates the same row’s title, `scheduled_at`, and `kind`.
- [ ] A type flip keeps the same `scheduled_at`.
- [ ] A task with null `scheduled_at` is not flipped until a start time is supplied. Until then the row stays a task and he asks for the time.

**Feature tests:** `tests/Feature/Records/ChangeRecordTest.php`

- `it('updates title, time, and kind on the same record after yes')`
- `it('keeps the previous title and transcript when the answer is no')`
- `it('keeps the same scheduled_at when the type flips')`
- `it('does not flip a task that has no due date before a start time is given')`

### Phase 7.4 — Remove after yes

- [ ] He names the record. Yes calls `RemoveRecord`, which soft-deletes. No leaves the row.
- [ ] A removed appointment is absent from the missed briefing and from answers.

**Feature tests:** `tests/Feature/Records/RemoveRecordTest.php`

- `it('soft-deletes the named record after yes')`
- `it('does not delete the record when the answer is no')`
- `it('omits a removed appointment from the missed briefing')`
- `it('omits a removed record from answers')`

Use `assertSoftDeleted`. The briefing case calls `travelTo()`.

### Phase 7.5 — Several records match

- [ ] When more than one on-the-books record matches, he names each match and writes nothing. He does not choose. The match list has `wire:key` on each row.
- [ ] After Gabriel chooses, Phase 7.3 or Phase 7.4 still waits for yes.

**Feature tests:** `tests/Feature/Records/DisambiguationTest.php`

- `it('names both matches and changes neither when two titles match')`
- `it('updates only the chosen record after yes')`

---

## Phase 8 — Clear failures

Depends on Phase 5.1. **US-06.** Typed notes are out of v1.

### Phase 8.1 — Microphone blocked

- [ ] A denied microphone is rendered in pt-BR: it is blocked, and how to allow it. No `voice_turns` row, because the failure happens before upload.

**Feature tests:** `tests/Feature/Conversation/MicrophoneDeniedTest.php`

- `it('renders how to allow the microphone when permission is denied')`
- `it('does not create a turn when the microphone is denied')`

### Phase 8.2 — He cannot speak

- [ ] `SpeechSynthesizer` contract. If synthesis is blocked, the same words are in the HTML, not only in audio.
- [ ] The success path still stores no reply file (Phase 5.6).

**Feature tests:** `tests/Feature/Conversation/SpeechTest.php`

- `it('renders the reply as text when speech is blocked')`
- `it('does not store an audio file when speaking a reply')`

Bind a fake synthesizer with `Pest\Laravel\mock`. Do not assert the binding class name.

### Phase 8.3 — No typed notes

- [ ] The conversation has no text field and no route that saves a record without audio.

**Feature tests:** `tests/Feature/Conversation/TypedNoteTest.php`

- `it('renders no text input for a note on the conversation')`
- `it('returns not found for a typed-note url')`

---

## Phase 9 — PWA shell

Depends on Phase 4.1 and Phase 5.1. The installed icon opens the same conversation.

### Phase 9.1 — Manifest and service worker

- [ ] Manifest `start_url` is `/`. The service worker is served. Login is still required for the conversation.
- [ ] HTTPS stays an operational requirement, not a table.

**Feature tests:** `tests/Feature/Pwa/ManifestTest.php`

- `it('sets the manifest start url to the conversation')`
- `it('does not name a separate inbox in the manifest')`
- `it('serves the service worker script')`

---

## Phase 10 — Voice-turn retention

Depends on Phase 2.3 and Phase 2.4. Specified in the schema. Not implemented.

### Phase 10.1 — Prune after 7 days

- [ ] `voice-turns:prune` hard-deletes every `voice_turns` row with `created_at` older than 7 days, in every status, including `uploaded` and `transcribing`.
- [ ] The record’s `last_voice_turn_id` becomes null. `last_transcript` stays.
- [ ] Newer rows stay.

**Feature tests:** `tests/Feature/Console/PruneVoiceTurnsTest.php`. `travelTo()` both sides of the cutoff. One test that creates one row per status is enough, because the result is the same. Do not add a dataset unless setup and assertions stay identical.

- `it('deletes turns older than seven days in every status')`
- `it('keeps the record transcript and clears the turn link when pruning')`
- `it('keeps turns newer than seven days')`

### Phase 10.2 — Schedule

- [ ] `routes/console.php` schedules the command `daily()->withoutOverlapping()`.

**Feature tests:**

- `it('schedules the prune daily without overlapping')` in `tests/Feature/Console/PruneVoiceTurnsTest.php`

Assert the schedule event’s command, frequency, and overlap lock. Do not boot a worker.

---

## Phase 11 — Not v1

Do not implement these while building Phases 2–10. No feature tests until a later phase opens them.

- [ ] Away-from-app reminders (Web Push vs the local Notification API is still undecided).
- [ ] Complete, snooze, recurrence, and search on the same `records` row.
- [ ] Password-reset screen. Keep `password_reset_tokens`. Do not build the flow.
- [ ] A `record_proposals` table so a yes-draft survives refresh.
- [ ] Extra users, sharing, public signup.
- [ ] Calendar export.
- [ ] Separate `appointments` and `tasks` tables.

---

## Story map

| Story | Phases |
|---|---|
| US-01 Log in | 3.1, 3.2 |
| US-02 First greeting | 2.1, 4.2 |
| US-03 Today and missed | 2.4, 2.5, 4.3 |
| US-04 Click to talk | 5.1–5.6 |
| US-05 Ask about tasks and appointments | 6.1–6.3 |
| US-06 Clear failure | 8.1–8.3 |
| US-07 Create after yes | 7.1, 7.2 |
| US-08 Change title, time, type | 7.3 |
| US-09 Remove after yes | 7.4 |
| US-10 Disambiguate | 7.5 |
| Schema retention | 10.1, 10.2 |
| After v1 | 11 |
