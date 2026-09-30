# Rafael

Rafael is your personal secretary on the web. He reminds you of your appointments and tasks. You can ask him about them, and tell him to create, change, or remove them.

The technical map is in [docs/project-description.md](docs/project-description.md). The app runs on **MySQL**. Cache and the queue need Redis on `127.0.0.1:6379` and the PHP Redis extension (`ext-redis`). Sessions stay in the database.

## What it is

A conversation with your secretary.

Rafael speaks first. The first time, he only greets you. After that, he tells you today’s appointments and any earlier appointment you never removed, then waits. You ask about your tasks. You tell him to create, change, or remove an appointment or a task, and he repeats the change before it is saved.

He speaks Portuguese, and you answer in Portuguese. The app is for one person. There is no public signup and no sharing.

It is not a silent recorder, not a list you scan before he has spoken, and not a general chatbot. The microphone is not always on. Reminders that arrive while you are away come later. While you are in the app, he already tells you what is on the books.

## How it works

You open the site and Rafael starts.

- The first time, he greets you and waits.
- On later visits, he tells you today’s appointments and any earlier appointment you never removed, then waits.
- You click to talk, and click again to stop.
- You can ask about your tasks. He answers from what he has stored.
- You can tell him to create, change, or remove an appointment or a task. He repeats it and waits for a yes. Only then does he save the change.

## Principles

1. **Rafael starts.** Opening the app is his turn. You do not have to press anything to hear him.
2. **First visit is a greeting.** No appointments, no tasks, no tour of features.
3. **Returning visits start with appointments.** Tasks are there when you ask. He does not dump both lists unasked.
4. **Voice is a draft.** A change is saved only after he repeats it and you agree. A wrong removal is worse than one extra turn.
5. **Questions stay on your records.** He can talk about your tasks and appointments. He is not a general chatbot.
6. **Audio is not a memory.** What remains is the words and the record, not the recording.
7. **Rafael owns reminders** when they exist. A calendar export can be a later door, not the core.

## How to use

1. Open the app and listen. The first time, he only says hello.
2. On the next visits, listen to today’s appointments and any earlier one you never removed.
3. Click the control to speak. Click it again when you are done.
4. Ask about a task, or tell him to create, change, or remove an appointment or a task.
5. If he is changing something, wait until he repeats it, then agree. That agreement is what saves it.
