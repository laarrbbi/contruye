/**
 * Whether the cinematic intro (S0) plays. It is a welcome for a first visit
 * to the front door, and nothing else:
 *
 * - once per browser (remembered), never for someone who comes back;
 * - never on a direct link: an address with a #section or a ?query, or a
 *   visit that began on another screen (checkout, a lesson, sign-in);
 * - never for someone who asked for reduced motion, who is saving data, or
 *   whose connection or device would turn it into a wait.
 *
 * `?intro=1` plays it again on demand (for a demonstration); `?intro=0` holds it.
 */
const KEY = 'construye:intro'
/** The address this visit began on: the intro belongs to that first screen only. */
const LANDED_ON = typeof window === 'undefined' ? '' : window.location.pathname
let played = false

interface Hints {
  connection?: { saveData?: boolean; effectiveType?: string }
  deviceMemory?: number
}

function seen(): boolean {
  try {
    return window.localStorage.getItem(KEY) !== null
  } catch {
    // No storage (private mode, blocked): the visit cannot be remembered, so
    // the intro would greet this visitor every time. Better never.
    return true
  }
}

export function rememberIntro() {
  played = true
  try {
    window.localStorage.setItem(KEY, new Date().toISOString())
  } catch {
    /* Nothing to do: `played` covers this page load. */
  }
}

export function shouldPlayIntro(): boolean {
  if (typeof window === 'undefined' || played) return false
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false
  const params = new URLSearchParams(window.location.search)
  const forced = params.get('intro')
  if (forced === '0') return false
  try {
    if (document.createElement('canvas').getContext('webgl2') === null) return false
  } catch {
    return false
  }
  if (forced === '1') return true
  if (window.location.hash || window.location.search) return false
  if (window.location.pathname !== LANDED_ON) return false
  if (seen()) return false
  const hints = navigator as Navigator & Hints
  if (hints.connection?.saveData) return false
  if (hints.connection?.effectiveType && hints.connection.effectiveType !== '4g') return false
  if (hints.deviceMemory !== undefined && hints.deviceMemory < 4) return false
  return true
}
