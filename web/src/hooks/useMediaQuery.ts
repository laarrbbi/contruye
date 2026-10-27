import { useEffect, useState } from 'react'

/**
 * Whether a CSS media query matches, following later changes.
 * Starts as `null` (unknown until mounted) so callers can hold back anything
 * expensive until the answer is in.
 */
export function useMediaQuery(query: string): boolean | null {
  const [matches, setMatches] = useState<boolean | null>(null)

  useEffect(() => {
    const list = window.matchMedia(query)
    setMatches(list.matches)

    const onChange = (event: MediaQueryListEvent) => setMatches(event.matches)
    list.addEventListener('change', onChange)
    return () => list.removeEventListener('change', onChange)
  }, [query])

  return matches
}
