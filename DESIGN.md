---
name: Rafael
description: Click-to-talk on a void — five ice bars, nothing else.
colors:
  void: "#05070c"
  ice: "#d8f4ff"
typography:
  body:
    fontFamily: "system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "normal"
  label:
    fontFamily: "system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 560
    lineHeight: 1.4
    letterSpacing: "normal"
rounded:
  bar: "5px"
spacing:
  xs: "4px"
  sm: "10px"
  md: "24px"
  lg: "72px"
  control: "88px"
components:
  talk-bars:
    backgroundColor: "transparent"
    textColor: "{colors.ice}"
    rounded: "{rounded.bar}"
    size: "{spacing.control}"
    width: "{spacing.control}"
    height: "{spacing.control}"
    padding: "0"
  talk-bars-recording:
    backgroundColor: "transparent"
    textColor: "{colors.ice}"
    size: "{spacing.control}"
  mic-fail-title:
    backgroundColor: "transparent"
    textColor: "{colors.ice}"
    typography: "{typography.label}"
  mic-fail-body:
    backgroundColor: "transparent"
    textColor: "{colors.ice}"
    typography: "{typography.body}"
---

# Design System: Rafael

## Overview

**Creative North Star: "The Ice Void"**

Rafael’s visual world is a near-black field and one ice-cyan mark. The home is the talk: five rounded bars sit alone in the center of the void. There is no wordmark, no inbox list, no holographic ring, and no labeled button. Presence is the bars; everything else is absence.

Density is extreme sparsity. The palette is two values. Motion lives only inside the bars while Rafael is listening. Failure is plain pt-BR type under the control — named, recoverable, then gone again. The aesthetic philosophy is refusal: if it is not the talk, it does not appear.

**Key Characteristics:**
- Full-bleed void field (`#05070c`) with no texture, gradient, or second surface
- One 88×88 ice control: five symmetric rounded bars (`#d8f4ff`)
- Idle control unlabeled; aria-label only
- A click starts the bars; another click returns them to rest
- Mic denial is centered ice type below the control — no toast, modal, or chrome

## Colors

Two colors only. Ice is the sole accent; void is the entire field.

### Primary
- **Ice** (`#d8f4ff`): The bars fill, body text, selection highlight (ice on void), and focus outline. Every visible mark that is not empty space.

### Neutral
- **Void** (`#05070c`): Page background, selection foreground when ice is selected, and the implied “off” state of the field. Never a card or panel fill — it *is* the canvas.

### Named Rules
**The Two-Value Rule.** Only void and ice. No third fill, tint wash, or brand accent on this world’s surfaces.

**The Ice-Is-Signal Rule.** Ice appears only as the control, its focus ring, selection, or failure copy. It is never decorative ornament.

## Typography

**Display Font:** none — the bars are the brand mark
**Body Font:** system-ui (with sans-serif fallback)
**Label/Mono Font:** system-ui (same stack; heavier weight for titles)

**Character:** Utility prose, not display identity. Type appears only when the microphone fails; idle home has no visible words.

### Hierarchy
- **Label** (560, 16px, 1.4): Failure title (`Sem microfone`). Block display, 4px gap below.
- **Body** (400, 16px, 1.4): Failure recovery line (`Permita o microfone para falar com Rafael.`). Centered, full width within side insets.

### Named Rules
**The Silent Idle Rule.** No visible type on the idle home. Copy appears only for mic denial.

**The Bars-Are-Brand Rule.** Do not invent a display face or wordmark for the home. The five bars carry identity.

## Layout

The spatial model is a single centered cell: full viewport height (`100dvh`), CSS grid `place-items: center`. The talk control is fixed at 88×88px and stays centered on phone (390×844) and desktop (1440×900) alike — no breakpoint restyle of size or position.

Failure copy sits absolutely at `top: calc(50% + 72px)` with `24px` side insets, centered. Focus outline sits `10px` outside the control. No columns, no nav chrome, no secondary regions.

### Named Rules
**The Centered Talk Rule.** The 88px bars remain the sole visual anchor at every viewport; do not pin them to corners or grow them with breakpoints.

## Elevation & Depth

Flat. No box-shadows, no blur, no layered panels. Depth is not simulated; the void is continuous and the bars sit in it as color, not as lifted objects.

### Named Rules
**The Flat Field Rule.** No shadows, glows, or ring frames. Recording state is bar motion, not elevation.

## Shapes

Form language is five vertical capsules inside an 88×88 square. Each bar is 10px wide with fully rounded ends (`border-radius: 5px`). Heights are symmetric: 20 / 44 / 68 / 44 / 20px; left offsets 6 / 22 / 38 / 54 / 70px. No circles, punches, or outer frames.

### Named Rules
**The Five-Bar Silhouette Rule.** The talk control is this exact five-bar geometry. Do not substitute a mic glyph, waveform ring, or labeled pill.

## Components

### Talk Bars (signature)
The only idle interactive element. Transparent hit target, ice-colored bars, pointer cursor, no border or fill behind the icon.

- **Shape:** 88×88 control; bars as above (`5px` radius)
- **Primary / Idle:** ice bars at rest; `aria-label` “Falar com Rafael”; `aria-pressed="false"`
- **Recording (click):** class `is-recording`; `aria-label` “Parar”; bars scale on staggered loops with `cubic-bezier(0.2, 0.8, 0.2, 1)` (side ~520–560ms, mid ~380–420ms, center 460ms)
- **Focus:** `2px` ice outline, `10px` offset
- **Reduced motion:** animations off; bars at `opacity: 0.55` while recording

### Mic Denial
Absolute failure block under the control when getUserMedia fails.

- **Title:** strong, weight 560, ice
- **Body:** span, weight 400, ice
- **Placement:** centered, `72px` below vertical center, `24px` horizontal inset
- **State:** hidden until denial; no animation chrome

## Do's and Don'ts

### Do:
- **Do** keep the home as void + one unlabeled 88px ice bars control.
- **Do** use ice for bars, focus, selection, and mic-failure copy only.
- **Do** animate bars on click and stop on the next click; honor `prefers-reduced-motion`.
- **Do** show pt-BR denial as title + recovery under the control when the mic is blocked.

### Don't:
- **Don't** put the inbox list, date, logo, or wordmark on the home.
- **Don't** wrap the bars in a holographic ring, glow, or second frame.
- **Don't** add a visible idle label, badge, or kicker on the control.
- **Don't** introduce a third brand color or a card/panel surface on this field.
