import type { CSSProperties } from 'react'

/**
 * Moments on a pinned stage. The stage sets one custom property, --p (0 to
 * 1, the scroll), and each thing on it reads its own moment from that:
 * nothing is all there at once, each thing arrives in turn.
 */

/** An element that fades and settles in while --p runs from `from` to `to`. */
export function venue(from: number, to: number, rise = 18): CSSProperties {
  const t = `clamp(0, calc((var(--p, 0) - ${from}) / ${(to - from).toFixed(4)}), 1)`
  return { opacity: t, transform: `translate3d(0, calc((1 - ${t}) * ${rise}px), 0)` }
}

/** The same, for something that also leaves again between `out` and `gone`. */
export function passage(from: number, to: number, out: number, gone: number): CSSProperties {
  const arriving = `clamp(0, calc((var(--p, 0) - ${from}) / ${(to - from).toFixed(4)}), 1)`
  const leaving = `clamp(0, calc((${gone} - var(--p, 0)) / ${(gone - out).toFixed(4)}), 1)`
  return { opacity: `min(${arriving}, ${leaving})`, transform: `translate3d(0, calc((1 - ${arriving}) * 18px - (1 - ${leaving}) * 18px), 0)` }
}

/** A stroke that draws itself while --p runs from `from` to `to` (pathLength="1" on the shape). */
export function trace(from: number, to: number): CSSProperties {
  return { strokeDasharray: 1, strokeDashoffset: `clamp(0, calc(1 - (var(--p, 0) - ${from}) / ${(to - from).toFixed(4)}), 1)` }
}
