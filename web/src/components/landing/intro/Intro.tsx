import { useCallback, useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type MouseEvent, type PointerEvent, type TouchEvent, type WheelEvent } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, ArrowUpRight } from 'lucide-react'
import { ConstruyeLogo } from '@/components/brand/ConstruyeMark'
import { Mots } from '@/components/landing/kit/pieces'
import { vars } from '@/components/landing/kit/tools'
import { useMediaNow } from '@/hooks/useMediaNow'
import { cn } from '@/lib/utils'
import type { IntroScene } from './introScene'

const CHAPTERS = ['terrain', 'dossier', 'chantier', 'cles'] as const
/** The loader is a welcome, not a wait: it shows for at least this long… */
const LOADER_MIN_MS = 1500
/** …and if the scene is not ready by then, the visitor goes straight to the page. */
const LOADER_MAX_MS = 3000
/** One step per gesture: a wheel or a trackpad sends dozens of events. */
const STEP_LOCK_MS = 850
/** A gap this long between two wheel events means a new gesture has begun. */
const WHEEL_PAUSE_MS = 140
const LEAVE_MS = 800

type Phase = 'loading' | 'title' | 'story' | 'leaving'

/**
 * S0, the cinematic entrance. A staged loader, then an illustrated world with
 * the title and two ways in: "Lancer votre opération" tells the method's
 * story in four chapters while the camera travels through the scene;
 * "Voir directement la formation" and "Passer" go to the sales page at once.
 *
 * It lies over the sales page, which is already in the document underneath
 * and simply made unreachable (inert) while this plays. Whether it plays at
 * all is decided by introGate: first visit to the front door only.
 */
export function Intro({ onDone }: { onDone(): void }) {
  const { t, i18n } = useTranslation()
  const compact = useMediaNow('(max-width: 767px)')
  const rtl = i18n.dir() === 'rtl'
  const [phase, setPhase] = useState<Phase>('loading')
  const [loaded, setLoaded] = useState(false)
  const [chapter, setChapter] = useState(0)
  const root = useRef<HTMLDivElement>(null)
  const canvas = useRef<HTMLCanvasElement>(null)
  const scene = useRef<IntroScene | null>(null)
  const locked = useRef(0)
  const wheeled = useRef(0)
  const touch = useRef<{ x: number; y: number } | null>(null)
  const leaving = useRef(false)

  const leave = useCallback(() => {
    if (leaving.current) return
    leaving.current = true
    scene.current?.finish()
    setPhase('leaving')
    window.setTimeout(onDone, LEAVE_MS)
  }, [onDone])

  // The scene arrives in its own file while the loader plays.
  useEffect(() => {
    let cancelled = false
    const started = performance.now()
    const timers: number[] = []
    timers.push(
      window.setTimeout(() => {
        if (!scene.current) leave()
      }, LOADER_MAX_MS),
    )
    void import('./introScene')
      .then(({ createIntroScene }) => {
        if (cancelled || !canvas.current) return
        scene.current = createIntroScene(canvas.current, { compact })
        scene.current.start()
        timers.push(
          window.setTimeout(
            () => {
              setLoaded(true)
              timers.push(window.setTimeout(() => setPhase((now) => (now === 'loading' ? 'title' : now)), 420))
            },
            Math.max(0, LOADER_MIN_MS - (performance.now() - started)),
          ),
        )
      })
      .catch(leave)
    const onResize = () => scene.current?.resize()
    window.addEventListener('resize', onResize)
    return () => {
      cancelled = true
      timers.forEach((timer) => window.clearTimeout(timer))
      window.removeEventListener('resize', onResize)
      scene.current?.dispose()
      scene.current = null
    }
    // Built once: the scene keeps the layout it was born with.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // While the intro plays, the page under it cannot be scrolled, focused or read aloud.
  useEffect(() => {
    const page = document.getElementById('root')
    const html = document.documentElement
    const overflow = html.style.overflow
    page?.setAttribute('inert', '')
    html.style.overflow = 'hidden'
    root.current?.focus({ preventScroll: true })
    return () => {
      page?.removeAttribute('inert')
      html.style.overflow = overflow
    }
  }, [])

  useEffect(() => {
    scene.current?.setChapter(phase === 'story' ? chapter : phase === 'leaving' ? CHAPTERS.length - 1 : -1)
  }, [phase, chapter, loaded])

  const next = useCallback(() => {
    if (phase === 'title') setPhase('story')
    else if (phase === 'story') {
      if (chapter < CHAPTERS.length - 1) setChapter(chapter + 1)
      else leave()
    }
  }, [phase, chapter, leave])
  const previous = useCallback(() => {
    if (phase !== 'story') return
    if (chapter > 0) setChapter(chapter - 1)
    else setPhase('title')
  }, [phase, chapter])
  /** At most one step per gesture. */
  const step = (forward: boolean) => {
    const now = performance.now()
    if (now < locked.current) return
    locked.current = now + STEP_LOCK_MS
    if (forward) next()
    else previous()
  }

  const onKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') return leave()
    // Buttons keep their own Enter and Space.
    const onControl = (event.target as HTMLElement).closest('button, a') !== null
    const forward = rtl ? 'ArrowLeft' : 'ArrowRight'
    const back = rtl ? 'ArrowRight' : 'ArrowLeft'
    if (event.key === forward || event.key === 'ArrowDown' || event.key === 'PageDown' || (!onControl && (event.key === ' ' || event.key === 'Enter'))) {
      event.preventDefault()
      next()
    } else if (event.key === back || event.key === 'ArrowUp' || event.key === 'PageUp') {
      event.preventDefault()
      previous()
    }
  }
  // A trackpad keeps sending wheel events long after the fingers have left
  // (inertia): one swipe is one step, and the next step waits for a pause.
  const onWheel = (event: WheelEvent<HTMLDivElement>) => {
    const now = performance.now()
    const fresh = now - wheeled.current > WHEEL_PAUSE_MS
    wheeled.current = now
    if (!fresh || Math.abs(event.deltaY) < 12) return
    step(event.deltaY > 0)
  }
  const onTouchStart = (event: TouchEvent<HTMLDivElement>) => {
    touch.current = { x: event.touches[0].clientX, y: event.touches[0].clientY }
  }
  const onTouchEnd = (event: TouchEvent<HTMLDivElement>) => {
    const from = touch.current
    touch.current = null
    if (!from) return
    const dy = event.changedTouches[0].clientY - from.y
    if (Math.abs(dy) > 48) step(dy < 0)
  }
  // In the story, the whole screen is the "next" button.
  const onClick = (event: MouseEvent<HTMLDivElement>) => {
    if (phase !== 'story' || (event.target as HTMLElement).closest('button, a')) return
    next()
  }
  const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType !== 'mouse') return
    scene.current?.setPointer((event.clientX / window.innerWidth) * 2 - 1, (event.clientY / window.innerHeight) * 2 - 1)
  }

  const name = CHAPTERS[chapter]
  const last = chapter === CHAPTERS.length - 1

  return createPortal(
    <div
      ref={root}
      role="dialog"
      aria-modal="true"
      aria-label={t('landing.intro.label')}
      tabIndex={-1}
      onKeyDown={onKeyDown}
      onWheel={onWheel}
      onTouchStart={onTouchStart}
      onTouchEnd={onTouchEnd}
      onClick={onClick}
      onPointerMove={onPointerMove}
      className={cn(
        'landing-intro fixed inset-0 z-[100] touch-none select-none overflow-hidden bg-sombre-950 text-creme-100 outline-none transition-opacity ease-out-strong',
        phase === 'leaving' && 'pointer-events-none opacity-0',
      )}
      style={{ ...vars({ '--focus': '#F6F2EA' }), transitionDuration: `${LEAVE_MS}ms` }}
    >
      {/* The sky the scene is drawn on, and the two lights of the page. */}
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(130%_75%_at_50%_100%,#1F3A5F,#0B1A2E_48%,#070F1C)]" />
      <div aria-hidden className="halo absolute -start-[20%] -top-[30%] h-[80vmax] w-[80vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.32),transparent_60%)]" />
      <div aria-hidden className="halo absolute -bottom-[45%] -end-[20%] h-[85vmax] w-[85vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.26),transparent_58%)] [animation-delay:-9s]" />
      {/* A flat moon over the town: far enough not to move with the camera. */}
      <div
        aria-hidden
        className={cn(
          'absolute end-[13%] top-[19%] h-[15vmin] w-[15vmin] rounded-full max-md:top-[10%] bg-[#F1E7D2] shadow-[0_0_0_3px_#05080F,0_0_90px_20px_rgb(241_231_210/0.22)] transition-opacity duration-1000 ease-out-strong',
          phase === 'loading' ? 'opacity-0' : 'opacity-100',
        )}
      />
      <canvas ref={canvas} aria-hidden className={cn('absolute inset-0 h-full w-full transition-opacity duration-1000 ease-out-strong', phase === 'loading' ? 'opacity-0' : 'opacity-100')} />
      {/* Words are read over the scene: it steps back behind them. */}
      <div
        aria-hidden
        className={cn(
          'absolute inset-0 transition-opacity duration-700 ease-out-strong',
          phase === 'story'
            ? 'bg-[linear-gradient(to_top,rgb(7_15_28/0.88),rgb(7_15_28/0.35)_42%,transparent_70%)]'
            : 'bg-[radial-gradient(60%_42%_at_50%_50%,rgb(7_15_28/0.72),transparent)]',
          phase === 'loading' ? 'opacity-0' : 'opacity-100',
        )}
      />
      <div aria-hidden className="grain" style={{ position: 'absolute', zIndex: 0, opacity: 0.12 }} />

      <Loader shown={phase === 'loading'} loaded={loaded} label={t('landing.intro.loading')} />

      <div className="absolute inset-x-0 top-0 z-10 flex items-center justify-between p-5 pt-[max(1.25rem,env(safe-area-inset-top))] lg:px-10 lg:py-8">
        <ConstruyeLogo variant="inherit" className={cn('transition-opacity duration-500', phase === 'loading' && 'opacity-0')} />
        <button type="button" onClick={leave} className="lien-edito etiquette -mx-2 -my-3 px-2 py-3 text-creme-100/85 [--lien-bas:0.25rem]">
          {t('landing.intro.skip')}
        </button>
      </div>

      {phase === 'title' && (
        <div className="absolute inset-0 z-[5] flex flex-col items-center justify-center px-6 pb-6 text-center">
          <p className="etiquette entree text-creme-100/75" style={vars({ '--d': '100ms' })}>
            {t('landing.s1.eyebrow')}
          </p>
          <h2 className="titre mt-5 max-w-[16ch] text-balance text-[clamp(2.6rem,1rem+5.4vw,5.75rem)] [text-shadow:0_2px_48px_rgb(7_15_28/0.9)]">
            <Mots text={t('landing.heroV2.title')} delay={200} />
          </h2>
          <div className="entree mt-9 flex flex-col items-center gap-6 lg:mt-11" style={vars({ '--d': '900ms' })}>
            <button type="button" onClick={next} className="btn-or">
              <span>{t('landing.intro.start')}</span>
              <span aria-hidden className="btn-or__fleche">
                <ArrowRight className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
              </span>
            </button>
            <button type="button" onClick={leave} className="lien-edito -my-2.5 py-2.5 text-sm text-creme-100/85 [--lien-bas:0.625rem]">
              {t('landing.intro.direct')}
              <ArrowUpRight aria-hidden className="h-[1em] w-[1em] translate-y-[0.12em] rtl:-scale-x-100" strokeWidth={1.5} />
            </button>
          </div>
        </div>
      )}

      {phase === 'story' && (
        <>
          {/* Keyed on the chapter: each message makes its own entrance. */}
          <div key={name} aria-live="polite" className="absolute inset-x-0 bottom-0 z-[5] px-6 pb-32 lg:px-16 lg:pb-36">
            <p className="etiquette entree flex items-center gap-3 text-or" style={vars({ '--d': '250ms' })}>
              <span dir="ltr">{String(chapter + 1).padStart(2, '0')}</span>
              <span aria-hidden className="h-px w-8 bg-or/70" />
              {t(`landing.intro.chapters.${name}.name`)}
            </p>
            <p className="titre mt-4 max-w-[20ch] text-balance text-[clamp(2rem,1rem+3.4vw,3.75rem)]">
              <Mots text={t(`landing.intro.chapters.${name}.text`)} delay={350} />
            </p>
          </div>
          <div className="absolute inset-x-0 bottom-0 z-10 flex items-center gap-5 px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] lg:px-16 lg:pb-10">
            <ol className="flex flex-1 gap-2" aria-label={t('landing.intro.chapterOf', { n: chapter + 1, total: CHAPTERS.length })}>
              {CHAPTERS.map((key, index) => (
                <li key={key} className="flex-1">
                  <button
                    type="button"
                    onClick={() => setChapter(index)}
                    aria-label={t(`landing.intro.chapters.${key}.name`)}
                    aria-current={index === chapter ? 'step' : undefined}
                    className="group block w-full py-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-creme-100"
                  >
                    <span className="block h-[3px] overflow-hidden rounded-full bg-creme-100/20">
                      <span className={cn('block h-full origin-left rounded-full bg-or transition-transform duration-700 ease-out-strong rtl:origin-right', index <= chapter ? 'scale-x-100' : 'scale-x-0')} />
                    </span>
                  </button>
                </li>
              ))}
            </ol>
            <button type="button" onClick={next} className="lien-edito shrink-0 text-sm font-medium">
              {last ? t('landing.intro.enter') : t('landing.intro.next')}
              <ArrowRight aria-hidden className="h-[1em] w-[1em] translate-y-[0.14em] rtl:-scale-x-100" strokeWidth={1.5} />
            </button>
          </div>
        </>
      )}
    </div>,
    document.body,
  )
}

const STROKE = { stroke: '#05080F', strokeWidth: 2.5, strokeLinejoin: 'round' as const }

/**
 * The loader, staged: a site floodlight's beam on the night, the things of
 * the trade drifting in it, the logo, and a crane cable at the bottom whose
 * hook travels as the scene arrives. Drawn in SVG and CSS so that it shows at
 * once, before any 3D has loaded.
 */
function Loader({ shown, loaded, label }: { shown: boolean; loaded: boolean; label: string }) {
  // The hook sets off on the first frame, runs most of the cable while the
  // scene loads, and finishes the moment it is ready.
  const [moving, setMoving] = useState(false)
  useEffect(() => {
    const frame = requestAnimationFrame(() => setMoving(true))
    return () => cancelAnimationFrame(frame)
  }, [])
  const advance = loaded ? 1 : moving ? 0.86 : 0
  const travel = { transitionDuration: loaded ? '380ms' : '2400ms' }

  return (
    <div
      role="progressbar"
      aria-label={label}
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.round(advance * 100)}
      className={cn('absolute inset-0 z-[6] transition-opacity duration-500 ease-out-strong', shown ? 'opacity-100' : 'pointer-events-none opacity-0')}
    >
      {/* The floodlight and its beam. */}
      <div aria-hidden className="absolute left-1/2 top-0 h-[92%] w-[min(120vw,60rem)] -translate-x-1/2 bg-[linear-gradient(to_bottom,rgb(241_184_96/0.42),rgb(232_163_61/0.1)_55%,transparent)] blur-md [clip-path:polygon(46%_0,54%_0,100%_100%,0_100%)]" />
      <div aria-hidden className="absolute left-1/2 top-0 h-3 w-24 -translate-x-1/2 rounded-b-xl bg-or shadow-[0_0_50px_14px_rgb(232_163_61/0.55)]" />
      <div aria-hidden className="absolute bottom-[10%] left-1/2 h-[16vmin] w-[min(110vw,56rem)] -translate-x-1/2 rounded-[50%] bg-[radial-gradient(closest-side,rgb(232_163_61/0.3),transparent)]" />

      <div className="absolute inset-0 flex items-center justify-center">
        <div aria-hidden className="relative h-0 w-0">
          {/* An I-beam. */}
          <svg viewBox="0 0 100 60" className="flotte absolute -left-[min(40vw,19rem)] -top-[min(26vh,13rem)] w-[clamp(5rem,13vw,9rem)]" style={vars({ '--t': '7s', '--a': '-9deg', '--b': '4deg' })}>
            <g {...STROKE}>
              <path d="M32 26 L72 12 V30 L32 44 Z" fill="#1F3A5F" />
              <path d="M44 42 L84 28 V34 L44 48 Z" fill="#1F3A5F" />
              <path d="M12 14 L52 0 H84 L44 14 Z" fill="#3B6195" />
              <path d="M44 14 L84 0 V6 L44 20 Z" fill="#1F3A5F" />
              <path d="M12 14 H44 V20 H32 V42 H44 V48 H12 V42 H24 V20 H12 Z" fill="#2B4D7E" />
            </g>
          </svg>
          {/* A block of concrete. */}
          <svg viewBox="0 0 100 86" className="flotte absolute left-[min(24vw,12rem)] -top-[min(30vh,15rem)] w-[clamp(3.5rem,8vw,5.5rem)]" style={vars({ '--t': '8.5s', '--d': '-2s', '--a': '8deg', '--b': '-5deg' })}>
            <g {...STROKE}>
              <path d="M50 4 L92 22 L50 40 L8 22 Z" fill="#F1E7D2" />
              <path d="M8 22 L50 40 V82 L8 64 Z" fill="#CFC7B6" />
              <path d="M92 22 L50 40 V82 L92 64 Z" fill="#A9A293" />
            </g>
          </svg>
          {/* A breeze block, with its two hollows. */}
          <svg viewBox="0 0 120 80" className="flotte absolute left-[min(30vw,15rem)] top-[min(12vh,6rem)] w-[clamp(4.5rem,10vw,7rem)]" style={vars({ '--t': '7.5s', '--d': '-4s', '--a': '-6deg', '--b': '9deg' })}>
            <g {...STROKE}>
              <path d="M40 4 L116 20 L80 36 L4 20 Z" fill="#E3DCCB" />
              <path d="M4 20 L80 36 V72 L4 56 Z" fill="#CFC7B6" />
              <path d="M116 20 L80 36 V72 L116 56 Z" fill="#A9A293" />
              <path d="M40 12 L62 17 L50 22 L28 17 Z" fill="#05080F" />
              <path d="M70 18 L92 23 L80 28 L58 23 Z" fill="#05080F" />
            </g>
          </svg>
          {/* The golden key. */}
          <svg viewBox="0 0 60 120" className="flotte absolute -left-[min(34vw,16rem)] top-[min(8vh,4rem)] w-[clamp(2.25rem,5vw,3.5rem)]" style={vars({ '--t': '6.5s', '--d': '-1s', '--a': '14deg', '--b': '26deg' })}>
            <g {...STROKE} fill="#E8A33D">
              <path d="M22 44 H38 V112 H22 Z" />
              <path d="M38 78 H52 V88 H38 Z M38 94 H48 V104 H38 Z" />
              <path fillRule="evenodd" d="M30 4 A24 24 0 1 1 29.9 4 Z M30 18 A10 10 0 1 0 30.1 18 Z" />
            </g>
          </svg>
          {/* A rolled drawing. */}
          <svg viewBox="0 0 120 50" className="flotte absolute -left-[min(8vw,4rem)] top-[min(24vh,12rem)] w-[clamp(4.5rem,11vw,7.5rem)]" style={vars({ '--t': '9s', '--d': '-5s', '--a': '-16deg', '--b': '-7deg' })}>
            <g {...STROKE}>
              <path d="M16 8 H104 A10 17 0 0 1 104 42 H16 A10 17 0 0 1 16 8 Z" fill="#F6F2EA" />
              <ellipse cx="104" cy="25" rx="10" ry="17" fill="#CFC7B6" />
              <path d="M58 8 H72 V42 H58 Z" fill="#3B6FD8" />
            </g>
          </svg>
        </div>
        <ConstruyeLogo variant="inherit" className="relative scale-[1.6] drop-shadow-[0_4px_30px_rgb(7_15_28/0.9)] lg:scale-[2]" />
      </div>

      {/* A crane's cable; its hook travels with the loading. */}
      <div aria-hidden className="absolute inset-x-8 bottom-[max(3rem,env(safe-area-inset-bottom))] lg:inset-x-16 rtl:-scale-x-100">
        <div className="h-px w-full bg-creme-100/25" />
        <div className="absolute inset-x-0 top-0 h-px origin-left bg-or transition-transform ease-out-strong" style={{ ...travel, transform: `scaleX(${advance})` }} />
        <div className="absolute inset-x-0 top-0 transition-transform ease-out-strong" style={{ ...travel, transform: `translateX(${advance * 100}%)` }}>
          <svg viewBox="0 0 20 44" className="-ms-[10px] -mt-[3px] w-5 rtl:ms-0">
            <rect x="3" y="0" width="14" height="6" rx="1.5" fill="#E8A33D" />
            <path d="M10 6 V24" stroke="#F6F2EA" strokeWidth="1.2" />
            <path d="M10 24 V31 A6 6 0 1 1 4 31" fill="none" stroke="#E8A33D" strokeWidth="2.6" strokeLinecap="round" />
          </svg>
        </div>
      </div>
    </div>
  )
}
