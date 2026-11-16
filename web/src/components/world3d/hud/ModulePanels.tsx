import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, CheckCircle2, ChevronRight, ClipboardCheck, FileText, GraduationCap, Landmark, Lock, Play, RotateCcw } from 'lucide-react'
import { lessonName, type WorldLesson, type WorldModule, type WorldOverviewModule, type WorldPhase } from '@/api/world'
import { MarkdownContent } from '@/components/MarkdownContent'
import { cn } from '@/lib/utils'

const PRIMARY =
  'inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006FFE] px-4 text-[0.95rem] font-bold text-white transition-colors hover:bg-[#005FE0] active:scale-[0.99]'
const SECONDARY = 'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border bg-white px-4 text-sm font-semibold transition-colors hover:bg-muted'

function Eyebrow({ children }: { children: React.ReactNode }) {
  return <p className="text-xs font-bold uppercase tracking-wider text-[#006FFE]">{children}</p>
}

function Outcome({ phase }: { phase: WorldPhase | null }) {
  const { t } = useTranslation()
  if (!phase) return null
  return (
    <p className="mt-2.5 flex gap-2 rounded-xl bg-[#006FFE]/[0.06] px-3 py-2.5 text-sm leading-snug">
      <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-[#006FFE]" aria-hidden />
      <span>
        <span className="font-bold text-plume">{t('world3d.outcomeLabel')}: </span>
        {phase.outcome}
      </span>
    </p>
  )
}

/** The first time: what to click, in four lines. Also behind the «?» button. */
export function HowItWorks({ onDone, className }: { onDone?(): void; className?: string }) {
  const { t } = useTranslation()
  return (
    <div className={cn('rounded-xl bg-plume p-4 text-white', className)}>
      <p className="text-[0.95rem] font-bold">{t('world3d.howTitle')}</p>
      <ol className="mt-2.5 space-y-2 text-sm leading-snug text-white/85">
        {[1, 2, 3, 4].map((n) => (
          <li key={n} className="flex gap-2.5">
            <span aria-hidden className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-bold">
              {n}
            </span>
            {t(`world3d.how${n}`)}
          </li>
        ))}
      </ol>
      {onDone && (
        <button type="button" onClick={onDone} className="mt-3.5 inline-flex min-h-10 items-center rounded-lg bg-white/15 px-3.5 text-sm font-semibold hover:bg-white/25">
          {t('world3d.howDone')}
        </button>
      )}
    </div>
  )
}

/**
 * Before the first step: which module this is, what the course says it is for (its «Objetivo», «Duración» and «Resultado»), and its
 * lessons as ways in. «Empezar» (or «Seguir en el paso N»), and «Reproducir»
 * to let it run on its own.
 */
export function ModuleStartPanel({
  world,
  total,
  lessons,
  resumeAt,
  onStart,
  onPlay,
}: {
  world: WorldModule
  total: number
  lessons: WorldLesson[]
  resumeAt: number | null
  onStart(index: number): void
  onPlay(): void
}) {
  const { t } = useTranslation()
  const steps = world.steps.length

  return (
    <>
      <div className="w3d-enter min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
        <Eyebrow>{t('world3d.moduleOf', { number: world.module.order, total })}</Eyebrow>
        <h2 id="w3d-step-title" tabIndex={-1} className="mt-1 text-[1.35rem] font-bold leading-tight text-plume outline-none">
          {world.module.title}
        </h2>
        {world.module.description_html && <MarkdownContent html={world.module.description_html} className="mt-3 text-[0.92rem] leading-relaxed [&_p]:my-1" />}
        <p className="mt-3 text-sm font-medium text-foreground/70">
          {t('world3d.moduleCounts', {
            steps,
            lessons: lessons.length,
            tasks: world.checklist_total,
          })}
        </p>

        <h3 className="mt-5 text-[0.95rem] font-bold text-plume">{t('world3d.lessonsTitle')}</h3>
        <ol className="mt-2 space-y-1.5">
          {lessons.map((lesson) => (
            <li key={lesson.id}>
              <button
                type="button"
                onClick={() => onStart(lesson.first)}
                className="group flex min-h-14 w-full items-center gap-3 rounded-xl border bg-white px-3 py-2 text-start transition-colors hover:border-[#006FFE]/40 hover:bg-[#006FFE]/[0.03]"
              >
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#006FFE]/10 text-sm font-bold tabular-nums text-[#006FFE]">{lesson.number}</span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-semibold leading-snug">{lessonName(lesson.title)}</span>
                  <span className="block text-xs text-muted-foreground">{t('world3d.stepsCount', { count: lesson.count })}</span>
                </span>
                <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100" aria-hidden />
              </button>
            </li>
          ))}
        </ol>
      </div>
      <footer className="shrink-0 space-y-2 border-t border-foreground/[0.08] px-4 py-3 sm:px-5">
        {resumeAt !== null ? (
          <>
            <button type="button" onClick={() => onStart(resumeAt)} className={PRIMARY}>
              {t('world3d.resumeAt', { index: resumeAt + 1, total: steps })}
              <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
            </button>
            <button type="button" onClick={() => onStart(0)} className={SECONDARY}>
              {t('world3d.restart')}
            </button>
          </>
        ) : (
          <button type="button" onClick={() => onStart(0)} className={PRIMARY}>
            {t('world3d.start')}
            <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
          </button>
        )}
        <button type="button" onClick={onPlay} className={SECONDARY}>
          <Play className="h-4 w-4 text-[#006FFE]" aria-hidden />
          {t('world3d.playModule')}
        </button>
      </footer>
    </>
  )
}

/**
 * After the last step: what the student has gathered (papers, taxes), the
 * checklist and the module's test, and the next module with what comes out
 * of it.
 */
export function ModuleEndPanel({
  slug,
  world,
  total,
  phase,
  papers,
  taxes,
  checklistDone,
  next,
  onSelectModule,
  onRestart,
}: {
  slug: string
  world: WorldModule
  total: number
  phase: WorldPhase | null
  papers: string[]
  taxes: string[]
  checklistDone: number
  next: WorldOverviewModule | null
  onSelectModule(moduleId: number): void
  onRestart(): void
}) {
  const { t } = useTranslation()
  const tasks = world.checklist_total

  return (
    <>
      <div className="w3d-enter min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
        <div>
          <Eyebrow>{t('world3d.moduleOf', { number: world.module.order, total })}</Eyebrow>
          <h2 id="w3d-step-title" tabIndex={-1} className="mt-1 text-[1.35rem] font-bold leading-tight text-plume outline-none">
            {t('world3d.moduleDoneTitle', { number: world.module.order })}
          </h2>
          <Outcome phase={phase} />
        </div>

        {papers.length > 0 && (
          <section>
            <h3 className="mb-2 flex items-center gap-2 text-[0.95rem] font-bold text-plume">
              <FileText className="h-4 w-4 text-[#006FFE]" aria-hidden />
              {t('world3d.papersGathered', { count: papers.length })}
            </h3>
            <ul className="flex flex-wrap gap-1.5">
              {papers.map((paper) => (
                <li key={paper} lang={world.content_locale} className="rounded-full bg-[#006FFE]/[0.08] px-3 py-1.5 text-sm font-medium text-[#0055C4]">
                  {paper}
                </li>
              ))}
            </ul>
          </section>
        )}

        {taxes.length > 0 && (
          <section>
            <h3 className="mb-2 flex items-center gap-2 text-[0.95rem] font-bold text-plume">
              <Landmark className="h-4 w-4 text-or-fonce" aria-hidden />
              {t('world3d.taxesSeen', { count: taxes.length })}
            </h3>
            <ul className="flex flex-wrap gap-1.5">
              {taxes.map((tax) => (
                <li key={tax} lang={world.content_locale} className="rounded-full bg-or/15 px-3 py-1.5 text-sm font-medium text-or-fonce">
                  {tax}
                </li>
              ))}
            </ul>
          </section>
        )}

        <section className="rounded-xl border bg-white p-3.5">
          <h3 className="flex items-center gap-2 text-[0.95rem] font-bold text-plume">
            <ClipboardCheck className="h-4 w-4 text-success" aria-hidden />
            {t('world3d.checklistTitle', { done: checklistDone, total: tasks })}
          </h3>
          <span aria-hidden className="mt-2.5 block h-1.5 overflow-hidden rounded-full bg-foreground/10">
            <span className="block h-full rounded-full bg-success" style={{ width: `${tasks ? (checklistDone / tasks) * 100 : 0}%` }} />
          </span>
          <div className="mt-3 flex flex-wrap gap-2">
            <Link to={`/learn/${slug}/modules/${world.module.id}/tareas`} className="inline-flex min-h-10 items-center gap-1.5 rounded-lg border px-3 text-sm font-semibold hover:bg-muted">
              {t('world3d.checklistComplete')}
            </Link>
            {world.module.quiz_id && (
              <Link to={`/learn/${slug}/quizzes/${world.module.quiz_id}`} className="inline-flex min-h-10 items-center gap-1.5 rounded-lg border px-3 text-sm font-semibold hover:bg-muted">
                <GraduationCap className="h-4 w-4 text-[#006FFE]" aria-hidden />
                {t('world3d.takeQuiz', { number: world.module.order })}
              </Link>
            )}
          </div>
        </section>

        {next && (
          <section className="rounded-xl bg-plume p-4 text-white">
            <p className="text-xs font-bold uppercase tracking-wider text-or">{t('world3d.nextModule')}</p>
            <p className="mt-1 text-base font-bold leading-snug">
              {t('world3d.module', { number: next.order })} · {next.phase?.name ?? next.title}
            </p>
            {next.phase && <p className="mt-1 text-sm text-white/75">{next.phase.outcome}</p>}
            <button
              type="button"
              onClick={() => onSelectModule(next.id)}
              className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg bg-white px-3.5 text-sm font-bold text-plume hover:bg-white/90"
            >
              {next.is_locked && <Lock className="h-4 w-4" aria-hidden />}
              {t('world3d.seeModule', { number: next.order })}
              <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
            </button>
          </section>
        )}
      </div>
      <footer className="shrink-0 border-t border-foreground/[0.08] px-4 py-3 sm:px-5">
        <button type="button" onClick={onRestart} className={SECONDARY}>
          <RotateCcw className="h-4 w-4" aria-hidden />
          {t('world3d.repeatModule')}
        </button>
      </footer>
    </>
  )
}

/**
 * A module whose 3D steps are not written yet, or not bought: how the
 * promotion stands once it is done, what comes out of it, and the ways on.
 */
export function ModuleSoonPanel({
  slug,
  module,
  total,
  firstScripted,
  onSelectModule,
  onTour,
}: {
  slug: string
  module: WorldOverviewModule
  total: number
  firstScripted: WorldOverviewModule | null
  onSelectModule(moduleId: number): void
  onTour(): void
}) {
  const { t } = useTranslation()

  return (
    <div className="w3d-enter min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
      <div>
        <Eyebrow>{t('world3d.moduleOf', { number: module.order, total })}</Eyebrow>
        <h2 id="w3d-step-title" tabIndex={-1} className="mt-1 text-[1.3rem] font-bold leading-tight text-plume outline-none">
          {module.title}
        </h2>
        <Outcome phase={module.phase} />
      </div>
      <p className="text-sm leading-relaxed text-foreground/75">{module.is_locked ? t('world3d.lockedText') : t('world3d.soonText')}</p>
      <div className="space-y-2">
        {module.is_locked && (
          <Link to={`/checkout/${slug}?modules=${module.id}`} className={PRIMARY}>
            <Lock className="h-4 w-4" aria-hidden />
            {t('world3d.unlock')}
          </Link>
        )}
        {firstScripted && firstScripted.id !== module.id && (
          <button type="button" onClick={() => onSelectModule(firstScripted.id)} className={module.is_locked ? SECONDARY : PRIMARY}>
            {t('world3d.backToModule', { number: firstScripted.order })}
          </button>
        )}
        <button type="button" onClick={onTour} className={SECONDARY}>
          <Play className="h-4 w-4 text-[#006FFE]" aria-hidden />
          {t('world3d.tour')}
        </button>
      </div>
    </div>
  )
}
