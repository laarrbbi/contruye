export type Role = 'student' | 'admin'
// The one list of locales lives with i18n (it mirrors config/locales.php).
export type { Locale } from '@/i18n'
import type { Locale } from '@/i18n'

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: Role
  locale: Locale
  /** Where the learner is building — partner lists default to it. */
  province_id: number | null
  province?: { id: number; code: number; name: string } | null
  created_at: string
}

export interface Course {
  id: number
  slug: string
  title: string
  description: string
  price_eur: number
  status: 'draft' | 'published'
  cover_image: string | null
  modules?: CourseModule[]
  lessons_count?: number
  total_duration_seconds?: number
  is_enrolled?: boolean
  progress_percent?: number
  /** Running promotion, so catalog cards show the same price as everywhere else. */
  campaign?: { label: string; percent_off: number; ends_at: string } | null
}

export interface CourseModule {
  id: number
  course_id: number
  order: number
  title: string
  lessons?: Lesson[]
}

export interface Lesson {
  id: number
  module_id: number
  order: number
  title: string
  duration_seconds: number
  is_free_preview: boolean
  attachments: Attachment[]
  is_completed?: boolean
  last_position_seconds?: number
}

export interface Attachment {
  name: string
  url?: string
}

/**
 * A row of GET /enrollments — the student dashboard. One entry per course with
 * the access summary (full access vs. how many modules are owned) and progress
 * over the lessons the student can actually watch.
 */
export interface Enrollment {
  id: number
  course: {
    id: number
    slug: string
    title: string
    cover_image: string | null
    progress_percent: number
  }
  full_access: boolean
  owned_modules_count: number
  total_modules_count: number
  expires_at: string | null
  created_at: string
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
