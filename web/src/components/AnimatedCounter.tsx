import { useEffect, useRef, useState } from 'react'
import { intlLocale } from '@/lib/utils'

/**
 * Counts up to `end` the first time it scrolls into view.
 *
 * Design ported from the Aurea project, with two changes for this codebase:
 * the number is localised (so Arabic gets its own digits), and users who ask
 * for reduced motion get the final value immediately instead of an animation.
 */
export function AnimatedCounter({
  end,
  duration = 1800,
  prefix = '',
  suffix = '',
  locale,
  className,
}: {
  end: number
  duration?: number
  prefix?: string
  suffix?: string
  locale?: string
  className?: string
}) {
  const [count, setCount] = useState(0)
  const ref = useRef<HTMLSpanElement>(null)

  useEffect(() => {
    const node = ref.current
    if (!node) return

    // Counting up to a small number just shows "0" for most of the animation,
    // which reads as broken. Only large figures are worth animating.
    const worthAnimating = end >= 10
    if (!worthAnimating || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setCount(end)
      return
    }

    let frame = 0
    // Guard is per-effect, not a persistent ref: under StrictMode the effect
    // runs twice, and a ref would leave the second run thinking it had already
    // animated — the counter would sit at 0 forever.
    let started = false
    const observer = new IntersectionObserver(
      (entries) => {
        if (!entries[0].isIntersecting || started) return
        started = true
        observer.disconnect()

        let startTime: number | undefined
        const step = (timestamp: number) => {
          startTime ??= timestamp
          const progress = Math.min((timestamp - startTime) / duration, 1)
          const easeOut = 1 - Math.pow(1 - progress, 3)
          setCount(Math.floor(easeOut * end))
          if (progress < 1) frame = window.requestAnimationFrame(step)
        }
        frame = window.requestAnimationFrame(step)
      },
      { threshold: 0.4 },
    )

    observer.observe(node)
    return () => {
      observer.disconnect()
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [end, duration])

  return (
    <span ref={ref} className={className}>
      {prefix}
      {count.toLocaleString(intlLocale(locale))}
      {suffix}
    </span>
  )
}
