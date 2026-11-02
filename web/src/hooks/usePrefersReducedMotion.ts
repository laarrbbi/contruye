import { useSyncExternalStore } from 'react'

const QUERY = '(prefers-reduced-motion: reduce)'

function subscribe(onChange: () => void) {
  const list = window.matchMedia(QUERY)
  list.addEventListener('change', onChange)
  return () => list.removeEventListener('change', onChange)
}

/**
 * Whether the visitor asked for reduced motion, known on the first render.
 *
 * useReducedMotion starts as `null` and resolves after mount, which suits a
 * component that only has to hold an animation back. A page that lays itself
 * out differently without motion needs the answer before it paints, or the
 * visitor who asked for calm gets a flash of the moving layout first.
 */
export function usePrefersReducedMotion(): boolean {
  return useSyncExternalStore(subscribe, () => window.matchMedia(QUERY).matches)
}
