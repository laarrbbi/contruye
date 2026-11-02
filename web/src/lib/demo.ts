import type { CourseDetail } from '@/api/courses'

/**
 * Demo data announces itself with a "[DEMO]" prefix (constitution, VI). The
 * showcase seeder never runs in production, but a development or preview
 * database is what the owner looks at: the sales page shows him none of it,
 * so he never takes an invented promotion or review for his own.
 */
export function isDemo(text: string | null | undefined): boolean {
  return !!text && text.trimStart().startsWith('[DEMO]')
}

/** The course without a demo promotion: its real price, no countdown. */
export function withoutDemo<T extends CourseDetail | undefined>(course: T): T {
  return course && isDemo(course.campaign?.label) ? { ...course, campaign: null } : course
}
