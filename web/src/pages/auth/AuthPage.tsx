import { useCallback, useEffect, useLayoutEffect, useRef, useState, type PointerEvent, type ReactNode } from 'react'
import { useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ConstruyeMark } from '@/components/brand/ConstruyeMark'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useMediaNow } from '@/hooks/useMediaNow'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { cn } from '@/lib/utils'
import { ForgotForm } from './ForgotForm'
import { LoginForm } from './LoginForm'
import { NuitAuth } from './NuitAuth'
import { RegisterForm } from './RegisterForm'

type View = 'login' | 'register' | 'forgot'
type Side = 'login' | 'register'

const viewOf = (pathname: string): View => (pathname === '/register' ? 'register' : pathname === '/forgot-password' ? 'forgot' : 'login')
const sideOf = (view: View): Side => (view === 'register' ? 'register' : 'login')

/** How long the welcome shows after an account is made, before the next page. */
const WELCOME_MS = 2400
const WELCOME_STILL_MS = 1400

/**
 * Sign in, sign up and the forgotten password, as one screen in the sales
 * page's night.
 *
 * The three addresses (/login, /register, /forgot-password) all show this
 * page, which stays mounted as one moves between them: signing in and
 * signing up are the two faces of one card, which turns over on its upright
 * axis (its navy back, with the mark, shows halfway), and the forgotten
 * password slides in beside the sign-in. What one typed stays where it was.
 *
 * The forms themselves, their rules, their requests and where they lead are
 * the old pages', unchanged (LoginForm, RegisterForm, ForgotForm).
 *
 * The night around it, with the building-site video, is NuitAuth's. Without
 * motion the faces change with a short fade instead of turning.
 */
export function AuthPage() {
  const { t, i18n } = useTranslation()
  const location = useLocation()
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const fine = useMediaNow('(hover: hover) and (pointer: fine)')
  const rtl = i18n.dir() === 'rtl'

  const view = viewOf(location.pathname)
  const side = sideOf(view)
  usePageTitle(t(view === 'register' ? 'auth.registerTitle' : view === 'forgot' ? 'auth.resetPassword' : 'auth.loginTitle'))

  // The links between the views keep where the visitor was going (?redirect=…, or the state a guard left).
  const to = (pathname: string) => ({ pathname, search: location.search })

  /* ---------- The turn ---------- */

  // The face being shown. It changes halfway through the turn, when neither face is towards us.
  const [shown, setShown] = useState<Side>(side)
  const card = useRef<HTMLDivElement>(null)
  const turning = useRef<Animation | null>(null)
  const [fading, setFading] = useState(false)
  // Focus follows the visitor to the new view, but not on the first arrival.
  const moved = useRef(false)

  useEffect(() => {
    if (side === shown) return
    moved.current = true
    const node = card.current
    if (still || !node || typeof node.animate !== 'function') {
      // A short fade instead of the turn.
      setFading(true)
      const timer = window.setTimeout(() => {
        setShown(side)
        setFading(false)
      }, 140)
      return () => window.clearTimeout(timer)
    }
    const duration = wide ? 900 : 550
    const way = rtl ? -1 : 1
    turning.current?.cancel()
    const animation = node.animate(
      [
        { transform: 'rotateY(0deg) scale(1)' },
        { transform: `rotateY(${90 * way}deg) scale(0.94)`, offset: 0.25 },
        { transform: `rotateY(${180 * way}deg) scale(0.92)`, offset: 0.5 },
        { transform: `rotateY(${270 * way}deg) scale(0.94)`, offset: 0.75 },
        { transform: `rotateY(${360 * way}deg) scale(1)` },
      ],
      { duration, easing: 'cubic-bezier(0.65, 0, 0.35, 1)' },
    )
    turning.current = animation
    const half = window.setTimeout(() => setShown(side), duration / 2)
    animation.onfinish = () => {
      animation.cancel()
      if (turning.current === animation) turning.current = null
    }
    return () => window.clearTimeout(half)
  }, [side, shown, still, wide, rtl])

  /* ---------- The height, which follows the view shown ---------- */

  const panes = {
    login: useRef<HTMLDivElement>(null),
    forgot: useRef<HTMLDivElement>(null),
    register: useRef<HTMLDivElement>(null),
  }
  const front: View = shown === 'register' ? 'register' : view === 'forgot' ? 'forgot' : 'login'
  const [height, setHeight] = useState<number | null>(null)
  useLayoutEffect(() => {
    const node = panes[front].current
    if (!node) return
    const measure = () => setHeight(node.offsetHeight)
    measure()
    const sizes = new ResizeObserver(measure)
    sizes.observe(node)
    return () => sizes.disconnect()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [front])

  const lastView = useRef(view)
  useEffect(() => {
    if (lastView.current !== view) moved.current = true
    lastView.current = view
  }, [view])
  // Move focus to the heading of the view one arrived at.
  useEffect(() => {
    if (!moved.current) return
    const timer = window.setTimeout(() => panes[front].current?.querySelector<HTMLElement>('h1')?.focus({ preventScroll: true }), still ? 160 : 320)
    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [front])

  /* ---------- The welcome ---------- */

  const [welcome, setWelcome] = useState<string | null>(null)

  /* ---------- The mouse: the card leans ---------- */

  const tilt = useRef<HTMLDivElement>(null)
  const follow = fine && wide && !still
  const lean = useCallback(
    (event: PointerEvent<HTMLDivElement>) => {
      if (!follow || event.pointerType !== 'mouse') return
      const x = (event.clientX / window.innerWidth) * 2 - 1
      const y = (event.clientY / window.innerHeight) * 2 - 1
      if (tilt.current) tilt.current.style.transform = `rotateX(${(-y * 3.5).toFixed(2)}deg) rotateY(${(x * 4.5).toFixed(2)}deg)`
    },
    [follow],
  )
  const rest = () => {
    if (tilt.current) tilt.current.style.transform = ''
  }


  return (
    <NuitAuth onPointerMove={lean} onPointerLeave={rest}>
      {/* The card. */}
      <div className="[perspective:1600px]">
        <div ref={tilt} className="transition-transform duration-500 ease-out-strong [transform-style:preserve-3d]">
          <div ref={card} className="relative [transform-style:preserve-3d]">
            {/* Its face: the glass, with the view shown. */}
            <div
              className={cn(
                'verre auth-carte overflow-hidden [backface-visibility:hidden] [-webkit-backface-visibility:hidden]',
                'transition-[height] duration-500 ease-out-strong motion-reduce:transition-none',
              )}
              style={{ height: height === null ? undefined : height }}
            >
              <div className={cn('transition-opacity duration-150', fading && 'opacity-0')}>
                {/* Sign in, and beside it the forgotten password, which slides in. */}
                <div className={cn(shown !== 'login' && 'hidden')}>
                  <div className={cn('flex items-start transition-transform duration-500 ease-out-strong motion-reduce:transition-none', view === 'forgot' && (rtl ? 'translate-x-full' : '-translate-x-full'))}>
                    <Pane refTo={panes.login} active={shown === 'login' && view !== 'forgot'}>
                      <LoginForm onForgot={`/forgot-password${location.search}`} onRegister={to('/register')} active={shown === 'login' && view !== 'forgot'} />
                    </Pane>
                    <Pane refTo={panes.forgot} active={shown === 'login' && view === 'forgot'}>
                      <ForgotForm onBack={to('/login')} active={shown === 'login' && view === 'forgot'} />
                    </Pane>
                  </div>
                </div>
                <div className={cn(shown !== 'register' && 'hidden')}>
                  <Pane refTo={panes.register} active={shown === 'register'}>
                    <RegisterForm onLogin={to('/login')} onWelcome={setWelcome} active={shown === 'register'} welcomeMs={still ? WELCOME_STILL_MS : WELCOME_MS} />
                  </Pane>
                </div>
              </div>
              {welcome !== null && <Bienvenue name={welcome} />}
            </div>
            {/* Its back: navy, the mark. Seen only halfway through the turn. */}
            <div
              aria-hidden
              className="absolute inset-0 flex flex-col items-center justify-center overflow-hidden rounded-[1.25rem] bg-[linear-gradient(160deg,#2A4C78,#1F3A5F_38%,#0F2038)] text-white ring-1 ring-inset ring-white/15 [backface-visibility:hidden] [-webkit-backface-visibility:hidden]"
              style={{ transform: 'rotateY(180deg)' }}
            >
              <span className="absolute inset-0 bg-[linear-gradient(125deg,rgb(255_255_255/0.16),transparent_32%,transparent_70%,rgb(255_255_255/0.06))]" />
              <span className="absolute inset-3 rounded-[0.875rem] border border-white/10" />
              <ConstruyeMark className="h-16 w-16" />
              <span dir="ltr" className="mt-4 text-[1.5rem] font-semibold tracking-[0.24em]">
                CONSTRUYE
              </span>
              <span dir="ltr" className="mt-1 text-[0.625rem] font-medium tracking-[0.42em] text-white/75">
                ACADEMY
              </span>
            </div>
          </div>
        </div>
      </div>
    </NuitAuth>
  )
}

/**
 * One view inside the card. The one not shown is out of reach: no tab stop,
 * nothing read by a screen reader (inert).
 */
function Pane({ refTo, active, children }: { refTo: React.RefObject<HTMLDivElement>; active: boolean; children: ReactNode }) {
  useEffect(() => {
    refTo.current?.toggleAttribute('inert', !active)
  }, [refTo, active])
  return (
    <div ref={refTo} aria-hidden={active ? undefined : true} className={cn('w-full shrink-0 p-6 transition-[visibility] duration-500 sm:p-9 [&_h1]:outline-none', !active && 'invisible')}>
      {children}
    </div>
  )
}

/** After the account is made: a golden key turns in its lock, a word of welcome, then the next page. */
function Bienvenue({ name }: { name: string }) {
  const { t } = useTranslation()
  return (
    <div role="status" className="bienvenue absolute inset-0 z-10 flex flex-col items-center justify-center bg-[linear-gradient(160deg,#16294A,#0B1A2E_60%)] px-8 text-center">
      <div className="relative grid h-28 w-28 place-items-center">
        <span aria-hidden className="absolute inset-0 rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.35),transparent_65%)] bienvenue-lueur" />
        <svg aria-hidden viewBox="0 0 64 64" className="bienvenue-cle relative h-24 w-24">
          <defs>
            <linearGradient id="cle-or" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#FBE3A6" />
              <stop offset="0.5" stopColor="#E8A33D" />
              <stop offset="1" stopColor="#9C6216" />
            </linearGradient>
          </defs>
          {/* The bow, then the shank and its bit. */}
          <circle cx="32" cy="16" r="11" fill="none" stroke="url(#cle-or)" strokeWidth="5" />
          <circle cx="32" cy="16" r="3.2" fill="url(#cle-or)" />
          <rect x="29.5" y="26" width="5" height="30" rx="2" fill="url(#cle-or)" />
          <rect x="34" y="44" width="8" height="4" rx="1" fill="url(#cle-or)" />
          <rect x="34" y="51" width="6" height="4" rx="1" fill="url(#cle-or)" />
        </svg>
      </div>
      <p className="titre mt-6 text-[1.625rem] text-creme-100">{t('auth.welcomeTitle', { name: name.split(' ')[0] })}</p>
      <p className="mt-2 max-w-[22rem] text-[0.9375rem] leading-relaxed text-creme-100/65">{t('auth.welcomeText')}</p>
    </div>
  )
}
