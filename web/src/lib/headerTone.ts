import { useSyncExternalStore } from 'react'

/**
 * What the header is standing on. The redesigned sales page opens on a night
 * sky that runs under the header: there the bar goes translucent and its text
 * white. Over its cream paper the bar is clear and its text ink. Every
 * other screen leaves it on `default`.
 *
 * A page says so with setHeaderTone and resets it when it leaves; the header
 * only listens.
 */
export type HeaderTone = 'default' | 'night' | 'paper'

let tone: HeaderTone = 'default'
const listeners = new Set<() => void>()

export function setHeaderTone(next: HeaderTone) {
  if (next === tone) return
  tone = next
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function useHeaderTone(): HeaderTone {
  return useSyncExternalStore(subscribe, () => tone)
}
