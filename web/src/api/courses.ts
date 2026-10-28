import { useQuery } from '@tanstack/react-query'
import { api } from './client'

/** Public lesson row as returned by GET /courses/{slug}. */
export interface PublicLesson {
  id: number
  order: number
  title: string
  duration_seconds: number
  is_free_preview: boolean
}

/** A volume-discount step: buy `min_modules` → `percent_off` applies. */
export interface DiscountTier {
  min_modules: number
  percent_off: number
}

/**
 * A running promotion. `ends_at` is a real, fixed deadline: the same for every
 * visitor, and when it passes the discount stops and the price goes back up.
 */
export interface Campaign {
  label: string
  percent_off: number
  ends_at: string
}

export interface PublicModule {
  id: number
  order: number
  title: string
  /** Null when the module is only sold as part of full access. */
  price_eur: number | null
  /** Present for authenticated viewers who already own this module. */
  is_owned?: boolean
  lessons: PublicLesson[]
}

export interface CourseDetail {
  id: number
  slug: string
  title: string
  description: string
  price_eur: number
  module_discount_tiers: DiscountTier[]
  cover_image: string | null
  is_enrolled?: boolean
  /** True when the authenticated viewer already has full access. */
  full_access?: boolean
  /** Running promotion, or null when there is none. */
  campaign?: Campaign | null
  /** Real social proof: students with an active enrolment. */
  students_count?: number
  /** Average of APPROVED reviews — null until a genuine review lands. */
  rating?: number | null
  reviews_count?: number
  lessons_count: number
  total_duration_seconds: number
  modules: PublicModule[]
}

export function useCourse(slug: string | undefined) {
  return useQuery<CourseDetail>({
    queryKey: ['course', slug],
    enabled: !!slug,
    queryFn: async () => {
      const { data } = await api.get(`/courses/${slug}`)
      return data.data as CourseDetail
    },
  })
}

/** Signed Bunny embed URL for a lesson. */
export interface Playback {
  url: string
  expires_at: string
  watermark: string | null
}

/**
 * Fetch the signed playback URL for a lesson. 401/403 mean the viewer is not
 * allowed (not logged in / not enrolled) — surfaced via the query error.
 */
export function usePlayback(lessonId: number | null | undefined, enabled = true) {
  return useQuery<Playback>({
    queryKey: ['playback', lessonId],
    enabled: enabled && lessonId != null,
    retry: false,
    // Signed URLs expire server-side; keep them fresh-ish but avoid refetch
    // storms while a lesson is open.
    staleTime: 60_000,
    queryFn: async () => {
      const { data } = await api.get(`/lessons/${lessonId}/playback`)
      return data.data as Playback
    },
  })
}
