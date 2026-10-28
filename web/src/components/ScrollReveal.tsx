import { useEffect, useRef, useState, type ReactNode } from 'react'
import { useReducedMotion } from '@/hooks/useReducedMotion'
import { cn } from '@/lib/utils'

/**
 * Reveals its children once they scroll into view.
 *
 * Design ported from the Aurea project. Respects prefers-reduced-motion:
 * users who ask for less motion get the content immediately, not a fade.
 */
export function ScrollReveal({
  children,
  className,
  delay = 0,
  direction = 'up',
  as: Tag = 'div',
}: {
  children: ReactNode
  className?: string
  delay?: number
  /** `li` inside a list, so the list keeps valid markup (ol > li). */
  as?: 'div' | 'li'
  /** `start`/`end` follow the writing direction; `left`/`right` alias them. */
  direction?: 'up' | 'down' | 'start' | 'end' | 'left' | 'right' | 'fade'
}) {
  const [isVisible, setIsVisible] = useState(false)
  const ref = useRef<HTMLDivElement & HTMLLIElement>(null)
  const reduced = useReducedMotion()

  useEffect(() => {
    if (reduced !== false) {
      // Unknown or reduced: show it, never leave content hidden behind a
      // preference we have not resolved yet.
      setIsVisible(true)
      return
    }

    const node = ref.current
    if (!node) return

    let timer: number | undefined
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            timer = window.setTimeout(() => setIsVisible(true), delay)
            observer.disconnect()
          }
        })
      },
      { threshold: 0.1, rootMargin: '50px' },
    )

    observer.observe(node)
    return () => {
      observer.disconnect()
      if (timer) window.clearTimeout(timer)
    }
  }, [delay, reduced])

  // `start`/`end` follow the writing direction, so a reveal that slides in
  // from the reading edge would do so in an RTL language too. `left`/`right` are kept as
  // aliases for the same logical sides rather than as physical ones.
  const hidden = {
    up: 'translate-y-8 opacity-0',
    down: '-translate-y-8 opacity-0',
    start: 'ltr:-translate-x-8 rtl:translate-x-8 opacity-0',
    end: 'ltr:translate-x-8 rtl:-translate-x-8 opacity-0',
    left: 'ltr:-translate-x-8 rtl:translate-x-8 opacity-0',
    right: 'ltr:translate-x-8 rtl:-translate-x-8 opacity-0',
    fade: 'opacity-0',
  }[direction]

  return (
    <Tag
      ref={ref}
      className={cn(
        'transition-[opacity,transform] duration-700 ease-out-strong motion-reduce:transition-none',
        isVisible ? 'translate-x-0 translate-y-0 opacity-100' : hidden,
        className,
      )}
    >
      {children}
    </Tag>
  )
}
