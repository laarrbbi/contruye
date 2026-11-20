import { useCallback, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import type { LearnData } from '@/api/learn'
import { CourseReferencesContext, lessonNumber, type CourseReference } from './references'

/**
 * Inside the course, «Lección 4.2» or «Módulo 9» in a text opens that place:
 * a lesson by the number its title carries, a module by its first lesson.
 * Without a provider (a page with no outline) the references read as plain
 * text.
 */
export function CourseReferencesProvider({ data, children }: { data: LearnData | undefined; children: ReactNode }) {
  const navigate = useNavigate()

  const open = useCallback(
    (target: CourseReference) => {
      if (!data) return false
      const lesson = target.lesson
        ? data.modules.flatMap((module) => module.lessons).find((l) => lessonNumber(l.title) === target.lesson)
        : data.modules.find((module) => module.kind === 'phase' && module.order === target.module)?.lessons[0]
      if (!lesson) return false
      navigate(`/learn/${data.course.slug}/lessons/${lesson.id}`)
      return true
    },
    [data, navigate],
  )

  return <CourseReferencesContext.Provider value={open}>{children}</CourseReferencesContext.Provider>
}
