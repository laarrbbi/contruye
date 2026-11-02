import { useCallback, useEffect, useRef, useState, type CSSProperties, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { ConstruyeMark } from '@/components/brand/ConstruyeMark'
import { between, useScrollProgress, useVu, vars } from '@/components/landing/kit/tools'
import { useMediaNow } from '@/hooks/useMediaNow'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { cn } from '@/lib/utils'

/**
 * What the buyer leaves with: four cards. On their face, a picture of the
 * thing (a clean 3D still, nothing on it can be read: scripts/outils-3d.html),
 * its name and one sentence; the full description waits in a bubble. On
 * their back, navy, the CONSTRUYE mark. The Excel picture stands in for the real
 * file until the instructor sends a screenshot of it.
 */
const TOOLS = [
  { key: 'excel', img: '/outils/excel.webp', turn: -7, lift: 1.4 },
  { key: 'papers', img: '/outils/papiers.webp', turn: -2.5, lift: 0 },
  { key: 'templates', img: '/outils/plans.webp', turn: 2.5, lift: 0 },
  { key: 'subs', img: '/outils/contacts.webp', turn: 7, lift: 1.4 },
] as const
type Tool = (typeof TOOLS)[number]

/** On a phone the cards lie in a column, each a little off the line of the one before. */
const STAGGER = ['ms-0', 'ms-8', 'ms-3', 'ms-10']

/** A card turns once on its way in, half a turn: it arrives on its back and settles on its face. */
const SPIN = 180
/** Its thickness, seen when it stands edge on. */
const EDGE = '4px'

const ease = (t: number) => 1 - Math.pow(1 - t, 3)

const smooth = (t: number) => t * t * (3 - 2 * t)

/**
 * Where a card's bubble opens: beside it, towards the middle of the row, on a
 * wide screen; under it on a phone. Never above, where the words are.
 */
type Side = 'end' | 'start' | 'below'
const BULLE = 'outils-bulle'
const GAP = 16

/**
 * The full description of the open card, in one bubble laid after all the
 * cards, so that it is drawn over every one of them: inside a card, Chrome
 * drew it under the next card, whatever their z-index. It is placed by
 * measuring the open card, again whenever the page scrolls or resizes.
 */
function Bulle({ frame, items, open, side }: { frame: RefObject<HTMLElement | null>; items: RefObject<(HTMLLIElement | null)[]>; open: number | null; side(index: number): Side }) {
  const { t, i18n } = useTranslation()
  const rtl = i18n.dir() === 'rtl'
  const [place, setPlace] = useState<{ left: number; top: number; arrow: 'left' | 'right' | 'top'; index: number } | null>(null)

  // After the commit, once every ref (the frame is its parent) is attached again.
  useEffect(() => {
    if (open === null) return
    const index = open
    const measure = () => {
      const box = frame.current?.getBoundingClientRect()
      const card = (items.current ?? [])[index]?.getBoundingClientRect()
      if (!box || !card) return
      const width = Math.min(256, window.innerWidth * 0.84)
      const where = side(index)
      if (where === 'below') {
        setPlace({ left: card.left + card.width / 2 - width / 2 - box.left, top: card.bottom + GAP - box.top, arrow: 'top', index })
        return
      }
      // 'end' is to the right in French and English, to the left in Arabic.
      const right = (where === 'end') !== rtl
      setPlace({ left: (right ? card.right + GAP : card.left - GAP - width) - box.left, top: card.top + card.height / 2 - 14 - box.top, arrow: right ? 'left' : 'right', index })
    }
    measure()
    let tick = 0
    const again = () => {
      cancelAnimationFrame(tick)
      tick = requestAnimationFrame(measure)
    }
    window.addEventListener('scroll', again, { passive: true })
    window.addEventListener('resize', again)
    return () => {
      cancelAnimationFrame(tick)
      window.removeEventListener('scroll', again)
      window.removeEventListener('resize', again)
    }
  }, [open, frame, items, side, rtl])

  // The last card opened, so that the bubble keeps its words while it fades.
  const tool = TOOLS[open ?? place?.index ?? 0]
  const arrow = place?.arrow ?? 'left'
  return (
    <div
      id={BULLE}
      role="region"
      aria-live="polite"
      aria-label={t(`landing.toolkit.${tool.key}Title`)}
      className={cn(
        'pointer-events-none absolute z-40 w-[min(16rem,84vw)] rounded-2xl bg-sombre-800/95 p-4 text-[0.875rem] leading-relaxed text-creme-100/90 shadow-[0_20px_50px_-12px_rgb(0_0_0/0.7)] ring-1 ring-creme-100/10 backdrop-blur',
        'transition-opacity duration-300 ease-out-strong',
        open !== null && place ? 'opacity-100' : 'opacity-0',
        arrow !== 'top' && '-translate-y-1/2',
      )}
      style={place ? { left: place.left, top: place.top } : { left: 0, top: 0 }}
    >
      {t(`landing.toolkit.${tool.key}Body`)}
      <span
        aria-hidden
        className={cn(
          'absolute h-3 w-3 rotate-45 bg-sombre-800/95',
          arrow === 'left' && 'right-full top-1/2 -translate-y-1/2 translate-x-1.5',
          arrow === 'right' && 'left-full top-1/2 -translate-y-1/2 -translate-x-1.5',
          arrow === 'top' && 'bottom-full left-1/2 -translate-x-1/2 translate-y-1.5',
        )}
      />
    </div>
  )
}

/**
 * A card with two faces and a thickness: the cream face, the navy back with
 * the mark, and its edges, seen only when it stands edge on. Never both
 * faces at once (backface-visibility). `turn` is how it is turned on its
 * upright axis (a CSS value; 0 shows the face); `shadow`, how wide its shadow
 * is. Pointed at with a mouse, or touched, it lifts and the bubble with the
 * full description opens beside it (Bulle); both go through the same state
 * (`open`). It does not turn again under the pointer: edge on, it slipped
 * from under it and the bubble flickered.
 */
function Carte({ tool, index, open, onToggle, onPoint, turn, shadow, arrive, resting = true, onRest }: { tool: Tool; index: number; open: boolean; onToggle(): void; onPoint(on: boolean): void; turn?: string; shadow?: string; arrive?: boolean; resting?: boolean; onRest?(): void }) {
  const { t, i18n } = useTranslation()

  const rtl = i18n.dir() === 'rtl'
  const number = String(index + 1).padStart(2, '0')
  const face = 'absolute inset-0 overflow-hidden rounded-[1.25rem] [backface-visibility:hidden]'
  return (
    <div
      className={cn('group relative', open && 'z-30')}
      onPointerEnter={(event) => event.pointerType === 'mouse' && onPoint(true)}
      onPointerLeave={(event) => event.pointerType === 'mouse' && onPoint(false)}
    >
      {/* Its shadow on the light: as wide as the card is, seen from the front. */}
      <span
        aria-hidden
        className="pointer-events-none absolute inset-x-[12%] -bottom-7 h-7 rounded-[50%] bg-[rgb(7_15_28/0.45)] blur-xl transition-transform duration-500"
        style={{ transform: `scaleX(${shadow ?? 1})` }}
      />
      <div className={cn('relative transition-transform duration-500 ease-out-strong [perspective:1200px]', open && '-translate-y-3')}>
        <div
          // In 3D while it turns; flat once it rests on its face, which looks the same: Chrome draws a
          // resting 3D card over the bubble of the card before it, whatever their order.
          className={cn('relative', resting ? '[transform-style:flat]' : '[transform-style:preserve-3d]', arrive && 'carte-arrive')}
          style={{ ...(turn && !resting ? { transform: `rotateY(${turn})` } : null), ...(arrive ? vars({ '--spin': `${rtl ? SPIN : -SPIN}deg`, '--dx': rtl ? '3rem' : '-3rem', '--d': '120ms' }) : null) }}
          onAnimationEnd={arrive ? onRest : undefined}
        >
          <div className={cn('relative', resting ? '[transform-style:flat]' : '[transform-style:preserve-3d]')}>
            {/* The face. */}
            <button
              type="button"
              data-feuille
              aria-expanded={open}
              aria-controls={BULLE}
              onClick={onToggle}
              className={cn(
                'relative block w-full overflow-hidden rounded-[1.25rem] bg-[linear-gradient(165deg,#FFFDF8,#F3ECDF)] text-start text-plume [backface-visibility:hidden]',
                'shadow-[0_1px_1px_rgb(7_15_28/0.2),0_14px_30px_-12px_rgb(7_15_28/0.5)] ring-1 ring-plume/[0.06]',
                'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-creme-100',
              )}
              style={{ transform: `translateZ(calc(${EDGE} / 2))` }}
            >
              <span className="flex items-center justify-between px-5 pt-4">
                <span dir="ltr" className="font-mono text-[0.75rem] font-medium tracking-[0.14em] text-or-fonce">
                  {number}
                </span>
                <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-or" />
              </span>
              <img src={tool.img} alt="" loading="lazy" decoding="async" draggable={false} className="mx-auto aspect-[4/3] w-[86%] object-contain" />
              {/* Where the card would be torn off. */}
              <span aria-hidden className="mx-5 block border-t-[1.5px] border-dashed border-plume/20" />
              <span className="block px-5 pb-5 pt-3.5">
                <span className="block text-[1.0625rem] font-semibold leading-snug">{t(`landing.toolkit.${tool.key}Title`)}</span>
                <span className="mt-1 block text-[0.875rem] leading-snug text-plume/75">{t(`landing.toolkit.${tool.key}Short`)}</span>
              </span>
            </button>
            {/* The back: navy, a thin light along its edge, the mark in the middle. */}
            <div
              aria-hidden
              className={cn(face, 'flex flex-col items-center justify-center bg-[linear-gradient(160deg,#2A4C78,#1F3A5F_38%,#0F2038)] text-white ring-1 ring-inset ring-white/15')}
              style={{ transform: `rotateY(180deg) translateZ(calc(${EDGE} / 2))` }}
            >
              <span className="absolute inset-0 bg-[linear-gradient(125deg,rgb(255_255_255/0.16),transparent_32%,transparent_70%,rgb(255_255_255/0.06))]" />
              <span className="absolute inset-3 rounded-[0.875rem] border border-white/10" />
              <span dir="ltr" className="absolute start-5 top-4 font-mono text-[0.6875rem] tracking-[0.18em] text-white/55">
                {number}
              </span>
              <span className="absolute bottom-5 end-5 h-px w-8 bg-white/30" />
              <ConstruyeMark className="h-14 w-14" />
              <span dir="ltr" className="mt-4 text-[1.375rem] font-semibold tracking-[0.24em]">
                CONSTRUYE
              </span>
              <span dir="ltr" className="mt-1 text-[0.5625rem] font-medium tracking-[0.42em] text-white/75">
                ACADEMY
              </span>
            </div>
            {/* Its two edges, paper-coloured. */}
            {[90, -90].map((angle) => (
              <span
                key={angle}
                aria-hidden
                className="absolute inset-y-[0.6rem] left-1/2 rounded-sm bg-[linear-gradient(180deg,#EDE4D3,#CFC3AC)]"
                style={{ width: EDGE, marginLeft: `calc(${EDGE} / -2)`, transform: `rotateY(${angle}deg) translateZ(calc(var(--cw) / 2))` }}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

/** On a phone: a card that comes in turning as it is seen. */
function CarteVue(props: Omit<Parameters<typeof Carte>[0], 'arrive' | 'resting' | 'onRest'> & { className?: string; item(node: HTMLLIElement | null): void }) {
  const seen = useVu<HTMLLIElement>(0.35)
  const [rested, setRested] = useState(false)
  const { className, item, ...card } = props
  return (
    <li
      ref={(node) => {
        ;(seen as { current: HTMLLIElement | null }).current = node
        item(node)
      }}
      className={cn('relative', className, card.open && 'z-30')}
      style={vars({ '--cw': 'min(78vw, 19rem)' })}
    >
      <Carte {...card} arrive={!rested} resting={rested} onRest={() => setRested(true)} />
    </li>
  )
}

/**
 * S6, the tools, staged after the client's reference. Night.
 *
 * Pinned, the scroll setting the pace. The sentence is there from the start,
 * large, in the middle, in the very colour of the night: it cannot be seen.
 * A large soft light, golden at its heart and blue at its edges, rises from
 * below behind it, and the sentence appears against it, a silhouette drawn
 * by the light, until it reads in full. Then all of it blurs and fades into
 * the background but "the tools", and the four cards come in one by one, on their navy
 * backs, turning over once on their upright axis to show their faces, and
 * settle side by side in a fan in the middle, in front of the blurred words.
 * On a phone the same, shorter, then the cards turn in as they are seen, in
 * a slightly staggered column. Without motion: the words dark on a still
 * golden light, the cards on their faces. In Arabic they come from the right.
 */
export function Outils() {
  const { t, i18n } = useTranslation()
  const rtl = i18n.dir() === 'rtl'
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const stage = useRef<HTMLDivElement | null>(null)
  const [open, setOpen] = useState<string | null>(null)
  // A click on a card the mouse has already opened keeps it open; a touch opens and closes.
  const hovered = useRef<string | null>(null)
  const toggle = (key: string) => setOpen((current) => (current === key && hovered.current !== key ? null : key))
  // A mouse leaving closes after a breath: a leave followed at once by an enter does not blink the bubble.
  const closing = useRef(0)
  const point = (key: string, on: boolean) => {
    hovered.current = on ? key : hovered.current === key ? null : hovered.current
    window.clearTimeout(closing.current)
    if (on) setOpen(key)
    else closing.current = window.setTimeout(() => setOpen((current) => (current === key ? null : current)), 120)
  }
  // Which cards have landed on their faces (and are drawn flat); kept in a ref between frames.
  const [rested, setRested] = useState<boolean[]>(() => TOOLS.map(() => false))
  const restedNow = useRef(rested)

  // The moments of the sequence, as shares of the pinned scroll.
  const at = wide ? { light: [0.02, 0.32], blur: [0.36, 0.48], cards: 0.5 } : { light: [0.04, 0.55], blur: [0.62, 0.9], cards: 2 }
  const track = useScrollProgress<HTMLDivElement>((p) => {
    const box = stage.current
    if (!box) return
    box.style.setProperty('--g', smooth(between(p, at.light[0], at.light[1])).toFixed(4))
    box.style.setProperty('--f', ease(between(p, at.blur[0], at.blur[1])).toFixed(4))
    TOOLS.forEach((_, index) => {
      // It comes up quickly, and turns a little behind, so that it turns where it can be seen.
      const start = at.cards + index * 0.09
      const a = ease(between(p, start, start + 0.08))
      const spin = smooth(between(p, start + 0.015, start + 0.13))
      const angle = (1 - spin) * SPIN * (rtl ? 1 : -1)
      box.style.setProperty(`--a${index}`, a.toFixed(4))
      box.style.setProperty(`--r${index}`, `${angle.toFixed(2)}deg`)
      // The shadow narrows as the card stands edge on.
      box.style.setProperty(`--s${index}`, Math.max(0.12, Math.abs(Math.cos((angle * Math.PI) / 180))).toFixed(3))
    })
    const landed = TOOLS.map((_, index) => p >= at.cards + index * 0.09 + 0.13)
    if (landed.some((value, index) => value !== restedNow.current[index])) {
      restedNow.current = landed
      setRested(landed)
    }
  }, !still)

  // The words, in the very colour of the night; the light behind them makes them out.
  // Then they blur, all but "the tools", which stay sharp.
  const [before, after] = t('landing.toolkit.l3', { tools: '§' }).split('§')
  const blurred: CSSProperties = still ? {} : { filter: 'blur(calc(var(--f, 0) * 10px))', opacity: 'calc(1 - var(--f, 0) * 0.7)' }
  const words = (
    <div className="text-center text-sombre-950">
      <h2 id="s6-titre" aria-label={t('landing.toolkit.title')} className="titre text-[clamp(2.4rem,1.2rem+4.6vw,5.5rem)]">
        <span aria-hidden className="block" style={blurred}>
          {t('landing.toolkit.l1')}
        </span>
        <span aria-hidden className="block" style={blurred}>
          {t('landing.toolkit.l2')}
        </span>
        <span aria-hidden className="block">
          <span style={blurred}>{before}</span>
          {t('landing.toolkit.tools')}
          <span style={blurred}>{after}</span>
        </span>
      </h2>
      <p className="mt-6 text-[1.125rem] font-medium" style={blurred}>
        {t('landing.toolkit.footer')}
      </p>
    </div>
  )

  // The light: golden at its heart, blue at its edges, very soft. It rises from below.
  const light = (
    <div
      aria-hidden
      className="pointer-events-none absolute left-1/2 top-1/2 h-[150vmax] w-[150vmax] rounded-full bg-[radial-gradient(closest-side,rgb(246_200_120/0.98),rgb(241_184_96/0.95)_32%,rgb(232_163_61/0.82)_50%,rgb(59_111_216/0.42)_68%,transparent_84%)] blur-[40px] will-change-transform"
      style={still ? { transform: 'translate(-50%, -50%)' } : { transform: 'translate(-50%, calc(-50% + (1 - var(--g, 0)) * 85svh)) scale(calc(0.6 + var(--g, 0) * 0.4))', opacity: 'calc(0.15 + var(--g, 0) * 0.85)' }}
    />
  )

  const card = (tool: Tool, index: number) => ({ tool, index, open: open === tool.key, onToggle: () => toggle(tool.key), onPoint: (on: boolean) => point(tool.key, on) })
  // The bubble lives after the cards, in the frame they are laid in.
  const frame = useRef<HTMLDivElement | null>(null)
  const items = useRef<(HTMLLIElement | null)[]>([])
  const item = (index: number) => (node: HTMLLIElement | null) => {
    items.current[index] = node
  }
  const opened = open === null ? null : TOOLS.findIndex((tool) => tool.key === open)
  const row = wide
  const side = useCallback((index: number): Side => (row ? (index < 2 ? 'end' : 'start') : 'below'), [row])
  const bulle = <Bulle frame={frame} items={items} open={opened} side={side} />
  // On a wide screen the stage is also the bubble's frame.
  const stageFrame = useCallback((node: HTMLDivElement | null) => {
    stage.current = node
    frame.current = node
  }, [])

  if (still) {
    return (
      <section id="outils" data-tone="night" aria-labelledby="s6-titre" className="relative overflow-hidden bg-sombre-950 py-24 lg:py-36" style={vars({ '--focus': '#F6F2EA' })}>
        <div ref={frame} className="container relative">
          {/* The light stands behind the words themselves, whatever the height of the section. */}
          <div className="relative">
            {light}
            <div className="relative">{words}</div>
          </div>
          <ul className="mx-auto mt-16 grid max-w-[22rem] gap-12 sm:max-w-none sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
            {TOOLS.map((tool, index) => (
              <li key={tool.key} ref={item(index)} className={cn('relative', open === tool.key && 'z-30')} style={vars({ '--cw': '0px' })}>
                <Carte {...card(tool, index)} />
              </li>
            ))}
          </ul>
          {bulle}
        </div>
      </section>
    )
  }

  if (wide) {
    // They come up from below, from the side the reading starts on, and settle in a fan.
    const from = rtl ? '24vw' : '-24vw'
    return (
      <section id="outils" data-tone="night" aria-labelledby="s6-titre" className="relative bg-sombre-950" style={vars({ '--focus': '#F6F2EA' })}>
        <div ref={track} className="h-[520svh]">
          <div
            ref={stageFrame}
            className="sticky top-0 h-[100svh] overflow-hidden"
            style={vars({ '--g': 0, '--f': 0 })}
          >
            {light}
            {/* The words rise as they blur, so that "the tools" stay in sight above the fan of cards. */}
            <div className="container absolute inset-0 flex items-center justify-center" style={{ transform: 'translate3d(0, calc(var(--f, 0) * -30svh), 0)' }}>
              {words}
            </div>
            <ul className="container absolute inset-x-0 top-[56%] flex -translate-y-1/2 items-center justify-center gap-[clamp(0.75rem,1.4vw,1.75rem)]">
              {TOOLS.map((tool, index) => (
                <li
                  key={tool.key}
                  ref={item(index)}
                  className={cn('relative', open === tool.key && 'z-30')}
                  style={{
                    ...vars({ '--cw': 'clamp(12rem, 15.5vw, 16rem)' }),
                    width: 'var(--cw)',
                    transform: `translate(calc((1 - var(--a${index}, 0)) * ${from}), calc((1 - var(--a${index}, 0)) * 85svh + var(--a${index}, 0) * ${tool.lift}rem)) rotate(calc(var(--a${index}, 0) * ${tool.turn}deg))`,
                    opacity: `min(1, calc(var(--a${index}, 0) * 4))`,
                  }}
                >
                  <Carte {...card(tool, index)} turn={`var(--r${index}, ${rtl ? SPIN : -SPIN}deg)`} shadow={`var(--s${index}, 0.12)`} resting={rested[index]} />
                </li>
              ))}
            </ul>
            {bulle}
          </div>
        </div>
      </section>
    )
  }

  // A phone: the words made out by the light and blurred, pinned and shorter; then the cards, turning in one by one.
  // Clipped sideways only: a section that hides its overflow would hold the stage from sticking.
  return (
    <section id="outils" data-tone="night" aria-labelledby="s6-titre" className="relative overflow-x-clip bg-sombre-950" style={vars({ '--focus': '#F6F2EA' })}>
      <div ref={track} className="h-[240svh]">
        <div ref={stage} className="sticky top-0 flex h-[100svh] items-center overflow-hidden" style={vars({ '--g': 0, '--f': 0 })}>
          {light}
          <div className="container relative">{words}</div>
        </div>
      </div>
      {/* The light stays behind the cards. */}
      <div ref={frame} className="relative pb-28">
        <div aria-hidden className="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_60%_at_50%_30%,rgb(232_163_61/0.32),rgb(59_111_216/0.22)_55%,transparent_80%)]" />
        <ul className="container relative flex flex-col gap-14 pt-10">
          {TOOLS.map((tool, index) => (
            <CarteVue key={tool.key} {...card(tool, index)} item={item(index)} className={cn('w-[min(78vw,19rem)]', STAGGER[index])} />
          ))}
        </ul>
        {bulle}
      </div>
    </section>
  )
}
