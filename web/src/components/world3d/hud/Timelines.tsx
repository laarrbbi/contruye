import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Lock } from 'lucide-react'
import type { WorldOverview, WorldStep } from '@/api/world'
import { cn } from '@/lib/utils'

/**
 * The module's steps in a line, as a parcel's journey is tracked: done,
 * current, to come, grouped under their lesson. A tap goes to a step.
 */
export function StepTimeline({ steps, index, onSelect }: { steps: WorldStep[]; index: number; onSelect(i: number): void }) {
  const { t } = useTranslation()
  const current = useRef<HTMLButtonElement>(null)
  useEffect(() => {
    current.current?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
  }, [index])

  return (
    <ol className="w3d-fade-x flex snap-x items-start gap-0 overflow-x-auto overscroll-contain px-1 pb-1 [scrollbar-width:none]">
      {steps.map((step, i) => {
        const state = i < index ? 'done' : i === index ? 'current' : 'next'
        const firstOfLesson = i === 0 || steps[i - 1].lesson?.number !== step.lesson?.number
        return (
          <li key={step.key} className="relative flex w-[7.5rem] shrink-0 snap-center flex-col items-center sm:w-32">
            {/* The line between nodes. */}
            {i > 0 && <span aria-hidden className={cn('absolute end-1/2 top-[0.95rem] h-0.5 w-full', i <= index ? 'bg-[#006FFE]' : 'bg-foreground/10')} />}
            <button
              ref={i === index ? current : undefined}
              type="button"
              onClick={() => onSelect(i)}
              aria-current={state === 'current' ? 'step' : undefined}
              className="group relative z-10 flex flex-col items-center gap-1.5 px-1 focus-visible:outline-none"
            >
              <span
                className={cn(
                  'flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-300',
                  state === 'done' && 'border-[#006FFE] bg-[#006FFE] text-white',
                  state === 'current' && 'scale-110 border-[#006FFE] bg-white text-[#006FFE] shadow-[0_0_0_6px_rgb(0_111_254/0.15)]',
                  state === 'next' && 'border-foreground/15 bg-white text-foreground/50 group-hover:border-foreground/30',
                  'group-focus-visible:ring-2 group-focus-visible:ring-ring',
                )}
              >
                {state === 'done' ? <Check className="h-4 w-4" aria-hidden /> : i + 1}
              </span>
              <span className={cn('line-clamp-2 text-center text-[0.7rem] leading-tight', state === 'current' ? 'font-semibold text-foreground' : 'text-muted-foreground')}>{step.title}</span>
              {firstOfLesson && step.lesson && <span className="text-[0.62rem] font-semibold uppercase tracking-wider text-[#006FFE]/80">{t('world3d.lesson', { number: step.lesson.number })}</span>}
            </button>
          </li>
        )
      })}
    </ol>
  )
}

/**
 * The whole promotion in twelve segments: where this module sits, which
 * ones are built, which are locked. A tap shows that module's world.
 */
export function CourseTimeline({ overview, currentOrder, onSelect }: { overview: WorldOverview; currentOrder: number; onSelect(moduleId: number): void }) {
  const { t } = useTranslation()
  return (
    <div>
      <p className="mb-1.5 text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.promotion')}</p>
      <ol className="flex gap-1">
        {overview.modules.map((m) => (
          <li key={m.id} className="flex-1">
            <button
              type="button"
              onClick={() => onSelect(m.id)}
              title={`${t('world3d.module', { number: m.order })} · ${m.title}`}
              aria-label={`${t('world3d.module', { number: m.order })} · ${m.title}`}
              aria-current={m.order === currentOrder ? 'step' : undefined}
              className={cn(
                'relative flex h-7 w-full min-w-[1.5rem] items-center justify-center rounded-md text-[0.65rem] font-bold tabular-nums transition-colors',
                m.order < currentOrder && 'bg-[#006FFE]/80 text-white',
                m.order === currentOrder && 'bg-[#006FFE] text-white ring-2 ring-[#006FFE]/30 ring-offset-1',
                m.order > currentOrder && 'bg-foreground/[0.07] text-foreground/55 hover:bg-foreground/[0.12]',
              )}
            >
              {m.is_locked ? <Lock className="h-3 w-3" aria-hidden /> : m.order}
            </button>
          </li>
        ))}
      </ol>
    </div>
  )
}
