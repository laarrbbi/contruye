import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CircleCheck, ClipboardList, Lock, PlayCircle } from 'lucide-react'
import type { LearnData, LearnModule } from '@/api/learn'
import { Progress } from '@/components/ui/progress'
import { cn, formatDuration } from '@/lib/utils'

interface PlayerSidebarProps {
  data: LearnData
  currentLessonId: number | null
  /** Called after a lesson link is clicked (used to close the mobile panel). */
  onNavigate?: () => void
}

function ModuleSection({
  module,
  index,
  slug,
  currentLessonId,
  onNavigate,
}: {
  module: LearnModule
  index: number
  slug: string
  currentLessonId: number | null
  onNavigate?: () => void
}) {
  const { t } = useTranslation()
  const completedCount = module.lessons.filter((lesson) => lesson.is_completed).length
  const percent = module.lessons.length > 0 ? Math.round((completedCount / module.lessons.length) * 100) : 0

  return (
    <section className={cn(module.is_locked && 'bg-secondary/30')}>
      <div className="space-y-2 px-4 py-3">
        <div className="flex items-center justify-between gap-2">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {t('player.moduleLabel', { number: index + 1 })}
          </p>
          {module.is_locked && (
            <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
              <Lock className="h-3 w-3" />
              {t('player.locked')}
            </span>
          )}
        </div>
        <h3 className="bidi-auto text-sm font-semibold leading-snug">{module.title}</h3>
        {!module.is_locked && (
          <div className="flex items-center gap-2">
            <Progress value={percent} className="h-1.5 flex-1" aria-label={module.title} />
            <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
              {completedCount}/{module.lessons.length}
            </span>
          </div>
        )}
      </div>

      <ul>
        {module.lessons.map((lesson) => {
          const isCurrent = lesson.id === currentLessonId

          // Locked lessons (in an unowned module, not a free preview) are teased
          // but not navigable — no link, no playback. The lock says they are
          // closed; the title stays readable (it was 2.7:1), since showing
          // what the rest of the course holds is the point.
          if (lesson.is_locked) {
            return (
              <li key={lesson.id}>
                <div className="flex items-center gap-3 px-4 py-2.5 text-sm text-muted-foreground">
                  <Lock className="h-4 w-4 shrink-0" aria-label={t('player.locked')} />
                  <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                  <span className="shrink-0 text-xs tabular-nums">
                    {formatDuration(lesson.duration_seconds, t)}
                  </span>
                </div>
              </li>
            )
          }

          return (
            <li key={lesson.id}>
              <Link
                to={`/learn/${slug}/lessons/${lesson.id}`}
                onClick={onNavigate}
                aria-current={isCurrent ? 'true' : undefined}
                className={cn(
                  // Every row reserves the marker's 2px, so opening a lesson
                  // recolours its edge instead of shifting its text.
                  'flex items-center gap-3 border-s-2 px-4 py-2.5 text-sm transition-colors hover:bg-secondary',
                  isCurrent
                    ? 'border-primary bg-secondary font-medium text-primary'
                    : 'border-transparent text-foreground/80',
                )}
              >
                {lesson.is_completed ? (
                  <CircleCheck className="h-4 w-4 shrink-0 text-success" aria-label={t('player.completed')} />
                ) : (
                  <PlayCircle
                    className={cn('h-4 w-4 shrink-0', isCurrent ? 'text-primary' : 'text-muted-foreground')}
                  />
                )}
                <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                  {formatDuration(lesson.duration_seconds, t)}
                </span>
              </Link>
            </li>
          )
        })}
      </ul>

      {module.is_locked && (
        <div className="px-4 pb-3">
          <Link
            to={`/checkout/${slug}?modules=${module.id}`}
            onClick={onNavigate}
            className="flex items-center justify-center gap-1.5 rounded-md border border-primary/40 bg-background px-3 py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary/5"
          >
            <Lock className="h-3.5 w-3.5" />
            {t('player.unlockModule')}
          </Link>
        </div>
      )}
    </section>
  )
}

/**
 * Course navigation: overall progress on top, then modules with a mini
 * progress bar and their lessons. Used as the desktop sidebar and inside the
 * mobile slide-over.
 */
export function PlayerSidebar({ data, currentLessonId, onNavigate }: PlayerSidebarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex h-full flex-col">
      <div className="space-y-2 border-b px-4 py-4">
        <h2 className="bidi-auto text-sm font-bold leading-snug">{data.course.title}</h2>
        <Progress value={data.course.progress_percent} aria-label={t('player.courseProgress')} />
        <p className="text-xs text-muted-foreground">
          {t('player.progressPercent', { percent: data.course.progress_percent })}
        </p>
        <Link
          to={`/learn/${data.course.slug}/checklists`}
          onClick={onNavigate}
          className="mt-1 flex items-center gap-2 rounded-md border border-primary/30 px-3 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/5"
        >
          <ClipboardList className="h-4 w-4 shrink-0" />
          {t('checklist.pageTitle')}
        </Link>
      </div>

      <nav className="flex-1 divide-y overflow-y-auto" aria-label={t('player.contents')}>
        {data.modules.map((module, index) => (
          <ModuleSection
            key={module.id}
            module={module}
            index={index}
            slug={data.course.slug}
            currentLessonId={currentLessonId}
            onNavigate={onNavigate}
          />
        ))}
      </nav>
    </div>
  )
}
