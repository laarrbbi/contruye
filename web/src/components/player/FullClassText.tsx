import { useTranslation } from 'react-i18next'
import { BookOpenText } from 'lucide-react'
import type { LearnModule } from '@/api/learn'
import { cn } from '@/lib/utils'

/**
 * "Le cours complet" — the whole class in one place: the module's
 * introductory text followed by every one of its sections in order.
 *
 * Shown at the bottom of each lesson of the module, so a student reading any
 * section can also read the full class without navigating away. The section
 * they are currently on is highlighted for orientation.
 *
 * Only rendered for modules the student owns: locked modules come back from
 * the API with empty key_points, so no paid text can leak through here.
 */
export function FullClassText({
  module,
  currentLessonId,
}: {
  module: LearnModule
  currentLessonId: number | null
}) {
  const { t } = useTranslation()

  const hasBody = module.lessons.some(
    (lesson) => lesson.key_points.length > 0 || (lesson.content ?? '') !== '',
  )

  if (!module.description && !hasBody) return null

  return (
    <section className="rounded-lg border">
      <div className="flex items-center gap-2 border-b px-4 py-3">
        <BookOpenText className="h-4 w-4 shrink-0 text-primary" />
        <h2 className="text-sm font-semibold">{t('player.fullClass')}</h2>
      </div>

      <div className="space-y-6 px-4 py-4">
        {/* Module heading + its introductory text */}
        <div className="space-y-2">
          <h3 className="text-base font-bold leading-snug text-primary">
            {t('player.moduleLabel', { number: module.order })} — <bdi>{module.title}</bdi>
          </h3>
          {module.description && (
            <p className="bidi-auto whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
              {module.description}
            </p>
          )}
        </div>

        {/* Every section of the module, in order */}
        {module.lessons.map((lesson) => {
          const isCurrent = lesson.id === currentLessonId
          const points = lesson.key_points ?? []

          if (points.length === 0 && !lesson.content) return null

          return (
            <article
              key={lesson.id}
              aria-current={isCurrent ? 'true' : undefined}
              className={cn(
                'space-y-2 border-s-2 ps-4',
                isCurrent ? 'border-primary' : 'border-border',
              )}
            >
              <h4
                className={cn(
                  'bidi-auto text-sm font-semibold leading-snug',
                  isCurrent ? 'text-primary' : 'text-foreground',
                )}
              >
                {lesson.title}
                {isCurrent && (
                  <span className="ms-2 align-middle text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    {t('player.currentSection')}
                  </span>
                )}
              </h4>

              {lesson.content && (
                <p className="bidi-auto whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                  {lesson.content}
                </p>
              )}

              {points.length > 0 && (
                <ul className="space-y-2">
                  {points.map((point, index) => (
                    <li key={index} className="flex gap-2.5 text-sm leading-relaxed">
                      <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-muted-foreground/60" aria-hidden />
                      <span className="bidi-auto">{point}</span>
                    </li>
                  ))}
                </ul>
              )}
            </article>
          )
        })}
      </div>
    </section>
  )
}
