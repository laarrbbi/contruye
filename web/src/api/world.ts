import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { PlaceKey } from '@/components/world3d/places'

/** What stands on the plot after each module, and which modules have their 3D script. Geometry only. */
export interface WorldOverview {
  start: string[]
  modules: { id: number; order: number; title: string; is_locked: boolean; end: string[]; dusk: number; steps: number }[]
}

export interface WorldTax {
  code: string | null
  name: string
  /** A fraction (0.07 = 7 %), the rate in force; null where the platform keeps none (ICIO…). */
  rate: number | null
  /** ITP and AJD: the student's autonomous community. */
  region: string | null
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
  taxes: WorldTax[]
  checklist: { id: number; order: number; text_html: string | null; checked: boolean }[]
  adds: string[]
  removes: string[]
  show: string[]
  emit: ('paper' | 'tax' | 'stamp')[]
  cutaway: boolean
}

export interface WorldModule {
  module: { id: number; order: number; title: string }
  start: string[]
  start_dusk: number
  end: string[]
  region: { name: string; itp: number; ajd: number } | null
  content_locale: string
  steps: WorldStep[]
  checklist_total: number
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
