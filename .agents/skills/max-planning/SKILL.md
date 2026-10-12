---
name: maxplanning
description: >
  Document a feature in the existing product docs, draw every state in Figma
  using DESIGN.md, then implement the service and the code, then write extra
  notes back into those docs. Use when starting a new feature, a new phase,
  a new screen, a voice change, or a sibling service.
---

# Plan Flow

Follow this order. Do not start a later step while an earlier one is still open.

## 1. Document first

Update the docs that already own the product. Do not add a new spec file.

- `docs/user-stories.md` for what Gabriel can do
- `docs/project-description.md` and `PRODUCT.md` for the product and the technical map
- `docs/project-phases.md` for the phase, the checks, and the Pest test names
- `DESIGN.md` when the screen changes

## 2. Figma, in this system

Create or update a Figma Design file. Load the Figma create-file skill and the `use_figma` skill before any Figma tool call.

Build from `DESIGN.md` only: void `#05070c`, ice `#d8f4ff`, and the existing controls. Draw every state the feature adds. No third color, no wordmark, no play icon, no inbox.

## 3. Implement

Sibling services stay outside this repo, the way Rafael-Whisper and Rafael-Piper do. This app stores a URL and a client. Then write the code and the Pest tests named in the phase. Use `Http::fake` and SQLite. Do not store spoken replies as files.

## 4. Extra notes

After the build, write only what the build taught back into the docs from step 1. Do not add a new notes file.
