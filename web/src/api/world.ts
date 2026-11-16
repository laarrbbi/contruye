import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { PlaceKey } from '@/components/world3d/places'

/** A phase as the introduction's «Las 12 fases» names it: its short name and what comes out of it. */
export interface WorldPhase {
  name: string
  outcome: string
}

export interface WorldOverviewModule {
  id: number
  order: number
  title: string
  is_locked: boolean
  end: string[]
  dusk: number
  /** How many 3D steps the module has (0: not scripted yet). */
  steps: number
  phase: WorldPhase | null
}

/** What stands on the plot after each module, which modules have their 3D script, each one's phase. */
export interface WorldOverview {
  start: string[]
  modules: WorldOverviewModule[]
}

export interface WorldTax {
  code: string | null
  name: string
  /** A fraction (0.07 = 7 %), the rate in force; null where the platform keeps none (ICIO…). */
  rate: number | null
  /** ITP and AJD: the student's autonomous community. */
  region: string | null
  /** The lesson's own words on this tax («la paga el vendedor»). */
  note: string | null
}

/** A row of the module's «Dónde ir» table, cell by cell, as the course writes it. */
export interface WorldWhere {
  institution_html: string | null
  ask_html: string | null
  get_html: string | null
  plazo_html: string | null
}

export interface WorldStep {
  key: string
  lesson: { id: number; number: string; title: string; has_video: boolean } | null
  title: string
  /** The lesson's own words, rendered on the server, with the reader's fingerprint. */
  quote_html: string | null
  place: PlaceKey
  documents: string[]
  people: string[]
  figures: string[]
  plazo: string | null
  /** A quote that is a row of a table, under that table's own header. */
  quote_rows: { label: string; value_html: string | null }[] | null
  where: WorldWhere[]
  /** The student's own offices (Anexo A); null when the step has some but the student has no province. */
  offices: { name: string; purpose: string | null }[] | null
  links: { name: string; url: string }[]
  /** The lesson's «Errores típicos». */
  warning_html: string | null
  taxes: WorldTax[]
  checklist: { id: number; order: number; text_html: string | null; checked: boolean }[]
  adds: string[]
  removes: string[]
  show: string[]
  emit: ('paper' | 'tax' | 'stamp')[]
  cutaway: boolean
}

export interface WorldModule {
  module: { id: number; order: number; title: string; description_html: string | null; quiz_id: number | null }
  /** The student's province, for «en tu provincia». */
  province: string | null
  start: string[]
  start_dusk: number
  end: string[]
  region: { name: string; itp: number; ajd: number } | null
  content_locale: string
  steps: WorldStep[]
  checklist_total: number
  /** Every item of the module with the student's tick (the steps name most of them, not all). */
  checklist: { id: number; checked: boolean }[]
}

export function useWorldOverview(slug: string | undefined) {
  return useQuery({
    queryKey: ['world', slug],
    enabled: Boolean(slug),
    queryFn: async () => (await api.get(`/courses/${slug}/world`)).data.data as WorldOverview,
  })
}

export function useWorldModule(slug: string | undefined, moduleId: number | null) {
  return useQuery({
    queryKey: ['world', slug, moduleId],
    enabled: Boolean(slug && moduleId),
    retry: false,
    queryFn: async () => (await api.get(`/courses/${slug}/modules/${moduleId}/world`)).data.data as WorldModule,
  })
}

export interface WorldLesson {
  id: number
  number: string
  title: string
  /** Index of its first step, and how many it has. */
  first: number
  count: number
}

/** The module's lessons, in the order its steps visit them. */
export function lessonsOf(steps: WorldStep[]): WorldLesson[] {
  const lessons: WorldLesson[] = []
  steps.forEach((step, i) => {
    if (!step.lesson) return
    const last = lessons[lessons.length - 1]
    if (last && last.id === step.lesson.id) last.count++
    else lessons.push({ id: step.lesson.id, number: step.lesson.number, title: step.lesson.title, first: i, count: 1 })
  })
  return lessons
}

/** «Lección 1.2 — Análisis urbanístico: qué puedes construir» → «Análisis urbanístico: qué puedes construir». */
export function lessonName(title: string): string {
  const at = title.search(/\s[—–-]\s/)
  return at >= 0 ? title.slice(at + 3).trim() : title
}

/** What stands at a given step: the module's start, with every step's additions and removals up to it. */
export function piecesAt(world: WorldModule, index: number): string[] {
  if (index >= world.steps.length) return world.end
  const set = new Set(world.start)
  for (let i = 0; i <= index && i < world.steps.length; i++) {
    for (const key of world.steps[i].adds) set.add(key)
    for (const key of world.steps[i].removes) set.delete(key)
  }
  if (index >= 0) for (const key of world.steps[index].show) set.add(key)
  return [...set]
}
