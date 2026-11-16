/**
 * Where the student left a module's 3D steps, kept in this browser only (a
 * convenience, never the record of anything): the step, the steps they have
 * seen, and the lesson it belongs to, so the lesson's «Ver en 3D» card can
 * offer to carry on. Every access is wrapped: private windows and blocked
 * storage simply start from the beginning.
 */
export interface WorldMemory {
  /** 1-based step, or 0 for the module's end. */
  paso: number
  visited: number[]
  lessonId: number | null
  lesson: string | null
  total: number
  title: string
}

const key = (slug: string, moduleId: number) => `construye.w3d.${slug}.${moduleId}`

export function readMemory(slug: string | undefined, moduleId: number | null | undefined): WorldMemory | null {
  if (!slug || !moduleId) return null
  try {
    const raw = window.localStorage.getItem(key(slug, moduleId))
    if (!raw) return null
    const value = JSON.parse(raw) as Partial<WorldMemory>
    if (typeof value.paso !== 'number' || !Array.isArray(value.visited)) return null
    return {
      paso: value.paso,
      visited: value.visited.filter((n): n is number => typeof n === 'number'),
      lessonId: typeof value.lessonId === 'number' ? value.lessonId : null,
      lesson: typeof value.lesson === 'string' ? value.lesson : null,
      total: typeof value.total === 'number' ? value.total : 0,
      title: typeof value.title === 'string' ? value.title : '',
    }
  } catch {
    return null
  }
}

export function writeMemory(slug: string | undefined, moduleId: number | null | undefined, memory: WorldMemory): void {
  if (!slug || !moduleId) return
  try {
    window.localStorage.setItem(key(slug, moduleId), JSON.stringify(memory))
  } catch {
    // Storage full or blocked: the URL still keeps the step.
  }
}

/** A flag kept once on this browser (the «Cómo funciona» card, seen). */
export function readFlag(name: string): boolean {
  try {
    return window.localStorage.getItem(`construye.w3d.${name}`) === '1'
  } catch {
    return false
  }
}

export function writeFlag(name: string): void {
  try {
    window.localStorage.setItem(`construye.w3d.${name}`, '1')
  } catch {
    // Nothing to do: the card shows again next time.
  }
}
