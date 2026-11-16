import { useTranslation } from 'react-i18next'
import { Check, Lock } from 'lucide-react'
import { lessonName, lessonsOf, type WorldOverview, type WorldOverviewModule, type WorldStep } from '@/api/world'
import { cn } from '@/lib/utils'

type Visited = ReadonlySet<number>

function stepState(i: number, index: number, visited: Visited) {
  return i === index ? 'current' : visited.has(i) ? 'done' : 'next'
}

/**
 * The module's steps as numbered dots under their lessons («Lección 1.2 ·
 * Análisis urbanístico…»): seen (ticked), current, to come. A lesson's name
 * goes to its first step, a dot to its step; the step's title is its label.
 */
export function StepDots({ steps, index, visited, onSelect }: { steps: WorldStep[]; index: number; visited: Visited; onSelect(i: number): void }) {
  const { t } = useTranslation()
  const lessons = lessonsOf(steps)

  return (
    <ol className="flex min-w-0 items-end gap-5" aria-label={t('world3d.stepsTitle')}>
      {lessons.map((lesson) => {
        const here = index >= lesson.first && index < lesson.first + lesson.count
        return (
          <li key={lesson.id} className="flex min-w-0 flex-col">
            <button
              type="button"
              onClick={() => onSelect(lesson.first)}
              title={lesson.title}
              className={cn(
                'w-0 min-w-full truncate rounded text-start text-[0.72rem] leading-tight focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                here ? 'font-bold text-plume' : 'font-semibold text-foreground/50 hover:text-foreground',
              )}
            >
              {t('world3d.lesson', { number: lesson.number })}
              <span className="font-medium"> · {lessonName(lesson.title)}</span>
            </button>
            <ol className="mt-1.5 flex items-center gap-1">
              {Array.from({ length: lesson.count }, (_, j) => {
                const i = lesson.first + j
                const step = steps[i]
                const state = stepState(i, index, visited)
                return (
                  <li key={step.key}>
                    <button
                      type="button"
                      onClick={() => onSelect(i)}
                      aria-current={state === 'current' ? 'step' : undefined}
                      aria-label={t('world3d.stepLabel', {
                        index: i + 1,
                        total: steps.length,
                        title: step.title,
                      })}
                      title={`${i + 1}. ${step.title}`}
                      className={cn(
                        "relative flex h-7 w-7 items-center justify-center rounded-full border-2 text-[0.68rem] font-bold tabular-nums transition-[transform,background-color,border-color,color] duration-200 before:absolute before:-inset-1.5 before:content-[''] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                        state === 'current' && 'scale-110 border-[#006FFE] bg-[#006FFE] text-white shadow-[0_0_0_4px_rgb(0_111_254/0.18)]',
                        state === 'done' && 'border-[#006FFE]/25 bg-[#006FFE]/10 text-[#006FFE]',
                        state === 'next' && 'border-foreground/15 bg-white text-foreground/55 hover:border-foreground/35',
                      )}
                    >
                      {state === 'done' ? <Check className="h-3.5 w-3.5" aria-hidden /> : i + 1}
                    </button>
                  </li>
                )
              })}
            </ol>
          </li>
        )
      })}
    </ol>
  )
}

function moduleLabel(t: (key: string, options?: Record<string, unknown>) => string, m: WorldOverviewModule) {
  return `${t('world3d.module', { number: m.order })} · ${m.title}${m.phase ? ` — ${m.phase.outcome}` : ''}`
}

/**
 * «Los 12 módulos del curso»: each module by its phase's name (the
 * introduction's «Las 12 fases»), the one shown filled, the ones with their 3D
 * steps marked «3D», the locked ones with a padlock. A tap shows that module.
 */
export function ModuleStrip({ overview, currentOrder, onSelect }: { overview: WorldOverview; currentOrder: number; onSelect(moduleId: number): void }) {
  const { t, i18n } = useTranslation()

  return (
    <div className="flex min-w-0 items-center gap-3">
      <p className="w-[5.5rem] shrink-0 text-[0.78rem] font-bold leading-tight text-plume">{t('world3d.modulesTitle', { count: overview.modules.length })}</p>
      <ol
        className="grid min-w-0 flex-1 gap-1"
        style={{
          gridTemplateColumns: `repeat(${overview.modules.length}, minmax(0, 1fr))`,
        }}
      >
        {overview.modules.map((m) => {
          const current = m.order === currentOrder
          const label = moduleLabel(t, m)
          return (
            <li key={m.id} className="min-w-0">
              <button
                type="button"
                onClick={() => onSelect(m.id)}
                title={label}
                aria-label={label}
                aria-current={current ? 'step' : undefined}
                className={cn(
                  'relative flex h-12 w-full flex-col items-center justify-center gap-0.5 rounded-lg px-0.5 text-center transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  current
                    ? 'bg-[#006FFE] text-white shadow-[0_8px_18px_-10px_rgb(0_111_254/0.9)]'
                    : m.is_locked
                      ? 'bg-foreground/[0.035] text-foreground/40 hover:bg-foreground/[0.07]'
                      : 'bg-foreground/[0.06] text-foreground/75 hover:bg-foreground/[0.1]',
                )}
              >
                <span className="flex items-center gap-0.5 text-[0.62rem] font-bold leading-none tabular-nums">
                  {m.is_locked && <Lock className="h-2.5 w-2.5" aria-hidden />}
                  {m.order}
                </span>
                <span lang={i18n.language} className="line-clamp-2 w-full hyphens-auto break-words text-[0.66rem] font-semibold leading-[1.1]">
                  {m.phase?.name ?? m.title}
                </span>
                {m.steps > 0 && !m.is_locked && (
                  <span aria-hidden className={cn('absolute -top-1.5 end-1 rounded px-1 text-[0.5rem] font-bold leading-[0.9rem]', current ? 'bg-or text-plume' : 'bg-[#006FFE] text-white')}>
                    3D
                  </span>
                )}
              </button>
            </li>
          )
        })}
      </ol>
    </div>
  )
}

/** The twelve modules as a list, for a phone: name, what comes out of it, state. */
export function ModuleList({ overview, currentOrder, onSelect }: { overview: WorldOverview; currentOrder: number; onSelect(moduleId: number): void }) {
  const { t } = useTranslation()

  return (
    <ol className="space-y-1.5">
      {overview.modules.map((m) => {
        const current = m.order === currentOrder
        return (
          <li key={m.id}>
            <button
              type="button"
              onClick={() => onSelect(m.id)}
              aria-current={current ? 'step' : undefined}
              className={cn('flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-start transition-colors', current ? 'bg-[#006FFE]/10 ring-1 ring-[#006FFE]/40' : 'hover:bg-muted')}
            >
              <span
                className={cn(
                  'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-bold tabular-nums',
                  current ? 'bg-[#006FFE] text-white' : 'bg-foreground/[0.06] text-foreground/70',
                )}
              >
                {m.is_locked ? <Lock className="h-4 w-4" aria-hidden /> : m.order}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block font-semibold leading-snug">
                  {t('world3d.module', { number: m.order })} · {m.phase?.name ?? m.title}
                </span>
                {m.phase && <span className="mt-0.5 block text-sm leading-snug text-muted-foreground">{m.phase.outcome}</span>}
                <span className="mt-1.5 flex flex-wrap gap-1.5 text-[0.7rem] font-semibold">
                  {current && <span className="rounded-full bg-[#006FFE] px-2 py-0.5 text-white">{t('world3d.here')}</span>}
                  {m.steps > 0 && !m.is_locked && <span className="rounded-full bg-[#006FFE]/10 px-2 py-0.5 text-[#006FFE]">{t('world3d.stepsCount', { count: m.steps })}</span>}
                  {m.steps === 0 && !m.is_locked && <span className="rounded-full bg-foreground/[0.06] px-2 py-0.5 text-foreground/60">{t('world3d.soonBadge')}</span>}
                  {m.is_locked && <span className="rounded-full bg-foreground/[0.06] px-2 py-0.5 text-foreground/60">{t('world3d.lockedBadge')}</span>}
                </span>
              </span>
            </button>
          </li>
        )
      })}
    </ol>
  )
}

/** The module's steps as a list under their lessons, for a phone. */
export function StepList({
  steps,
  index,
  visited,
  placeName,
  onSelect,
}: {
  steps: WorldStep[]
  index: number
  visited: Visited
  placeName(key: WorldStep['place']): string
  onSelect(i: number): void
}) {
  const { t } = useTranslation()

  return (
    <div className="space-y-5">
      {lessonsOf(steps).map((lesson) => (
        <section key={lesson.id}>
          <h3 className="mb-1.5 text-[0.95rem] font-bold leading-snug text-plume">
            {t('world3d.lesson', { number: lesson.number })} · <span className="font-semibold text-foreground/70">{lessonName(lesson.title)}</span>
          </h3>
          <ol className="space-y-1">
            {Array.from({ length: lesson.count }, (_, j) => {
              const i = lesson.first + j
              const step = steps[i]
              const state = stepState(i, index, visited)
              return (
                <li key={step.key}>
                  <button
                    type="button"
                    onClick={() => onSelect(i)}
                    aria-current={state === 'current' ? 'step' : undefined}
                    className={cn('flex min-h-12 w-full items-center gap-3 rounded-xl px-2.5 py-2 text-start', state === 'current' ? 'bg-[#006FFE]/10' : 'hover:bg-muted')}
                  >
                    <span
                      className={cn(
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold tabular-nums',
                        state === 'current' && 'border-[#006FFE] bg-[#006FFE] text-white',
                        state === 'done' && 'border-[#006FFE]/25 bg-[#006FFE]/10 text-[#006FFE]',
                        state === 'next' && 'border-foreground/15 text-foreground/55',
                      )}
                    >
                      {state === 'done' ? <Check className="h-4 w-4" aria-hidden /> : i + 1}
                    </span>
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold leading-snug">{step.title}</span>
                      <span className="block text-xs text-muted-foreground">{placeName(step.place)}</span>
                    </span>
                  </button>
                </li>
              )
            })}
          </ol>
        </section>
      ))}
    </div>
  )
}
