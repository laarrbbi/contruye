import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Mots } from '@/components/landing/kit/pieces'
import { trace, venue } from '@/components/landing/kit/moments'
import { Lecture } from '@/components/landing/kit/scroll'
import { between, useScrollProgress, useVu, vars } from '@/components/landing/kit/tools'
import { EDITO, H2, H3, TEXTE } from '@/components/landing/kit/type'
import { useMediaNow } from '@/hooks/useMediaNow'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import type { CourseDetail } from '@/api/courses'
import { phaseModules } from '@/lib/modules'
import { cn, formatNumber } from '@/lib/utils'
import { Cuestionario, Hoja, Lecciones } from './Dessins'

/** A figure that counts up once, when its turn comes. */
function Compteur({ to, prefix = '', running, lang }: { to: number; prefix?: string; running: boolean; lang: string }) {
  const still = usePrefersReducedMotion()
  const [value, setValue] = useState(still ? to : 0)
  const done = useRef(still)
  useEffect(() => {
    if (!running || done.current) return
    done.current = true
    let frame = 0
    let start: number | undefined
    const step = (time: number) => {
      start ??= time
      const t = Math.min(1, (time - start) / 1700)
      setValue(Math.round((1 - Math.pow(1 - t, 3)) * to))
      if (t < 1) frame = requestAnimationFrame(step)
    }
    frame = requestAnimationFrame(step)
    return () => cancelAnimationFrame(frame)
  }, [running, to])
  return (
    <span dir="ltr" className="tabular-nums">
      {prefix}
      {formatNumber(value, lang)}
    </span>
  )
}

/** Whether an element has been on screen: for the chapters that follow one another without a pinned stage. */
function useSeen<T extends HTMLElement>(share = 0.45) {
  const ref = useRef<T>(null)
  const [seen, setSeen] = useState(false)
  useEffect(() => {
    const node = ref.current
    if (!node) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return
        setSeen(true)
        observer.disconnect()
      },
      { threshold: share },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [share])
  return [ref, seen] as const
}

const FIGURE = 'titre text-[clamp(4.75rem,1.6rem+13.5vw,13rem)] !leading-[0.9]'
const SCRIM = 'absolute inset-0 bg-[linear-gradient(to_top,rgb(7_15_28/0.94),rgb(7_15_28/0.5)_44%,rgb(7_15_28/0.3)_72%,rgb(7_15_28/0.62))]'
/** Without a pinned stage, things arrive one after the other once their chapter is seen. */
const later = (on: boolean, delay: number): { className: string; style: CSSProperties } => ({
  className: cn('transition-[opacity,transform] duration-[900ms] ease-out-strong', on ? 'opacity-100' : 'translate-y-5 opacity-0'),
  style: { transitionDelay: on ? `${delay}ms` : '0ms' },
})

/**
 * A chapter that is a photograph: full screen, slowly drawing nearer. Its
 * words come in turn: the figure, which counts; its label; then the sentence.
 * `at` gives each its moment on a pinned stage (shares of --p); without it
 * they follow one another once the chapter is seen. The two photographs are
 * stock pictures, not a project of the course (docs/credits-photos.md): each
 * says so, quietly, in its bottom corner.
 */
function Plan({
  name,
  alt,
  head,
  label,
  figure,
  children,
  on,
  at,
  credit,
}: {
  name: string
  alt: string
  credit: string
  head?: ReactNode
  label: string
  figure: ReactNode
  children: string
  on: boolean
  at?: { figure: [number, number]; label: [number, number]; sentence: [number, number] }
}) {
  const flow = [later(on, 250), later(on, 900), later(on, 1300)]
  return (
    <>
      <picture>
        <source media="(max-width: 767px)" srcSet={`/photos/${name}-haut.webp`} />
        <img src={`/photos/${name}-plein.webp`} alt={alt} loading="lazy" decoding="async" className="kenburns absolute inset-0 h-full w-full object-cover" />
      </picture>
      <div aria-hidden className={SCRIM} />
      <div className="container relative flex h-full flex-col justify-between pb-[max(6.5rem,10svh)] pt-24 lg:pb-[11svh] lg:pt-28">
        <div>{head}</div>
        <div className="relative">
          <p className={cn(FIGURE, !at && flow[0].className)} style={at ? venue(...at.figure, 30) : flow[0].style}>
            {figure}
          </p>
          <p className={cn('etiquette mt-5 text-or', !at && flow[1].className)} style={at ? venue(...at.label) : flow[1].style}>
            {label}
          </p>
          <p className={cn(EDITO, 'mt-6 max-w-[34ch] text-creme-100', !at && flow[2].className)} style={at ? undefined : flow[2].style}>
            {at ? <Lecture text={children} from={at.sentence[0]} to={at.sentence[1]} /> : children}
          </p>
        </div>
      </div>
      <p className="absolute bottom-[5.25rem] end-4 text-[0.6875rem] tracking-wide text-creme-100/45 lg:bottom-4 lg:end-6 lg:text-xs">{credit}</p>
    </>
  )
}

/**
 * What else is ready on the first day, as three line drawings that trace
 * themselves one after the other, each with its figure, counted from the
 * course where it can be: the lessons, the sheets of the professional's
 * model (its specification: 17 sheets, 30 795 formulas) and the assessment
 * questions. The third promise closes the chapter.
 */
function Bilan({ on, pinned, course }: { on: boolean; pinned: boolean; course: CourseDetail | undefined }) {
  const { t, i18n } = useTranslation()
  const box = useVu<HTMLDivElement>(0.3)
  const items = [
    { Drawing: Lecciones, value: course?.lessons_count ?? 0, label: t('landing.parcours.lessons'), at: [0.68, 0.76] as const },
    { Drawing: Hoja, value: 17, label: t('landing.parcours.sheets'), at: [0.76, 0.84] as const },
    ...((course?.questions_count ?? 0) > 0
      ? [{ Drawing: Cuestionario, value: course?.questions_count ?? 0, label: t('landing.parcours.questions'), at: [0.84, 0.92] as const }]
      : []),
  ]
  return (
    <>
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(110%_80%_at_70%_30%,#12253F,#0B1A2E_50%,#070F1C)]" />
      <div ref={box} className="container relative flex h-full flex-col justify-center pb-24 pt-24 lg:pb-12">
        <dl className="grid gap-y-10 sm:grid-cols-3 sm:gap-x-8 lg:gap-x-14">
          {items.map(({ Drawing, value, label, at }, index) => {
            const caption = later(on, 1500 + index * 700)
            return (
              <div key={label}>
                <Drawing className="mx-auto w-full max-w-[13rem] sm:mx-0 sm:max-w-[17rem]" line={pinned ? trace(at[0], at[1]) : 'trace'} />
                <div className={cn('mt-5 flex items-baseline gap-4', !pinned && caption.className)} style={pinned ? venue(at[1] - 0.02, at[1] + 0.02) : caption.style}>
                  <dd className="titre text-[clamp(3.25rem,2rem+3.6vw,5.5rem)] !leading-none" dir="ltr">
                    {formatNumber(value, i18n.language)}
                  </dd>
                  <dt className="etiquette text-or">{label}</dt>
                </div>
              </div>
            )
          })}
        </dl>
        {/* The third promise: whether a development pays, before the plot is bought. */}
        <p className={cn(EDITO, 'mt-10 max-w-[40ch] text-creme-100 lg:mt-14', !pinned && later(on, 3600).className)} style={pinned ? venue(0.94, 0.98) : later(on, 3600).style}>
          {t('landing.parcours.p3')}
        </p>
      </div>
    </>
  )
}

/**
 * S2, what is already done and waits on the first day, told as a documentary.
 * Night.
 *
 * Three chapters, each a full screen: the phases, the provinces, then what
 * else the course holds as three line drawings. Nothing is all
 * there at once. On a wide screen the stage is pinned, each chapter opens
 * over the one before like a mask lifting, and the scroll brings the figure,
 * its label and its sentence word by word; a thin bar on the side says where
 * one is. On a phone, or without motion, the chapters follow one another,
 * each a screen high, and their words arrive once the chapter is seen.
 */
function Chapitres({ course }: { course: CourseDetail | undefined }) {
  const { t, i18n } = useTranslation()
  const lang = i18n.language
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const pinned = wide && !still
  const stage = useRef<HTMLDivElement>(null)
  const [reached, setReached] = useState(0)
  const [first, firstSeen] = useSeen<HTMLDivElement>()
  const [second, secondSeen] = useSeen<HTMLDivElement>()
  const [third, thirdSeen] = useSeen<HTMLDivElement>(0.25)

  const track = useScrollProgress<HTMLDivElement>((p) => {
    const box = stage.current
    if (!box) return
    box.style.setProperty('--p', p.toFixed(4))
    box.style.setProperty('--o2', between(p, 0.3, 0.36).toFixed(3))
    box.style.setProperty('--o3', between(p, 0.62, 0.68).toFixed(3))
    box.style.setProperty('--c1', Math.min(1, p / 0.33).toFixed(3))
    box.style.setProperty('--c2', Math.min(1, Math.max(0, (p - 0.33) / 0.32)).toFixed(3))
    box.style.setProperty('--c3', Math.min(1, Math.max(0, (p - 0.65) / 0.33)).toFixed(3))
    // The counters start when their figure arrives.
    setReached(p > 0.38 ? 2 : p > 0.05 ? 1 : 0)
  }, pinned)

  // Counted from the course: its phases (Financials comes on top of them).
  const phases = course ? phaseModules(course.modules).length : 0
  const head = (
    <h2 id="s2-titre" className={cn(H3, 'max-w-[20ch]')}>
      <Mots text={t('landing.parcours.title')} />
    </h2>
  )
  const OVER = 'absolute inset-0 overflow-hidden'

  if (pinned) {
    return (
      <div ref={track} className="h-[620svh]">
        <div ref={stage} className="sticky top-0 h-[100svh] overflow-hidden" style={vars({ '--p': 0, '--o2': 0, '--o3': 0, '--c1': 0, '--c2': 0, '--c3': 0 })}>
          <div className={OVER}>
            <Plan
              name="illustration-residence"
              alt={t('landing.parcours.residenceAlt')}
              credit={t('landing.parcours.illustration')}
              head={head}
              on
              label={t('landing.parcours.phases')}
              figure={<Compteur key={phases} to={phases} running={reached >= 1} lang={lang} />}
              at={{ figure: [0.04, 0.08], label: [0.1, 0.13], sentence: [0.14, 0.27] }}
            >
              {t('landing.parcours.p1')}
            </Plan>
          </div>
          <div className={cn(OVER, '[clip-path:inset(calc((1_-_var(--o2))*100%)_0_0_0)]')}>
            <Plan
              name="illustration-chantier"
              alt={t('landing.parcours.chantierAlt')}
              credit={t('landing.parcours.illustration')}
              on
              label={t('landing.parcours.provinces')}
              figure={<Compteur to={52} running={reached >= 2} lang={lang} />}
              at={{ figure: [0.37, 0.41], label: [0.43, 0.46], sentence: [0.47, 0.6] }}
            >
              {t('landing.parcours.p2')}
            </Plan>
          </div>
          <div className={cn(OVER, '[clip-path:inset(calc((1_-_var(--o3))*100%)_0_0_0)]')}>
            <Bilan on pinned course={course} />
          </div>
          {/* Where one is among the three chapters. */}
          <div aria-hidden className="absolute start-5 top-1/2 flex -translate-y-1/2 flex-col gap-2">
            {(['--c1', '--c2', '--c3'] as const).map((fill) => (
              <span key={fill} className="block h-12 w-px overflow-hidden bg-creme-100/25">
                <span className="block h-full w-full origin-top bg-or" style={{ transform: `scaleY(var(${fill}))` }} />
              </span>
            ))}
          </div>
        </div>
      </div>
    )
  }

  return (
    <>
      <div ref={first} className="relative h-[100svh] overflow-hidden">
        <Plan name="illustration-residence" alt={t('landing.parcours.residenceAlt')} credit={t('landing.parcours.illustration')} head={head} on={firstSeen} label={t('landing.parcours.phases')} figure={<Compteur key={phases} to={phases} running={firstSeen} lang={lang} />}>
          {t('landing.parcours.p1')}
        </Plan>
      </div>
      <div ref={second} className="relative h-[100svh] overflow-hidden">
        <Plan name="illustration-chantier" alt={t('landing.parcours.chantierAlt')} credit={t('landing.parcours.illustration')} on={secondSeen} label={t('landing.parcours.provinces')} figure={<Compteur to={52} running={secondSeen} lang={lang} />}>
          {t('landing.parcours.p2')}
        </Plan>
      </div>
      <div ref={third} className="relative min-h-[100svh] overflow-hidden">
        <Bilan on={thirdSeen} pinned={false} course={course} />
      </div>
    </>
  )
}

/**
 * Why now, staged as a question and its answer: the question alone, large, in
 * the middle of the screen; then its answer; then why that matters; then the
 * three things he can now say, one at a time; then the last line in amber.
 * Pinned on a wide screen, the scroll setting the pace. On a phone or without
 * motion, the same order, each piece arriving as it comes on screen.
 */
function Pourquoi() {
  const { t } = useTranslation()
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const pinned = wide && !still
  const stage = useRef<HTMLDivElement>(null)
  const flow = useVu<HTMLDivElement>()
  // The copy holds the question and its answer in one sentence, split by a dash.
  const [question, answer] = t('landing.whyNow.title').split(/\s+[—–]\s+/)
  const points = ['k1', 'k2', 'k3'] as const
  const track = useScrollProgress<HTMLDivElement>((p) => stage.current?.style.setProperty('--p', p.toFixed(4)), pinned)
  const halo = (
    <div aria-hidden className="pointer-events-none absolute inset-0">
      <div className="halo absolute -start-[20%] top-[-20%] h-[70vmax] w-[70vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.2),transparent_62%)]" />
      <div className="halo absolute -end-[18%] bottom-[-30%] h-[60vmax] w-[60vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.12),transparent_60%)] [animation-delay:-7s]" />
    </div>
  )

  if (pinned) {
    // While it stands alone the question sits lower and larger; it rises to its place as the answer comes.
    const settled = 'clamp(0, calc((var(--p, 0) - 0.14) / 0.1), 1)'
    return (
      <div ref={track} className="relative h-[460svh]">
        <div ref={stage} className="sticky top-0 flex h-[100svh] items-center overflow-hidden" style={vars({ '--p': 0 })}>
          {halo}
          <div className="container relative text-center">
            <h3 className={cn(H2, 'mx-auto max-w-[18ch] origin-top')} style={{ transform: `translate3d(0, calc((1 - ${settled}) * 20svh), 0) scale(calc(1 + (1 - ${settled}) * 0.28))` }}>
              <Lecture text={question} from={0} to={0.1} />
            </h3>
            {answer && (
              <p className={cn(H2, 'mx-auto mt-3 max-w-[20ch] text-or')} style={venue(0.2, 0.27)}>
                {answer}
              </p>
            )}
            {/* Held back until the answer is given: unread, its words would show through the question. */}
            <p className={cn(EDITO, 'mx-auto mt-10 max-w-[40ch] text-creme-100')} style={venue(0.27, 0.33, 0)}>
              <Lecture text={t('landing.whyNow.quote')} from={0.33} to={0.55} />
            </p>
            <ul className="mx-auto mt-12 grid max-w-[60rem] grid-cols-3 gap-x-10 text-start">
              {points.map((key, index) => (
                <li key={key} className={cn(TEXTE, 'border-t border-creme-100/20 pt-5 !leading-snug text-creme-100/90')} style={venue(0.6 + index * 0.08, 0.65 + index * 0.08)}>
                  {t(`landing.whyNow.${key}`)}
                </li>
              ))}
            </ul>
            <p className="mt-10 text-xl font-medium text-or" style={venue(0.87, 0.93)}>
              {t('landing.whyNow.close')}
            </p>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="relative overflow-hidden">
      {halo}
      <div ref={flow} className="container relative py-24">
        <h3 className={cn(H2, 'max-w-[18ch]')}>
          <Mots text={question} />
        </h3>
        {answer && (
          <p className={cn(H2, 'entree mt-2 max-w-[20ch] text-or')} style={vars({ '--d': '700ms' })}>
            {answer}
          </p>
        )}
        <p className={cn(EDITO, 'entree mt-10 max-w-[40ch]')} style={vars({ '--d': '1100ms' })}>
          {t('landing.whyNow.quote')}
        </p>
        <ul className="mt-10 grid gap-x-8 sm:grid-cols-3">
          {points.map((key, index) => (
            <li key={key} className={cn(TEXTE, 'entree border-t border-creme-100/20 py-5 !leading-snug text-creme-100/90')} style={vars({ '--d': `${1500 + index * 350}ms` })}>
              {t(`landing.whyNow.${key}`)}
            </li>
          ))}
        </ul>
        <p className="entree mt-6 text-lg font-medium text-or" style={vars({ '--d': '2700ms' })}>
          {t('landing.whyNow.close')}
        </p>
      </div>
    </div>
  )
}

/** S2: what is ready on the first day, then why now. */
export function Parcours({ course }: { course: CourseDetail | undefined }) {
  return (
    <section id="parcours" data-tone="night" aria-labelledby="s2-titre" className="relative bg-sombre-950 text-creme-100" style={vars({ '--focus': '#F6F2EA' })}>
      <Chapitres course={course} />
      <Pourquoi />
    </section>
  )
}
