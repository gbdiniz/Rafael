---
name: grilling
description: Grill the user relentlessly about a plan, decision, or idea. Use when the user wants to stress-test their thinking, or uses any 'grill' trigger phrases.
---

Interview the user relentlessly until you reach a shared understanding. Map this as a **design tree**: every decision branches into the decisions that hang off it.

Work the tree in **rounds**. The **frontier** is every decision whose prerequisites are already settled: the questions you can ask _now_ without guessing at answers you haven't heard yet. Ask the whole frontier in one round, each question with a recommended answer. Then wait for the user's answers before the next round.

Ask the round with the AskQuestion tool. Do not type the questions as a chat list. One call carries every frontier question.

- `id` is a short stable slug.
- `prompt` is the question title and the body. Do not repeat the options inside the prompt.
- `options` has at least two choices. Put the recommended answer first and end its label with `(Recommended)`.
- Set `allow_multiple` only when more than one option can be true at once.
- The user can always add their own answer. Do not add an "Other" option yourself.

Wait for the tool result. Do not continue the round in prose while it is open.

Each round the user answers reshapes the tree: settled decisions push the frontier outward and unblock questions that depended on them. Recompute the frontier and ask the next round. A question whose answer depends on another question still open in this round belongs to a _later_ round, not this one.

Finding _facts_ is your job, never the user's. When a frontier question needs a fact from the environment (filesystem, tools, etc.), dispatch a sub-agent to find it; don't ask the user for anything you could look up yourself. Don't block on it: a running exploration is an unsettled prerequisite, so only the questions downstream of it wait for the sub-agent to report; ask the rest of the frontier now. The _decisions_ are the user's: put each to them and wait.

The session is done when the frontier is empty: every branch of the design tree visited, nothing left silently assumed. Do not act on it until the user confirms you have reached a shared understanding.
