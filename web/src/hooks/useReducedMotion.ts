import { useMediaQuery } from './useMediaQuery'

/**
 * True when the visitor asked their OS for reduced motion.
 * Starts as `null` so callers can avoid mounting animations during SSR/first
 * paint, then resolves after mount and follows later changes to the setting.
 */
export function useReducedMotion(): boolean | null {
  return useMediaQuery('(prefers-reduced-motion: reduce)')
}
