import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Calculator,
  CircleCheck,
  ClipboardCheck,
  ClipboardList,
  FileText,
  GraduationCap,
  Lock,
  PlayCircle,
} from 'lucide-react'
import type { LearnData, LearnModule, LearnQuizSummary } from '@/api/learn'
import { Progress } from '@/components/ui/progress'
import { moduleLabel } from '@/lib/modules'
import { cn, formatDuration } from '@/lib/utils'

/** Where the student is in the player: a lesson, a quiz, a document, Financials or the exam. */
export type PlayerLocation =
  | { lessonId: number }
  | { quizId: number }
  | { document: string }
  | { financials: true }

interface PlayerSidebarProps {
  data: LearnData
  location: PlayerLocation
  /** Called after a link is clicked (used to close the mobile panel). */
  onNavigate?: () => void
}

const rowClass = (isCurrent: boolean) =>
  cn(
    // Every row reserves the marker's 2px, so opening an item recolours its
    // edge instead of shifting its text.
    'flex items-center gap-3 border-s-2 px-4 py-2.5 text-sm transition-colors hover:bg-secondary',
    isCurrent ? 'border-primary bg-secondary font-medium text-primary' : 'border-transparent text-foreground/80',
  )

function QuizBadge({ quiz }: { quiz: LearnQuizSummary }) {
  const { t } = useTranslation()

  if (quiz.passed) {
    return <span className="shrink-0 text-xs font-medium text-success">{t('quiz.passedShort')}</span>
  }
  if (quiz.best_score !== null) {
    return (
      <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
        {quiz.best_score}/{quiz.questions_count}
      </span>
    )
  }
  return null
}

function ModuleSection({
  module,
  slug,
  location,
  onNavigate,
}: {
  module: LearnModule
  slug: string
  location: PlayerLocation
  onNavigate?: () => void
}) {
  const { t } = useTranslation()
  const completedCount = module.lessons.filter((lesson) => lesson.is_completed).length
  const percent = module.lessons.length > 0 ? Math.round((completedCount / module.lessons.length) * 100) : 0

  return (
    <section className={cn(module.is_locked && 'bg-secondary/30')}>
      <div className="space-y-2 px-4 py-3">
        <div className="flex items-center justify-between gap-2">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{moduleLabel(module, t)}</p>
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
          const isCurrent = 'lessonId' in location && lesson.id === location.lessonId

          // Locked lessons (in an unowned module, not a free preview) are teased
          // but not navigable — no link, no playback. The lock says they are
          // closed; the title stays readable, since showing what the rest of
          // the course holds is the point.
          if (lesson.is_locked) {
            return (
              <li key={lesson.id}>
                <div className="flex items-center gap-3 px-4 py-2.5 text-sm text-muted-foreground">
                  <Lock className="h-4 w-4 shrink-0" aria-label={t('player.locked')} />
                  <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                  <span className="shrink-0 text-xs tabular-nums">{formatDuration(lesson.duration_seconds, t)}</span>
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
                className={rowClass(isCurrent)}
              >
                {lesson.is_completed ? (
                  <CircleCheck className="h-4 w-4 shrink-0 text-success" aria-label={t('player.completed')} />
                ) : (
                  <PlayCircle className={cn('h-4 w-4 shrink-0', isCurrent ? 'text-primary' : 'text-muted-foreground')} />
                )}
                <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                  {formatDuration(lesson.duration_seconds, t)}
                </span>
              </Link>
            </li>
          )
        })}

        {/* Financials: the professional's template, calculated by the platform */}
        {module.kind === 'financials' && !module.is_locked && (
          <li>
            <Link
              to={`/learn/${slug}/financials`}
              onClick={onNavigate}
              aria-current={'financials' in location ? 'true' : undefined}
              className={rowClass('financials' in location)}
            >
              <Calculator className="h-4 w-4 shrink-0 text-primary" />
              <span className="flex-1 leading-snug">{t('financials.navTitle')}</span>
            </Link>
          </li>
        )}

        {/* The quiz that closes the module */}
        {module.quiz && !module.is_locked && (
          <li>
            <Link
              to={`/learn/${slug}/quizzes/${module.quiz.id}`}
              onClick={onNavigate}
              aria-current={'quizId' in location && location.quizId === module.quiz.id ? 'true' : undefined}
              className={rowClass('quizId' in location && location.quizId === module.quiz.id)}
            >
              <ClipboardCheck className={cn('h-4 w-4 shrink-0', module.quiz.passed ? 'text-success' : 'text-muted-foreground')} />
              <span className="flex-1 leading-snug">{t('quiz.navTitle')}</span>
              <QuizBadge quiz={module.quiz} />
            </Link>
          </li>
        )}
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
 * Course navigation: overall progress and the course documents on top, then
 * the modules with their lessons and quiz, Financials, and the final exam.
 * Used as the desktop sidebar and inside the mobile slide-over.
 */
export function PlayerSidebar({ data, location, onNavigate }: PlayerSidebarProps) {
  const { t } = useTranslation()
  const slug = data.course.slug
  const exam = data.course.final_exam

  return (
    <div className="flex h-full flex-col">
      <div className="space-y-2 border-b px-4 py-4">
        <h2 className="bidi-auto text-sm font-bold leading-snug">{data.course.title}</h2>
        <Progress value={data.course.progress_percent} aria-label={t('player.courseProgress')} />
        <p className="text-xs text-muted-foreground">
          {t('player.progressPercent', { percent: data.course.progress_percent })}
        </p>
        <Link
          to={`/learn/${slug}/checklists`}
          onClick={onNavigate}
          className="mt-1 flex items-center gap-2 rounded-md border border-primary/30 px-3 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/5"
        >
          <ClipboardList className="h-4 w-4 shrink-0" />
          {t('checklist.pageTitle')}
        </Link>
      </div>

      <nav className="flex-1 divide-y overflow-y-auto" aria-label={t('player.contents')}>
        {data.course.documents.length > 0 && (
          <section className="py-2">
            <p className="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t('documents.navTitle')}
            </p>
            <ul>
              {data.course.documents.map((document) => {
                const isCurrent = 'document' in location && location.document === document.slug
                return (
                  <li key={document.slug}>
                    {document.is_locked ? (
                      <div className="flex items-center gap-3 px-4 py-2 text-sm text-muted-foreground">
                        <Lock className="h-4 w-4 shrink-0" aria-label={t('player.locked')} />
                        <span className="flex-1 leading-snug">{document.title}</span>
                      </div>
                    ) : (
                      <Link
                        to={`/learn/${slug}/docs/${document.slug}`}
                        onClick={onNavigate}
                        aria-current={isCurrent ? 'true' : undefined}
                        className={cn(rowClass(isCurrent), 'py-2')}
                      >
                        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1 leading-snug">{document.title}</span>
                      </Link>
                    )}
                  </li>
                )
              })}
            </ul>
          </section>
        )}

        {data.modules.map((module) => (
          <ModuleSection key={module.id} module={module} slug={slug} location={location} onNavigate={onNavigate} />
        ))}

        {exam && (
          <section className="px-4 py-3">
            {exam.is_locked ? (
              <div className="flex items-start gap-3 rounded-md border border-dashed px-3 py-2.5 text-sm text-muted-foreground">
                <GraduationCap className="mt-0.5 h-4 w-4 shrink-0" />
                <span className="flex-1 leading-snug">
                  <span className="block font-medium text-foreground/80">{t('exam.navTitle')}</span>
                  {t('exam.lockedHint')}
                </span>
              </div>
            ) : (
              <Link
                to={`/learn/${slug}/quizzes/${exam.id}`}
                onClick={onNavigate}
                aria-current={'quizId' in location && location.quizId === exam.id ? 'true' : undefined}
                className={cn(
                  'flex items-center gap-3 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors hover:bg-secondary',
                  'quizId' in location && location.quizId === exam.id ? 'border-primary text-primary' : 'border-border',
                )}
              >
                <GraduationCap className={cn('h-4 w-4 shrink-0', exam.passed ? 'text-success' : 'text-primary')} />
                <span className="flex-1 leading-snug">{t('exam.navTitle')}</span>
                <QuizBadge quiz={exam} />
              </Link>
            )}
          </section>
        )}
      </nav>
    </div>
  )
}
