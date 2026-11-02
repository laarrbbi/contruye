import { useEffect, useRef, type CSSProperties } from 'react'

/** Custom properties for a `style`, typed. */
export const vars = (values: Record<`--${string}`, string | number>) => values as CSSProperties

export const clamp = (value: number) => Math.min(1, Math.max(0, value))
/** 0 before `from`, 1 after `to`, eased in between. */
export const between = (p: number, from: number, to: number) => {
  const t = clamp((p - from) / (to - from))
  return t * t * (3 - 2 * t)
}

// One passive scroll listener for the whole page; every section that follows
// the scroll is called once per frame from it, never once per scroll event.
const followers = new Set<() => void>()
let pending = 0
const run = () => {
  pending = 0
  followers.forEach((follower) => follower())
}
const wake = () => {
  if (!pending) pending = requestAnimationFrame(run)
}
function follow(follower: () => void) {
  if (followers.size === 0) {
    window.addEventListener('scroll', wake, { passive: true })
    window.addEventListener('resize', wake)
  }
  followers.add(follower)
  return () => {
    followers.delete(follower)
    if (followers.size === 0) {
      window.removeEventListener('scroll', wake)
      window.removeEventListener('resize', wake)
      cancelAnimationFrame(pending)
      pending = 0
    }
  }
}

/**
 * How far the scroll has carried a tall element past the screen: 0 when its
 * top reaches the top of the screen, 1 when its bottom reaches the bottom.
 * `onProgress` is called at most once per frame, and only while it changes.
 */
export function useScrollProgress<T extends HTMLElement>(onProgress: (p: number, node: T) => void, active = true) {
  const ref = useRef<T>(null)
  const handler = useRef(onProgress)
  handler.current = onProgress
  useEffect(() => {
    const node = ref.current
    if (!node || !active) return
    let last = -1
    const measure = () => {
      const rect = node.getBoundingClientRect()
      const distance = rect.height - window.innerHeight
      const p = distance > 0 ? clamp(-rect.top / distance) : 0
      if (p === last) return
      last = p
      handler.current(p, node)
    }
    measure()
    return follow(measure)
  }, [active])
  return ref
}

/**
 * How far an element has travelled through the screen: 0 when its top comes
 * up to the lower part of the screen, 1 when its bottom gets there. For
 * drawings that trace themselves as they are read.
 */
export function useScrollThrough<T extends HTMLElement>(onProgress: (p: number, node: T) => void, active = true) {
  const ref = useRef<T>(null)
  const handler = useRef(onProgress)
  handler.current = onProgress
  useEffect(() => {
    const node = ref.current
    if (!node || !active) return
    let last = -1
    const measure = () => {
      const rect = node.getBoundingClientRect()
      // Complete a little before the element's end reaches the lower part of
      // the screen: its last piece is fully there while it is still being read.
      const line = window.innerHeight * 0.82
      const p = clamp((line - rect.top) / Math.max(1, rect.height * 0.86))
      if (p === last) return
      last = p
      handler.current(p, node)
    }
    measure()
    return follow(measure)
  }, [active])
  return ref
}

/**
 * How far an element has come up the screen: 0 when its top is at the bottom
 * of the screen, 1 when it reaches the top.
 */
export function useScrollEntry<T extends HTMLElement>(onProgress: (p: number, node: T) => void, active = true) {
  const ref = useRef<T>(null)
  const handler = useRef(onProgress)
  handler.current = onProgress
  useEffect(() => {
    const node = ref.current
    if (!node || !active) return
    let last = -1
    const measure = () => {
      const p = clamp(1 - node.getBoundingClientRect().top / window.innerHeight)
      if (p === last) return
      last = p
      handler.current(p, node)
    }
    measure()
    return follow(measure)
  }, [active])
  return ref
}

/**
 * Marks an element `data-vu="true"` the first time it comes into view. Until
 * then its entrances (index.css: .mot, .entree, .trace, .masque) are held.
 */
export function useVu<T extends HTMLElement>(share = 0.18) {
  const ref = useRef<T>(null)
  useEffect(() => {
    const node = ref.current
    if (!node) return
    node.dataset.vu = 'false'
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return
        node.dataset.vu = 'true'
        observer.disconnect()
      },
      { threshold: share, rootMargin: '0px 0px -8% 0px' },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [share])
  return ref
}
