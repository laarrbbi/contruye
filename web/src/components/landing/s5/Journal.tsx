import { useEffect, useRef, useState, type PointerEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Mots } from '@/components/landing/kit/pieces'
import { useVu, vars } from '@/components/landing/kit/tools'
import { EDITO, H2 } from '@/components/landing/kit/type'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { cn } from '@/lib/utils'

/**
 * A development in the order it actually happens, from the earthworks to the
 * lit windows: one still from each of the ten licensed clips of the
 * construction loop (scripts/fotogramas-obra.sh, docs/credits-photos.md).
 * None shows a project of the course or of its instructor, and the line
 * under the ring says so.
 */
const SHOTS = [
  { src: '/photos/obra-01.webp', key: 'o1', phase: 'solar' },
  { src: '/photos/obra-02.webp', key: 'o2', phase: 'solar' },
  { src: '/photos/obra-03.webp', key: 'o3', phase: 'estructura' },
  { src: '/photos/obra-04.webp', key: 'o4', phase: 'estructura' },
  { src: '/photos/obra-05.webp', key: 'o5', phase: 'estructura' },
  { src: '/photos/obra-06.webp', key: 'o6', phase: 'obra' },
  { src: '/photos/obra-07.webp', key: 'o7', phase: 'obra' },
  { src: '/photos/obra-08.webp', key: 'o8', phase: 'fachadas' },
  { src: '/photos/obra-09.webp', key: 'o9', phase: 'acabados' },
  { src: '/photos/obra-10.webp', key: 'o10', phase: 'entrega' },
] as const
/** The figures of the housing gap, each with its source (SOURCES). */
const FACTS = ['f1', 'f2', 'f3', 'f4', 'f5'] as const
/**
 * Where the figures come from: the press's report of each study, and the
 * link says so ("via …"). The original reports are to be cited once opened.
 */
const SOURCES = [
  { key: 'sourceBbva', url: 'https://funciohouse.com/blog/viviendas-iniciadas-espana-2026-oferta-deficit-bbva-funcionario/' },
  { key: 'sourceBde', url: 'https://www.infobae.com/espana/2026/10/09/espana-no-logra-construir-las-viviendas-que-necesita-el-deficit-llegara-al-millon-en-2028-calcula-el-banco-de-espana-e-insta-a-invertir-mas/' },
  { key: 'sourceIne', url: 'https://www.infobae.com/espana/2026/06/08/el-precio-de-la-vivienda-sigue-al-alza-crece-un-129-en-el-primer-trimestre-de-2026-respecto-al-ano-anterior-segun-el-ine/' },
  { key: 'sourcePlan', url: 'https://elderecho.com/plan-estatal-de-vivienda-2026-2030' },
] as const
const site = (url: string) => new URL(url).hostname.replace(/^www\./, '')
/** Degrees between two photographs on the ring. */
const STEP = 360 / SHOTS.length
/** The ring's radius, in photograph widths: the polygon they make, plus a 9 % gap. */
const RADIUS = +((1 / (2 * Math.tan(Math.PI / SHOTS.length))) * 1.09).toFixed(3)
/** Degrees a second: slow enough to read a picture as it passes. */
const DRIFT = 3.2

/**
 * S5, the opportunity: the housing gap, over a ring of building-site
 * photographs turning by itself.
 *
 * The visitor stands at the centre of a cylinder and the photographs line its
 * wall: the ones ahead are the smallest, the ones at the edges of the screen
 * loom large and slanted. It turns slowly and endlessly, whatever the scroll
 * does; a mouse nudges it, a finger drags it. A veil of night lies on the
 * photographs so that the title and the five kinds of project can be read
 * over them; pointing at one (or touching it) lifts its veil and shows what
 * it is. The figures are the owner's, each with its source under the ring. Plain CSS 3D, no WebGL. In Arabic it turns the other way. Without
 * motion the ring stands still and can still be turned by hand.
 */
export function Journal() {
  const { t, i18n } = useTranslation()
  const still = usePrefersReducedMotion()
  const rtl = i18n.dir() === 'rtl'
  const scene = useRef<HTMLDivElement>(null)
  const ring = useRef<HTMLDivElement>(null)
  const head = useVu<HTMLDivElement>(0.4)
  const close = useVu<HTMLDivElement>()
  const [lit, setLit] = useState<number | null>(null)
  // What the frame loop reads: kept out of React so that a frame costs no render.
  const state = useRef({ angle: 0, nudge: 0, held: false, dragging: false, lastX: 0, onScreen: false })
  state.current.held = lit !== null

  useEffect(() => {
    const node = ring.current
    const box = scene.current
    if (!node || !box) return
    const s = state.current
    const paint = () => node.style.setProperty('--a', s.angle.toFixed(2))
    paint()
    const presence = new IntersectionObserver(([entry]) => (s.onScreen = entry.isIntersecting))
    presence.observe(box)
    let frame = 0
    let last = 0
    const tick = (time: number) => {
      const delta = last ? Math.min(0.05, (time - last) / 1000) : 0
      last = time
      if (s.onScreen && !s.dragging && !s.held && !still) {
        s.angle += (DRIFT + s.nudge * 9) * delta * (rtl ? 1 : -1)
        paint()
      }
      frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => {
      cancelAnimationFrame(frame)
      presence.disconnect()
    }
  }, [still, rtl])

  // A mouse leans on the ring; a finger (or a held mouse) turns it.
  const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
    const s = state.current
    if (s.dragging) {
      s.angle += (event.clientX - s.lastX) * 0.16
      s.lastX = event.clientX
      ring.current?.style.setProperty('--a', s.angle.toFixed(2))
    } else if (event.pointerType === 'mouse') {
      s.nudge = (event.clientX / window.innerWidth) * 2 - 1
    }
  }
  const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType === 'mouse') return
    state.current.dragging = true
    state.current.lastX = event.clientX
  }
  const release = () => {
    state.current.dragging = false
    state.current.nudge = 0
  }

  return (
    <section id="journal" data-tone="night" aria-labelledby="s5-titre" className="relative bg-creme-100 text-creme-100" style={vars({ '--focus': '#F6F2EA' })}>
      <div className="relative -mt-8 overflow-hidden rounded-t-[2rem] bg-sombre-950">
        <div
          ref={scene}
          onPointerMove={onPointerMove}
          onPointerDown={onPointerDown}
          onPointerUp={release}
          onPointerCancel={release}
          onPointerLeave={release}
          className="relative h-[100svh] min-h-[36rem] touch-pan-y select-none overflow-hidden [perspective:var(--pers)]"
          style={vars({ '--w': 'clamp(17rem, 40vw, 40rem)', '--pers': 'clamp(24rem, 52vw, 50rem)' })}
        >
          {/* The ring: the camera stands at its centre, the photographs line its wall. */}
          <div ref={ring} className="absolute inset-0 [transform-style:preserve-3d] [transform:translateZ(var(--pers))_rotateY(calc(var(--a,0)*1deg))]">
            {SHOTS.map((shot, index) => {
              const on = lit === index
              return (
                <button
                  key={shot.key}
                  type="button"
                  aria-pressed={on}
                  onPointerEnter={(event) => event.pointerType === 'mouse' && setLit(index)}
                  onPointerLeave={(event) => event.pointerType === 'mouse' && setLit(null)}
                  onFocus={() => setLit(index)}
                  onBlur={() => setLit(null)}
                  onClick={() => setLit(on ? null : index)}
                  className="group absolute left-1/2 top-1/2 block aspect-[3/4] w-[var(--w)] overflow-hidden sm:aspect-[4/3] rounded-[1.25rem] text-start focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-creme-100"
                  // Each stands on the wall of a cylinder whose radius fits all of them side by side, with a little gap.
                  style={{ transform: `translate(-50%, -50%) rotateY(${index * STEP}deg) translateZ(calc(var(--w) * ${-RADIUS}))` }}
                >
                  <img src={shot.src} alt={t(`landing.gallery.${shot.key}`)} loading="lazy" decoding="async" draggable={false} className="h-full w-full object-cover" />
                  {/* The veil of night, lifted from the photograph one points at. */}
                  <span aria-hidden className={cn('absolute inset-0 bg-sombre-950 transition-opacity duration-500 ease-out-strong', on ? 'opacity-0' : 'opacity-[0.58]')} />
                  <span
                    className={cn(
                      'absolute inset-x-0 bottom-0 bg-[linear-gradient(to_top,rgb(7_15_28/0.92),transparent)] p-5 pt-12 transition-[opacity,transform] duration-500 ease-out-strong',
                      on ? 'opacity-100' : 'translate-y-3 opacity-0',
                    )}
                  >
                    <span className="etiquette block text-or">{t(`landing.journal.phases.${shot.phase}`)}</span>
                    <span className="mt-1.5 block text-[0.9375rem] leading-snug text-creme-100">{t(`landing.gallery.${shot.key}`)}</span>
                  </span>
                </button>
              )
            })}
          </div>

          {/* The title and the five kinds of project, over the ring: they arrive one by one. */}
          <div ref={head} className="pointer-events-none absolute inset-0 flex items-center justify-center bg-[radial-gradient(60%_46%_at_50%_50%,rgb(7_15_28/0.82),rgb(7_15_28/0.3)_70%,transparent)]">
            <div className="container text-center">
              <h2 id="s5-titre" className={cn(H2, 'mx-auto max-w-[24ch] !text-[clamp(1.75rem,1.2rem+2vw,3.25rem)]')}>
                <Mots text={t('landing.opportunity.title')} />
              </h2>
              <ul className="mx-auto mt-8 flex max-w-[52rem] flex-wrap justify-center gap-x-8 gap-y-4">
                {FACTS.map((key, index) => (
                  <li key={key} className="entree text-center" style={vars({ '--d': `${700 + index * 320}ms` })}>
                    <span className="titre block text-[clamp(1.5rem,1.1rem+1.4vw,2.25rem)] !leading-none text-or">{t(`landing.opportunity.${key}Figure`)}</span>
                    <span className="mt-1.5 block max-w-[14rem] text-sm font-medium text-creme-100/85">{t(`landing.opportunity.${key}Label`)}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>

        <div ref={close} className="container relative pb-24 pt-4 lg:pb-36">
          <p className={cn(EDITO, 'entree max-w-[40ch] text-creme-100')}>{t('landing.opportunity.close')}</p>
          {/* Every figure's source, and how far the estimates agree. */}
          <p className="entree mt-8 max-w-[48rem] text-sm leading-relaxed text-creme-100/70" style={vars({ '--d': '250ms' })}>
            {t('landing.opportunity.caveat')}
          </p>
          <ul className="entree mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm" style={vars({ '--d': '350ms' })}>
            {SOURCES.map(({ key, url }) => (
              <li key={key}>
                <a href={url} target="_blank" rel="noopener noreferrer" className="text-creme-100/80 underline decoration-creme-100/30 underline-offset-4 hover:text-creme-100 hover:decoration-or">
                  {t(`landing.opportunity.${key}`)} · {t('landing.opportunity.via', { site: site(url) })}
                </a>
              </li>
            ))}
          </ul>
          <p className="entree mt-6 text-xs text-creme-100/55" style={vars({ '--d': '450ms' })}>{t('landing.gallery.attribution')}</p>
        </div>
      </div>
    </section>
  )
}
