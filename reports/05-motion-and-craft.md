# Motion and Interaction Craft — Ebni Academy

**Date:** 2026-08-27
**Scope:** `web/src` — the 19 UI primitives, the sales page, and the surfaces a paying student uses
**Standard:** Emil Kowalski's design-engineering skills (`emilkowalski/skills`)

> **Status, 2026-10-04:** the tables below were brought up to date by the go-live pass. ✅ = fixed and covered by a test; *decided* = deliberately left as it is, owner's call; *mitigated* = the harm is gone though the mechanism remains. What was done, and how it was verified: [`06-go-live-pass.md`](06-go-live-pass.md).

## Summary

Three reviewers audited the SPA against the frequency table, the easing and duration rules, and the
performance rules. Every finding faced a verifier that had to confirm the "before" code exists, that
the "after" compiles against **this** Tailwind config and Radix version, and that the frequency
judgement holds. **56 were raised, 50 survived**, 6 rejected.

The pattern is consistent: the app is not over-animated, it is **under-acknowledged**. Nothing in the
product answered a press — not a single button, not the checklist checkbox that a student taps dozens
of times a session. Meanwhile the two places motion did run unattended were the two that cost most:
an infinite `top`/`left` keyframe over the video, and a reduced-motion block that removed everything
including the fades that carry meaning.

Counts by severity: High 13 · Medium 29 · Low 8. **18 fixed and shipped**, marked ✅.

## The rules being applied

| Question | Rule used |
|---|---|
| Should it animate? | 100+ times/day → never · tens/day → reduce · occasional → standard · rare → delight |
| Which easing? | entering/exiting → `ease-out` · moving → `ease-in-out` · hover → `ease` · **never `ease-in`** |
| Which curve? | Built-ins are too weak: `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` |
| How fast? | press 100–160ms · popovers 125–200ms · dropdowns 150–250ms · modals 200–500ms · **UI under 300ms** |
| What may animate? | `transform` and `opacity` only — they skip layout and paint |
| Reduced motion? | Fewer and gentler, **not zero**: keep opacity and colour, drop movement |

## Findings

| ID | Sev | Lens | Finding | Location |
|----|-----|------|---------|----------|
| M1 | High | ui-primitives | prefers-reduced-motion sets every transition to 0.01ms — that is zero, not gentler ✅ | `src/index.css:55-62` |
| M2 | High | ui-primitives | No button in the app acknowledges a press — zero :active feedback in components/ui ✅ | `src/components/ui/button.tsx:7` |
| M3 | High | ui-primitives | Dropdown menu scales from its own centre instead of from its trigger ✅ | `src/components/ui/dropdown-menu.tsx:63` |
| M4 | High | ui-primitives | Select popover also scales from centre, with the same default `ease` curve ✅ | `src/components/ui/select.tsx:68` |
| M5 | High | landing | No `:active` press feedback on Button — including every CTA on the sales page ✅ | `src/components/ui/button.tsx:7` |
| M6 | High | landing | `hover:` classes are ungated — they stick on after a tap on every touch device ✅ | `tailwind.config.js:2-4` |
| M7 | High | landing | Hero background video hard-cuts in when it starts playing ✅ | `src/components/BackgroundVideo.tsx:59-68` |
| M8 | High | app-surfaces | Checklist checkbox has no press feedback — the highest-frequency action in the product answers a tap with noth ✅ | `src/components/player/ModuleChecklist.tsx:26-40` |
| M9 | High | app-surfaces | Ticking a checklist item is routed through a 150ms colour crossfade, and the strike-through desyncs from it ✅ | `src/components/player/ModuleChecklist.tsx:33, 44` |
| M10 | High | app-surfaces | The Button primitive has no `:active` state — every pressable element in the player, checkout and orders pages ✅ | `src/components/ui/button.tsx:7` |
| M11 | High | app-surfaces | `prefers-reduced-motion` is implemented as a blanket kill switch — the exact thing the standard says not to do ✅ | `src/index.css:48-63` |
| M12 | High | app-surfaces | Progress bar uses `transition: all` to animate `width` — an unbounded transition on a layout property, fired o ✅ | `src/components/ui/progress.tsx:19-22` |
| M13 | High | app-surfaces | The video watermark animates `top`/`left` in an infinite loop, running layout on the one surface where frame b ✅ | `src/components/player/WatermarkOverlay.tsx:10-26` |
| M14 | Medium | ui-primitives | Submenu popover scales from centre ✅ | `src/components/ui/dropdown-menu.tsx:46` |
| M15 | Medium | ui-primitives | `transition-all` on the tab trigger, plus no press feedback ✅ | `src/components/ui/tabs.tsx:29` |
| M16 | Medium | ui-primitives | Progress indicator uses `transition-all` at the 300ms ceiling ✅ | `src/components/ui/progress.tsx:20` |
| M17 | Medium | ui-primitives | `transition-all` on the accordion trigger animates nothing ✅ | `src/components/ui/accordion.tsx:24` |
| M18 | Medium | ui-primitives | Floating label: `transition-all` with the weak built-in `ease-out` ✅ | `src/components/ui/floating-input.tsx:58` |
| M19 | Medium | ui-primitives | Dialog enter and exit are the same 200ms, with no easing curve ✅ | `src/components/ui/dialog.tsx:42` |
| M20 | Medium | ui-primitives | Dialog overlay runs 150ms while its content runs 200ms — the scrim and panel land 50ms apart ✅ | `src/components/ui/dialog.tsx:22` |
| M21 | Medium | ui-primitives | Alert dialog repeats the symmetric-timing and missing-curve problem ✅ | `src/components/ui/alert-dialog.tsx:36` |
| M22 | Medium | ui-primitives | Alert dialog overlay desyncs from its content by 50ms ✅ | `src/components/ui/alert-dialog.tsx:18` |
| M23 | Medium | ui-primitives | Switch thumb inherits Tailwind's default ease-in-out on a direct press response ✅ | `src/components/ui/switch.tsx:19` |
| M24 | Medium | ui-primitives | Switch has no press feedback despite being the most direct-manipulation control in the set ✅ | `src/components/ui/switch.tsx:11` |
| M25 | Medium | ui-primitives | Menu items fade colour on arrow-key navigation, smearing behind the keyboard cursor ✅ | `src/components/ui/dropdown-menu.tsx:81` |
| M26 | Medium | landing | FAQ accordion animates `height` via keyframes — non-GPU, non-interruptible, and symmetric ✅ | `tailwind.config.js:67-80` |
| M27 | Medium | landing | FaqChat swaps Plus/Minus as a hard DOM replacement — no transition, inconsistent with the app's own accordion ✅ | `src/components/ui/faq-chat.tsx:62-70` |
| M28 | Medium | landing | FaqChat question bubble is pressable but has no press feedback ✅ | `src/components/ui/faq-chat.tsx:50-57` |
| M29 | Medium | landing | ScrollReveal's left/right directions are hardcoded physical — they mirror wrong in Arabic RTL ✅ | `src/components/ScrollReveal.tsx:54-60` |
| M30 | Medium | landing | The money sections get no scroll reveal — the page animates its middle and forgets its close ✅ | `src/pages/CourseLandingPage.tsx:436, 518, 696, 790` |
| M31 | Medium | landing | No hover affordance on the bento proof tiles or the project photos — *decided* | `src/components/landing/TrackRecordBento.tsx:75, 79-84` |
| M32 | Medium | landing | Sticky mobile CTA bar: no easing named, and symmetric in/out ✅ | `src/pages/CourseLandingPage.tsx:854-855` |
| M33 | Medium | app-surfaces | The strong easing curves are defined twice and consumed zero times — every audited transition still runs on Ta ✅ | `src/index.css:36-41 (and tailwind.config.js:61-66)` |
| M34 | Medium | app-surfaces | "Marquer comme terminé" disables itself for the round trip even though the mutation is already optimistic ✅ | `src/pages/PlayerPage.tsx:227-237` |
| M35 | Medium | app-surfaces | Select and dropdown panels scale from their centre instead of from the trigger ✅ | `src/components/ui/select.tsx:68 (and dropdown-menu.tsx:46, 63)` |
| M36 | Medium | app-surfaces | Popover and modal entrances have no easing set at all, and exit at the same speed as enter ✅ | `src/components/ui/dialog.tsx:42 (and select.tsx:68, dropdown-menu.t…` |
| M37 | Medium | app-surfaces | The current-lesson marker in the sidebar is a 2px border that only the active row has, so every lesson you ope ✅ | `src/components/player/PlayerSidebar.tsx:83-86` |
| M38 | Medium | app-surfaces | The dashboard hand-rolls a second progress bar with `transition-all` on width instead of using the Progress pr ✅ | `src/pages/DashboardPage.tsx:69-81` |
| M39 | Medium | app-surfaces | A 2-second pulse plays on the video skeleton at the start of every lesson, making the player feel slower than  ✅ | `src/components/player/VideoPlayer.tsx:44-49` |
| M40 | Medium | app-surfaces | Loading states are a centred line of text that the loaded layout does not resemble, so every page resolves wit ✅ | `src/pages/PlayerPage.tsx:89-95 (same shape at ChecklistsPage.tsx:21…` |
| M41 | Medium | app-surfaces | Optimistic checklist mutations roll back silently — a failed tick un-ticks itself with no explanation ✅ | `src/api/checklists.ts:71 (and 104, 126)` |
| M42 | Medium | app-surfaces | Hover states are not gated for touch, so tapping a checklist row on a tablet leaves the delete button stuck vi ✅ | `tailwind.config.js:2-4` |
| M43 | Low | ui-primitives | Accordion open and close are identically timed on the weak built-in ease-out ✅ | `tailwind.config.js:78-79` |
| M44 | Low | ui-primitives | Accordion chevron rotates on Tailwind's default curve ✅ | `src/components/ui/accordion.tsx:30` |
| M45 | Low | ui-primitives | FAQ icon hard-swaps in one frame while everything around it animates ✅ | `src/components/ui/faq-chat.tsx:69` |
| M46 | Low | ui-primitives | Dialog close button has no press feedback ✅ | `src/components/ui/dialog.tsx:48` |
| M47 | Low | landing | Stagger delays exceed the 30-80ms band and compound past a second on the bento ✅ | `src/components/landing/TrackRecordBento.tsx:74` |
| M48 | Low | landing | `transition-all` on the curriculum accordion trigger for a `hover:underline` ✅ | `src/components/ui/accordion.tsx:24` |
| M49 | Low | landing | ScrollReveal reimplements reduced-motion detection instead of using the project's hook, and never follows chan ✅ | `src/components/ScrollReveal.tsx:24-29` |
| M50 | Low | app-surfaces | `transition-all` on the checkout tab trigger animates the box-shadow and anything else that changes ✅ | `src/components/ui/tabs.tsx:29` |
## Details — what was fixed

### The foundation

The strong curves now exist as both CSS variables (`--ease-out`, `--ease-in-out`, `--ease-drawer`)
and Tailwind utilities (`ease-out-strong`, `ease-in-out-strong`, `ease-drawer`). The audit's sharpest
observation was that defining them and then consuming them nowhere is worse than not having them —
so every transition touched below now names one.

### Reduced motion was a kill switch ✅ High

The block set `transition-duration: 0.01ms !important` on `*`, which is zero — every dialog fade,
hover colour and focus ring snapped between states, reading as broken rather than calm. It also
contradicted the comment sitting two lines above it, which said opacity should survive.

It now keeps opacity, colour, borders and shadows at a real 150ms and neutralises **only** movement,
by zeroing the six `--tw-enter-*` / `--tw-exit-*` variables that `tailwindcss-animate` interpolates.
Every slide and zoom becomes a plain fade. Spinners are exempted so they do not freeze mid-turn.

### Nothing acknowledged a press ✅ High

`Button` had `transition-colors` and no `:active` state, so every CTA on the sales page, every
checkout action and every admin control was inert under the finger. It now scales to `0.97` over
150ms on the strong ease-out curve. The checklist checkbox — measurably the most repeated action in
the product — gets a tighter 100ms scale to `0.90`, and lost the 150ms colour crossfade that was
smearing the state change it is supposed to report instantly.

### Popovers scaled from the wrong point ✅ High

The dropdown menu and select panel both scaled from their own centre. They now scale from their
trigger via Radix's `--radix-*-content-transform-origin`. Modals deliberately keep
`transform-origin: center` — they are not anchored to anything.

### The watermark ran layout on every frame of every video ✅ High

The anti-piracy overlay drifted between corners by animating `top` and `left` in a 40-second infinite
loop, over the one surface in the product that must not drop frames. It is now a composited
`transform` on an overlay-sized box. Verified empirically rather than assumed: the label was measured
at (5 %, 6 %), (55 %, 6 %), (55 %, 84 %) and (5 %, 84 %) — the same four corners as before.

### Hover stuck on every touch device ✅ High

`hover:` utilities were ungated, so a tap on a phone left the hover state applied until the next tap
elsewhere. `future.hoverOnlyWhenSupported` now gates them behind a real pointer.

### The hero video hard-cut in ✅ High

The sales page's first impression was a scrim that jumped to video the instant the network delivered
a frame. It now fades over 700ms as soon as a frame exists — including when autoplay is refused,
where the still first frame is the fallback and deserves the same fade.

### Dialogs ✅ Medium

Scrim and panel ran on different clocks (150ms and 200ms) with no easing named, and closed as slowly
as they opened. They now share the strong ease-out curve and exit in 150ms — the system responding,
not the user deciding.

## Recommended order for what remains

1. **Loading states** (M40). Every page shows a centred line of text that the loaded layout does not
   resemble, so each load ends in a jump. Skeletons matching the real layout would remove it — the
   single largest remaining perceived-performance win.
2. **RTL correctness in `ScrollReveal`** (M29). Its left/right directions are physical, so reveals
   mirror the wrong way in Arabic. A correctness bug for a third of the audience, not a polish item.
3. **Silent optimistic rollbacks** (M41). A failed checklist tick un-ticks itself with no explanation;
   the student concludes the app lost their work.
4. **The money sections get no scroll reveal** (M30) while the middle of the page does — the close
   is the part that should land hardest.
5. The remaining `transition-all` occurrences and stagger timings, as one sweep.

## Deliberately not done

Six findings were rejected on verification and are not listed as work: notably a claim that
`AnimatedCounter` re-renders per frame (it targets a single node and is gated on reduced motion,
where it jumps to the final value), and a proposal to add a slide animation to the mobile sidebar —
opened many times a session, so the frequency table says leave it instant.

No animation library was added. There is no `framer-motion` in this project and nothing here needed
one: every fix is CSS, which also keeps the motion off the main thread.
