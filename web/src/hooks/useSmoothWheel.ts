import { useEffect } from 'react'

/**
 * Lenis on a desktop pointer: it eases the mouse wheel's coarse steps, so
 * that what is tied to the scroll moves without jolts. A touch screen keeps
 * the browser's own scrolling, which is already smooth and which people know
 * by hand. Nothing is smoothed for someone who asked for reduced motion.
 */
export function useSmoothWheel(active: boolean) {
  useEffect(() => {
    if (!active) return
    if (!window.matchMedia('(hover: hover) and (pointer: fine) and (min-width: 1024px)').matches) return
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    let cancelled = false
    let stop: (() => void) | undefined
    void import('lenis')
      .then(({ default: Lenis }) => {
        if (cancelled) return
        const lenis = new Lenis({ lerp: 0.1, anchors: true })
        let frame = requestAnimationFrame(function tick(time) {
          lenis.raf(time)
          frame = requestAnimationFrame(tick)
        })
        stop = () => {
          cancelAnimationFrame(frame)
          lenis.destroy()
        }
      })
      .catch(() => {
        /* The page scrolls as the browser scrolls it. */
      })
    return () => {
      cancelled = true
      stop?.()
    }
  }, [active])
}
