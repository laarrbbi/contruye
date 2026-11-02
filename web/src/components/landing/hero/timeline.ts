import { EXPLODED_LIFT, STOREYS, STOREY_H } from './building'

/**
 * The hero's one clock: `p` runs from 0 to 1 over the section's scroll.
 *
 *   0 ──── INTRO_END ─────────────────────── STEPS_END ──── 1
 *   the pitch   twelve phases, one storey each   the windows come on
 *
 * Text, SVG and WebGL all read their state from here, so scrolling back
 * simply plays everything in reverse.
 */
export const INTRO_END = 0.07
export const STEPS_END = 0.9
const STEP_SPAN = (STEPS_END - INTRO_END) / STOREYS

export const clamp01 = (value: number) => (value < 0 ? 0 : value > 1 ? 1 : value)
export const mix = (from: number, to: number, t: number) => from + (to - from) * t
export const easeInOut = (t: number) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2)

/** How far through its own step a storey is: below 0 before, above 1 after. */
function local(index: number, p: number): number {
  return (p - (INTRO_END + index * STEP_SPAN)) / STEP_SPAN
}

/** -1 during the pitch, 0…11 during the steps, STOREYS once the building is up. */
export function activeStep(p: number): number {
  if (p < INTRO_END) return -1
  if (p >= STEPS_END) return STOREYS
  return Math.min(STOREYS - 1, Math.floor((p - INTRO_END) / STEP_SPAN))
}

export interface StoreyState {
  /** 0 floating in the dismantled model, 1 resting on the storey below. */
  descent: number
  /** 0 a line drawing, 1 concrete and glass. */
  material: number
  /** 0 dark, 1 its windows lit. */
  lit: number
  /** True while this is the storey being placed. */
  active: boolean
}

export function storeyState(index: number, p: number): StoreyState {
  const t = local(index, p)
  return {
    // Down in the first half of its step, poured in the second.
    descent: easeInOut(clamp01(t / 0.5)),
    material: clamp01((t - 0.42) / 0.5),
    // From the ground up, a moment apart.
    lit: clamp01((p - (STEPS_END + 0.012) - index * 0.006) / 0.035),
    active: t > 0 && t < 1,
  }
}

/**
 * How high a storey is: its place in the finished building, plus every gap
 * still open at or below it. When a storey lands, all those above come down
 * with it, so the dismantled model closes from the ground up and its top
 * sinks a little at every step.
 */
export function storeyY(index: number, p: number, gap: number): number {
  let y = index * STOREY_H
  for (let below = 0; below <= index; below++) {
    y += (1 - storeyState(below, p).descent) * (below === 0 ? EXPLODED_LIFT : gap)
  }
  return y
}

/** The top of the model at this moment: what a camera has to keep in frame. */
export function modelTop(p: number, gap: number): number {
  return storeyY(STOREYS - 1, p, gap) + STOREY_H
}

/** How visible each piece of text is, and which way it is leaving. */
export function introPresence(p: number): number {
  return 1 - clamp01((p - 0.012) / (INTRO_END - 0.022))
}

export function stepPresence(index: number, p: number): { opacity: number; shift: number } {
  const t = local(index, p)
  const entering = clamp01(t / 0.16)
  // The last step hands over to the closing line, like every other.
  const leaving = clamp01((t - 0.86) / 0.14)
  return { opacity: entering * (1 - leaving), shift: (1 - entering) * 1 - leaving * 1 }
}

export function outroPresence(p: number): number {
  return clamp01((p - STEPS_END - 0.005) / 0.03)
}

/** Where to scroll to read a given step: past its entrance, storey landed. */
export function progressForStep(index: number): number {
  return INTRO_END + (index + 0.62) * STEP_SPAN
}
