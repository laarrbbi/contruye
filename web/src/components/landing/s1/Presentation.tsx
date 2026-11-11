import { useEffect, useRef, useState, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { Check } from 'lucide-react'
import type { CourseDetail } from '@/api/courses'
import type { HeroCta } from '@/components/landing/kit/cta'
import { PriceTag } from '@/components/pricing/PriceTag'
import { BoutonOr, Feuillage, Mots } from '@/components/landing/kit/pieces'
import { between, useScrollProgress, vars } from '@/components/landing/kit/tools'
import { useMediaNow } from '@/hooks/useMediaNow'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { paymentMethodList } from '@/lib/payments'
import { phaseModules } from '@/lib/modules'
import { cn, formatNumber } from '@/lib/utils'

/** Paper and night lay their content out on the same grid, so that the two copies of the pitch coincide. */
const GRID = 'container relative flex h-full flex-col justify-center pb-8 pt-20 lg:grid lg:grid-cols-12 lg:items-center lg:gap-x-14'
const ART = 'relative z-20 mx-auto aspect-square w-[min(100%,56svh)] lg:aspect-[8/7] lg:w-full'
const CAPTION = 'etiquette mt-5 text-center lg:text-start'

/**
 * The picture is wider than its frame: the frame looks at the middle of the
 * residence's two blocks. The picture is a stock photo, labelled as such
 * under it (landing.s1.caption, docs/credits-photos.md): CONSTRUYE shows no
 * project as the instructor's until he supplies one (constitution, I).
 */
const LOOK = 'object-cover object-[52%_50%]'

interface Props {
  course: CourseDetail | undefined
  cta: HeroCta
  /** False while the intro plays over the page: the entrance waits for it. */
  awake: boolean
  /** Whether the section may slide under the header (nothing stands above it). */
  flush: boolean
}

/**
 * S1, the presentation: from the drawing to the real thing.
 *
 * On paper, the pitch and his residence in pencil. With the scroll the
 * drawing takes its colours and becomes the picture it was drawn from, then
 * the night spreads from that picture over the whole screen. The picture
 * stands alone: nothing is laid over it.
 *
 * The scroll is one number, p, from 0 to 1 over the pinned stage. Without
 * motion (prefers-reduced-motion) nothing is pinned: the coloured picture
 * stands on the paper.
 */
export function Presentation({ course, cta, awake, flush }: Props) {
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const stage = useRef<HTMLDivElement>(null)
  const frame = useRef<HTMLDivElement>(null)
  const section = useRef<HTMLElement>(null)
  const [tone, setTone] = useState<'paper' | 'night'>('paper')

  const track = useScrollProgress<HTMLDivElement>((p) => {
    const box = stage.current
    if (!box) return
    const iris = between(p, 0.46, 0.88)
    box.style.setProperty('--photo', between(p, 0.05, 0.4).toFixed(3))
    box.style.setProperty('--croquis', (1 - between(p, 0.3, 0.52)).toFixed(3))
    box.style.setProperty('--iris', iris.toFixed(3))
    box.style.setProperty('--halo', between(p, 0.56, 0.86).toFixed(3))
    box.dataset.iris = iris === 0 ? 'closed' : iris === 1 ? 'open' : 'moving'
    // The night reaches the header's links (on the picture's side) first.
    setTone(iris > 0.3 ? 'night' : 'paper')
  }, !still)

  // The iris opens from the middle of the picture.
  useEffect(() => {
    const box = stage.current
    const art = frame.current
    if (still || !box || !art) return
    const place = () => {
      const picture = art.getBoundingClientRect()
      const around = box.getBoundingClientRect()
      box.style.setProperty('--cx', `${(picture.left - around.left + picture.width / 2).toFixed(0)}px`)
      box.style.setProperty('--cy', `${(picture.top - around.top + picture.height / 2).toFixed(0)}px`)
    }
    place()
    window.addEventListener('resize', place)
    return () => window.removeEventListener('resize', place)
  }, [still, wide])

  // The header reads the ground it stands on from the page (SalesPage).
  useEffect(() => {
    section.current?.dispatchEvent(new CustomEvent('tonechange', { bubbles: true }))
  }, [tone])

  return (
    <section
      ref={section}
      id="presentation"
      aria-labelledby="s1-titre"
      data-tone={tone}
      className={cn('relative bg-creme-100 text-plume', flush && '-mt-[calc(4rem+1px)]', !awake && 'dort')}
      style={vars({ '--doux': 'rgb(20 26 34 / 0.72)', '--accent': '#8F5A0E', '--trait': 'rgb(20 26 34 / 0.16)' })}
    >
      {/* On a phone the pitch has the first screen to itself, and the drawing is pinned after it. */}
      {!wide && (
        <div className="relative z-[1] overflow-hidden">
          <Feuillage className="-start-24 -top-28 h-[34rem] w-[34rem] opacity-[0.13]" />
          <div className="container relative pb-8 pt-24">
            <Pitch course={course} cta={cta} />
          </div>
        </div>
      )}

      {/* On a phone the pinned stage starts under the end of the pitch, so the
          top of the drawing shows on the first screen. */}
      <div ref={track} className={cn(!still && !wide && '-mt-[10svh]')} style={still ? undefined : { height: wide ? '250svh' : '210svh' }}>
        <div
          ref={stage}
          data-iris="closed"
          className={cn('relative overflow-hidden', still ? 'py-12 lg:min-h-[100svh]' : 'sticky top-0 h-[100svh]')}
          style={still ? vars({ '--photo': 1, '--croquis': 0.3 }) : undefined}
        >
          {wide && <Feuillage className="-start-28 -top-36 h-[52rem] w-[52rem] opacity-[0.16]" />}
          {/* Three long lines towards the picture: the sheet is a drawing board. */}
          <svg
            aria-hidden
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            className="entree pointer-events-none absolute inset-0 hidden h-full w-full [mask-image:linear-gradient(to_right,transparent_42%,#000_66%)] lg:block rtl:-scale-x-100"
            style={vars({ '--d': '900ms' })}
          >
            {['M0 6 L100 47', 'M0 104 L100 66', 'M0 73 L100 73'].map((d) => (
              <path key={d} d={d} fill="none" stroke="#5B6470" strokeOpacity="0.3" strokeWidth="1" vectorEffect="non-scaling-stroke" />
            ))}
          </svg>

          <div className={GRID}>
            {wide && <Pitch course={course} cta={cta} />}
            <div className="lg:col-span-6">
              <Art frame={frame} />
              <Caption shown={still ? 1 : 'calc(var(--photo, 0) * (1 - var(--iris, 0)))'} />
            </div>
          </div>

          {/* The night, opening from the picture. It carries a light copy of
              the pitch at the very same place, so the words change colour
              exactly where the night's edge passes. Nothing in it is reachable. */}
          {!still && (
            <div
              aria-hidden
              data-nosnippet
              {...{ inert: '' }}
              className="iris absolute inset-0 z-10 bg-[radial-gradient(120%_90%_at_70%_40%,#12253F,#0B1A2E_45%,#070F1C)] text-creme-100"
              style={vars({ '--doux': 'rgb(246 242 234 / 0.74)', '--accent': '#E8A33D', '--focus': '#F6F2EA', '--trait': 'rgb(246 242 234 / 0.2)' })}
            >
              <div className="s1-halo absolute -start-[12%] -top-[30%] h-[70vmax] w-[70vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.3),transparent_62%)]" />
              <div className="s1-halo absolute -bottom-[38%] -end-[14%] h-[78vmax] w-[78vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.2),transparent_60%)] [animation-delay:-8s]" />
              <div className={GRID}>
                {wide && <Pitch course={course} cta={cta} copy />}
                <div className="lg:col-span-6">
                  <div className={cn(ART, 'invisible')} />
                  <Caption shown={1} />
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </section>
  )
}

/** What the picture is, said plainly under it: a stock photo, not a project of the course. */
function Caption({ shown }: { shown: number | string }) {
  const { t } = useTranslation()
  return (
    <p className={cn(CAPTION, 'text-[color:var(--doux)]')} style={{ opacity: shown }}>
      {t('landing.s1.caption')}
    </p>
  )
}

/** The pitch: what it is, the button and the price, how to pay, three figures. */
function Pitch({ course, cta, copy = false }: { course: CourseDetail | undefined; cta: HeroCta; copy?: boolean }) {
  const { t, i18n } = useTranslation()
  const lang = i18n.language
  // The page has one title: the night's copy of it is only a picture of it.
  const Title = copy ? 'p' : 'h1'
  // Prices are in euros, VAT included: said under the figure, as the checkout says it.
  const euro = course ? t('course.pricing.vatIncluded') : null
  // The size of the course, counted from it: its phases, its lessons, and the
  // 52 provinces of Anexo A (one entry each: 50 provinces, Ceuta and Melilla).
  const figures = course
    ? [
        { value: formatNumber(phaseModules(course.modules).length, lang), label: t('landing.s1.phases') },
        { value: formatNumber(course.lessons_count, lang), label: t('landing.s1.lessons') },
        { value: formatNumber(52, lang), label: t('landing.s1.provinces') },
      ]
    : []
  // The title carries the search words first ("Curso de promoción
  // inmobiliaria en España"), then the promise: the first part reads as a
  // smaller line above, so the large line stays as short as the design's.
  const [lead, promise] = splitTitle(t('landing.heroV2.title'))

  return (
    <div className="lg:col-span-6">
      <p className="etiquette entree flex items-center gap-3 text-[color:var(--doux)]" style={vars({ '--d': '80ms' })}>
        <span aria-hidden className="h-px w-8 bg-[color:var(--accent)]" />
        {t('landing.s1.eyebrow')}
      </p>
      <Title
        id={copy ? undefined : 's1-titre'}
        className="titre mt-5 text-balance text-[clamp(2.5rem,min(1.1rem+4.1vw,9.5svh),4.5rem)]"
      >
        {lead && <span className="mb-3 block text-[0.5em] leading-tight text-[color:var(--doux)]">{lead}</span>}
        <Mots text={promise} delay={180} />
      </Title>
      <p className="entree mt-[clamp(1rem,2.6svh,1.75rem)] max-w-[31rem] text-[1.0625rem] leading-relaxed text-[color:var(--doux)] lg:text-[1.1875rem]" style={vars({ '--d': '620ms' })}>
        {t('landing.hero.title')}
      </p>
      <div id={copy ? undefined : 's1-achat'} className="entree mt-[clamp(1.5rem,3.6svh,2.25rem)] flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6" style={vars({ '--d': '760ms' })}>
        <BoutonOr cta={cta} className="w-full sm:w-auto">
          {t(cta.label)}
        </BoutonOr>
        {course && (
          <div>
            <PriceTag course={course} dark={copy} />
            {euro && <span className="mt-1.5 block text-sm text-[color:var(--doux)]">{euro}</span>}
          </div>
        )}
      </div>
      <p className="entree mt-4 flex items-start gap-2.5 text-sm text-[color:var(--doux)]" style={vars({ '--d': '860ms' })}>
        <Check aria-hidden className="mt-0.5 h-4 w-4 shrink-0 text-[color:var(--accent)]" strokeWidth={1.75} />
        {paymentMethodList(t, { set: 'short' })}
      </p>

      {/* Three figures on a line: no box, no shade, only a rule above. */}
      <dl className="entree mt-[clamp(1.5rem,3.8svh,2.5rem)] flex max-w-[31rem] gap-x-8 border-t border-[color:var(--trait)] pt-5 sm:gap-x-12" style={vars({ '--d': '960ms' })}>
        {figures.map((figure) => (
          <div key={figure.label} className="flex flex-col-reverse">
            <dt className="etiquette mt-1.5 text-[color:var(--doux)]">{figure.label}</dt>
            <dd className="text-2xl font-semibold leading-none tabular-nums lg:text-[1.75rem]" dir="ltr">
              {figure.value}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

/**
 * A residence: the pencil drawing made from the picture
 * (scripts/make-sketches.mjs) and the picture itself under it, alone, with
 * the light it throws on the night. --croquis, --photo and --halo come from
 * the stage.
 */
function Art({ frame }: { frame: RefObject<HTMLDivElement> }) {
  const { t } = useTranslation()
  return (
    <div ref={frame} className={ART}>
      {/* The light the picture throws on the night around it. */}
      <div
        aria-hidden
        className="absolute -inset-[16%] -z-10 rounded-full bg-[radial-gradient(closest-side,rgb(59_111_216/0.55),rgb(232_163_61/0.16)_62%,transparent)] blur-2xl"
        style={{ opacity: 'var(--halo, 0)' }}
      />
      <div
        className="absolute inset-0 overflow-hidden rounded-[1.5rem] shadow-[0_2px_4px_rgb(31_58_95/0.1),0_24px_48px_-14px_rgb(31_58_95/0.35),0_70px_120px_-36px_rgb(31_58_95/0.5)] lg:rounded-[1.75rem]"
        style={{ opacity: 'var(--photo, 0)' }}
      >
        <img src="/photos/residencia-ilustracion.webp" alt={t('landing.s1.alt')} decoding="async" className={cn('h-full w-full', LOOK)} />
      </div>
      <img
        src="/croquis/residencia.webp"
        alt=""
        aria-hidden
        decoding="async"
        className={cn('absolute inset-0 h-full w-full [mask-image:radial-gradient(ellipse_56%_62%_at_50%_52%,#000_66%,transparent_100%)]', LOOK)}
        style={{ opacity: 'var(--croquis, 1)' }}
      />
    </div>
  )
}

/** "Curso de …: de la idea …" → ["Curso de …:", "de la idea …"]; a title with no colon is all promise. */
function splitTitle(title: string): [string | null, string] {
  const at = title.search(/[:：]\s/)
  if (at < 0) return [null, title]
  return [title.slice(0, at + 1), title.slice(at + 1).trim()]
}
