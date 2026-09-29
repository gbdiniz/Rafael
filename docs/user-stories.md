# User stories

Gabriel is the only user. Speech and interface copy are pt-BR. Away-from-app reminders are not in these stories.

A day is Gabriel’s local calendar day. A missed appointment is one whose start is before today and that was never removed. Today’s appointments are the ones whose start falls on today, whether or not that time has already passed.

## Enter

**US-01. Log in.** As Gabriel, I want to sign in to the only account, so that Rafael’s records are mine and the app is not open to anyone else.

- The app has one login and no public signup.
- The conversation, the records, and audio upload require that session.

**US-02. Hear a greeting the first time.** As Gabriel, I want Rafael to greet me the first time I open the app, so that an empty day is not recited at me.

- The first open is a greeting only.
- He does not list appointments or tasks, and he does not explain the product.
- Then he waits.

**US-03. Hear today and what I missed.** As Gabriel, I want Rafael to tell me today’s appointments and any earlier appointment I never removed, so that I know what is on today’s books and what is still hanging.

- This happens on every open after the first.
- He includes every appointment whose start falls on today.
- He includes every appointment whose start is before today and that was never removed.
- He does not recite future days, and he does not recite tasks.
- Then he waits.

## Talk

**US-04. Click to talk.** As Gabriel, I want to click once to speak and click again to stop, so that the microphone is only on while I mean it to be.

- The first click starts recording. The bars show that he is listening.
- The second click stops recording.
- Leaving the control does not stop a recording that is already going.
- After the turn, the audio is transcribed and the file is deleted.

**US-05. Ask about my tasks.** As Gabriel, I want to ask Rafael about my tasks, so that I hear them when I ask and not in the opening briefing.

- He answers from the stored tasks.
- He does not answer from the open web.
- A question about an appointment is answered from the stored appointments the same way.

**US-06. Hear a clear failure.** As Gabriel, I want to be told when the microphone or his voice is blocked, so that I know why the turn did not happen and what to do.

- A denied microphone says that it is blocked and how to allow it.
- If he cannot speak, that failure is said in words too.
- Typed notes without a microphone are not available.

## Change the books

A create, change, or removal is saved only after Rafael repeats the result and Gabriel agrees. A no, or silence instead of a yes, leaves the record as it was.

**US-07. Create an appointment or a task.** As Gabriel, I want to tell Rafael to create an appointment or a task, so that it is on the books after I agree.

- An appointment needs a title and a start time.
- A task needs a title. The due date is optional.
- He repeats the title, the time, and the type, and waits for a yes.
- The record exists only after that yes.

**US-08. Change the title, the time, or the type.** As Gabriel, I want to change the title, the time, or the type of a record, so that the books match what I meant.

- A change can rewrite the title, the start time or due date, and whether it is an appointment or a task.
- When the type flips, the time changes role: a start time becomes the due date, or a due date becomes the start time.
- If a task has no due date and would become an appointment, he asks for a start time before the yes.
- He repeats the whole result and waits for a yes.
- The record changes only after that yes.

**US-09. Remove an appointment or a task.** As Gabriel, I want to tell Rafael to remove a record, so that it leaves the books only after I agree.

- He repeats which record he will remove and waits for a yes.
- The record is removed only after that yes.
- A removed appointment is no longer recited as missed.

**US-10. Pick when more than one record fits.** As Gabriel, I want Rafael to ask which record I mean, so that he does not change or remove the wrong one.

- If more than one record matches what I said, he names the matches and waits.
- He does not pick one himself.
- After I choose, US-08 or US-09 applies: he still repeats the result and waits for a yes.
