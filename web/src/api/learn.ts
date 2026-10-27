import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, API_URL } from './client'

export interface LearnAttachment {
  name: string
  index: number
}

export interface LearnLesson {
  id: number
  order: number
  title: string
  duration_seconds: number
  is_free_preview: boolean
  /** True for lessons in a module the student doesn't own (free previews stay unlocked). */
  is_locked: boolean
  /** Written lesson notes; null when the lesson is locked. */
  content: string | null
  /** The lesson's bullet points (empty when locked). */
  key_points: string[]
  /** Tagged blocks attached to the lesson (empty when locked). */
  blocks: LearnContentBlock[]
  /** True for the printable safety sheet (module 13.2). */
  has_reflex_sheet: boolean
  /** False while the lesson's video has not been produced yet. */
  has_video: boolean
  is_completed: boolean
  last_position_seconds: number
  attachments: LearnAttachment[]
  /** Teaser count shown for locked lessons, whose attachments array is empty. */
  attachments_count: number
}

export type PartnerCategory =
  | 'notario'
  | 'asesor_fiscal'
  | 'banco'
  | 'arquitectura'
  | 'aseguradora'
  | 'constructora'
  | 'abogado'
  | 'marketing'
  | 'otro'

/** A recommended provider for this module. Only sent for owned modules. */
export interface LearnPartner {
  id: number
  name: string
  category: PartnerCategory
  description: string | null
  website: string | null
  phone: string | null
  email: string | null
  /** Covers every province — `provinces` is then empty. */
  is_national: boolean
  provinces: { id: number; code: number; name: string }[]
  /** Why this partner matters for this specific module. */
  note: string | null
}

export type ContentBlockType = 'text' | 'legal' | 'partner_slot' | 'expert_tip'

/** A tagged block of course content (legal reference, expert tip, partner slot). */
export interface LearnContentBlock {
  id: number
  type: ContentBlockType
  body: string | null
  /** partner_slot only: which kind of provider the slot is asking for. */
  partner_category?: string | null
  /** Always sent: lets a locked slot tease what it holds without listing it. */
  partners_count?: number
  /** partner_slot only: real attached partners. Empty → show the placeholder. */
  partners?: LearnPartner[]
}

export interface LearnModule {
  id: number
  order: number
  title: string
  /** True when the student doesn't own this module — its lessons are teased but not playable. */
  is_locked: boolean
  price_dzd: number | null
  price_eur: number | null
  /** The module's introductory text. */
  description: string | null
  /** Tagged blocks attached to the module (empty when locked). */
  blocks: LearnContentBlock[]
  /** Empty for locked modules — the contacts are a paid perk. */
  partners: LearnPartner[]
  /** Always present, so a locked module can tease how many contacts it unlocks. */
  partners_count: number
  lessons: LearnLesson[]
}

export interface LearnData {
  course: {
    id: number
    slug: string
    title: string
    progress_percent: number
    full_access: boolean
    owned_modules_count: number
    total_modules_count: number
  }
  modules: LearnModule[]
  next_lesson_id: number | null
}

export function useLearn(slug: string | undefined) {
  return useQuery<LearnData>({
    queryKey: ['learn', slug],
    enabled: !!slug,
    retry: false,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}/learn`)
      return data.data as LearnData
    },
  })
}

export interface ProgressResponse {
  lesson_id: number
  last_position_seconds: number
  is_completed: boolean
}

/** Heartbeat: persist the furthest watched position for a lesson. */
export async function sendProgress(lessonId: number, positionSeconds: number) {
  const { data } = await api.post(`/lessons/${lessonId}/progress`, {
    position_seconds: positionSeconds,
  })
  return data.data as ProgressResponse
}

/**
 * Toggle a lesson's completed flag with an optimistic update of the cached
 * learn payload, then re-sync from the server (progress_percent changes too).
 */
export function useToggleComplete(slug: string | undefined) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: async ({ lessonId, completed }: { lessonId: number; completed: boolean }) => {
      const { data } = await api.post(`/lessons/${lessonId}/complete`, { completed })
      return data.data as ProgressResponse
    },
    onMutate: async ({ lessonId, completed }) => {
      await qc.cancelQueries({ queryKey: ['learn', slug] })
      const previous = qc.getQueryData<LearnData>(['learn', slug])
      if (previous) {
        qc.setQueryData<LearnData>(['learn', slug], {
          ...previous,
          modules: previous.modules.map((module) => ({
            ...module,
            lessons: module.lessons.map((lesson) =>
              lesson.id === lessonId ? { ...lesson, is_completed: completed } : lesson,
            ),
          })),
        })
      }
      return { previous }
    },
    onError: (_error, _vars, context) => {
      if (context?.previous) {
        qc.setQueryData(['learn', slug], context.previous)
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['learn', slug] })
    },
  })
}

/**
 * Absolute URL for an attachment download. Opened via browser navigation so
 * the Sanctum session cookie rides along.
 */
export function attachmentUrl(lessonId: number, index: number) {
  return `${API_URL}/api/lessons/${lessonId}/attachments/${index}`
}

/** Printable "fiche réflexe" PDF (module 13.2), same cookie-auth navigation. */
export function reflexSheetUrl(lessonId: number) {
  return `${API_URL}/api/lessons/${lessonId}/reflex-sheet`
}
