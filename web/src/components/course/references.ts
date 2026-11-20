import { createContext, useContext } from 'react'

/**
 * The course text names other lessons and modules («Lección 4.2», «Módulo
 * 9»); the API turns each into a link that carries only its number
 * (`a.ref-curso`, data-lesson / data-module). CourseReferencesProvider turns
 * that number into a place in the student's outline.
 */
export type CourseReference = { lesson?: string; module?: number }

export const CourseReferencesContext = createContext<((target: CourseReference) => boolean) | null>(null)

/** «Lección 4.2 — Autorizaciones…», «Lesson F.3 — …» → "4.2", "F.3". */
export function lessonNumber(title: string): string | null {
  return /(?:^|\s)(\d{1,2}\.\d{1,2}|F\.\d{1,2})(?=\s|$)/.exec(title)?.[1] ?? null
}

/** Opens a referenced lesson or module; null outside the course (plain text there). */
export function useCourseReferences() {
  return useContext(CourseReferencesContext)
}
