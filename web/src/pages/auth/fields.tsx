import { forwardRef, useEffect, useId, useMemo, useRef, useState, type InputHTMLAttributes, type PointerEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Check, Eye, EyeOff, Loader2 } from 'lucide-react'
import { PASSWORD_MIN_LENGTH } from '@/lib/passwordRules'
import { cn } from '@/lib/utils'

/*
 * The account screens' fields and button, in the sales page's night: glass,
 * amber light. Looks only: what a field holds, how it is checked and what is
 * sent stay with the forms (LoginForm, RegisterForm, ForgotForm).
 */

/** A field's error: said under it in a soft coral, and the field gives a small shake when it appears. */
function useShake(error?: string) {
  const [shaking, setShaking] = useState(false)
  const last = useRef<string | undefined>(undefined)
  useEffect(() => {
    if (error && error !== last.current) {
      setShaking(true)
      const timer = window.setTimeout(() => setShaking(false), 420)
      last.current = error
      return () => window.clearTimeout(timer)
    }
    if (!error) last.current = undefined
  }, [error])
  return shaking
}

export function Erreur({ id, children }: { id: string; children: ReactNode }) {
  return (
    <p id={id} role="alert" className="mt-1.5 flex items-start gap-1.5 ps-1 text-[0.8125rem] leading-snug text-[#F2A38E]">
      <span aria-hidden className="mt-[0.45em] h-1 w-1 shrink-0 rounded-full bg-current" />
      {children}
    </p>
  )
}

export interface ChampProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
  /** Something at the end of the field: the eye, the green check. */
  end?: ReactNode
  /** Something at its start: the country code. */
  start?: ReactNode
  hint?: ReactNode
}

/**
 * A field with a floating label: inside while the field is empty, up in its
 * top corner once it is focused or filled (pure CSS: :placeholder-shown).
 * Focus lights its outline in amber.
 */
export const Champ = forwardRef<HTMLInputElement, ChampProps>(function Champ({ label, error, end, start, hint, id, className, ...props }, ref) {
  const made = useId()
  const inputId = id ?? made
  const errorId = `${inputId}-erreur`
  const shaking = useShake(error)
  // A field written left to right (an address, a password): its padding is on
  // its own right, or on its left in Arabic, where the eye sits at the field's end.
  const ltr = props.dir === 'ltr'
  return (
    <div className={cn('w-full', shaking && 'champ-secousse')}>
      <div
        // The country code stays on the left of the number, in Arabic too.
        dir={start && ltr ? 'ltr' : undefined}
        className={cn(
          'relative flex rounded-xl bg-white/[0.045] ring-1 transition-[box-shadow,background-color] duration-200 ease-out-strong',
          'focus-within:bg-white/[0.07] focus-within:shadow-[0_0_0_4px_rgb(232_163_61/0.14),0_0_24px_-6px_rgb(232_163_61/0.45)] focus-within:ring-[#E8A33D]',
          error ? 'ring-[#F2A38E]/60' : 'ring-white/[0.14] hover:ring-white/25',
        )}
      >
        {start}
        <div className="relative min-w-0 flex-1">
          <input
            id={inputId}
            ref={ref}
            placeholder=" "
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? errorId : undefined}
            className={cn(
              'peer h-[3.625rem] w-full rounded-xl bg-transparent px-4 pb-1.5 pt-5 text-[1rem] text-creme-100 outline-none',
              'placeholder:text-transparent focus:placeholder:text-creme-100/35 autofill:shadow-[inset_0_0_0_100px_#16243A] autofill:[-webkit-text-fill-color:#F6F2EA]',
              end && (ltr ? 'pr-12 rtl:pl-12 rtl:pr-4' : 'pe-12'),
              className,
            )}
            {...props}
          />
          <label
            htmlFor={inputId}
            className={cn(
              'pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-[1rem] text-creme-100/55 transition-[top,font-size,color,transform] duration-200 ease-out-strong',
              'peer-focus:top-[0.95rem] peer-focus:text-[0.75rem] peer-focus:text-[#F1B860]',
              'peer-[:not(:placeholder-shown)]:top-[0.95rem] peer-[:not(:placeholder-shown)]:text-[0.75rem]',
            )}
          >
            {label}
          </label>
          {end && <div className="absolute inset-y-0 end-2 flex items-center">{end}</div>}
        </div>
      </div>
      {error ? <Erreur id={errorId}>{error}</Erreur> : hint}
    </div>
  )
})

/** A password field with its eye: shown or hidden at will. */
export const ChampSecret = forwardRef<HTMLInputElement, Omit<ChampProps, 'type'> & { extra?: ReactNode }>(function ChampSecret({ extra, ...props }, ref) {
  const { t } = useTranslation()
  const [shown, setShown] = useState(false)
  return (
    <Champ
      ref={ref}
      type={shown ? 'text' : 'password'}
      dir="ltr"
      end={
        <span className="flex items-center gap-1">
          {extra}
          <button
            type="button"
            onClick={() => setShown((value) => !value)}
            aria-label={shown ? t('auth.hidePassword') : t('auth.showPassword')}
            aria-pressed={shown}
            className="grid h-9 w-9 place-items-center rounded-lg text-creme-100/55 transition-colors hover:bg-white/[0.06] hover:text-creme-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]"
          >
            {/* The icon says the state (hidden: struck out), the label says the action. */}
            {shown ? <Eye className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} /> : <EyeOff className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />}
          </button>
        </span>
      }
      {...props}
    />
  )
})

/** A green check that draws itself: the confirmation matches. */
export function Coche({ label }: { label: string }) {
  return (
    <span role="img" aria-label={label} className="grid h-7 w-7 place-items-center rounded-full bg-[#2FA36B]/20 text-[#5DD39A]">
      <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden>
        <path d="M5 12.5l4.5 4.5L19 7.5" pathLength={1} fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" className="coche-trace" />
      </svg>
    </span>
  )
}

/** How strong a password looks, 0 to 4: for the eye only, the rule itself is the form's. */
function strength(value: string) {
  if (!value) return 0
  let score = value.length >= PASSWORD_MIN_LENGTH ? 1 : 0
  if (value.length >= PASSWORD_MIN_LENGTH + 4) score++
  if (/\d/.test(value) && /\p{L}/u.test(value)) score++
  if (/[^\p{L}\d]/u.test(value) || (/\p{Lu}/u.test(value) && /\p{Ll}/u.test(value))) score++
  return Math.max(value.length > 0 ? 1 : 0, Math.min(4, score))
}
const STRENGTH_COLOURS = ['bg-white/10', 'bg-[#E0795F]', 'bg-[#E8A33D]', 'bg-[#C9C55A]', 'bg-[#5DD39A]']

export function Solidite({ value }: { value: string }) {
  const { t } = useTranslation()
  const score = strength(value)
  return (
    <div className="mt-2 px-1" aria-live="polite">
      <div className="grid grid-cols-4 gap-1.5" aria-hidden>
        {[1, 2, 3, 4].map((step) => (
          <span key={step} className="h-1 overflow-hidden rounded-full bg-white/10">
            <span className={cn('block h-full origin-left rounded-full transition-[transform,background-color] duration-500 ease-out-strong rtl:origin-right', STRENGTH_COLOURS[score])} style={{ transform: `scaleX(${score >= step ? 1 : 0})` }} />
          </span>
        ))}
      </div>
      {value && (
        <p className="mt-1.5 text-[0.75rem] text-creme-100/55">
          {t('auth.strengthLabel')} : <span className="text-creme-100/85">{t(`auth.strength${score}`)}</span>
        </p>
      )}
    </div>
  )
}

/**
 * Country codes for the phone: Spain first, then where the course's buyers
 * live or come from (Europe, the former Soviet countries, the Maghreb, the
 * Gulf and the Americas). Their names come from the browser, in the page's
 * language.
 */
const COUNTRIES = [
  { code: 'ES', dial: '+34' },
  { code: 'FR', dial: '+33' },
  { code: 'GB', dial: '+44' },
  { code: 'DE', dial: '+49' },
  { code: 'IT', dial: '+39' },
  { code: 'PT', dial: '+351' },
  { code: 'NL', dial: '+31' },
  { code: 'BE', dial: '+32' },
  { code: 'CH', dial: '+41' },
  { code: 'RU', dial: '+7' },
  { code: 'UA', dial: '+380' },
  { code: 'PL', dial: '+48' },
  { code: 'RO', dial: '+40' },
  { code: 'MA', dial: '+212' },
  { code: 'DZ', dial: '+213' },
  { code: 'AE', dial: '+971' },
  { code: 'SA', dial: '+966' },
  { code: 'US', dial: '+1' },
  { code: 'MX', dial: '+52' },
  { code: 'AR', dial: '+54' },
  { code: 'CO', dial: '+57' },
] as const
const flag = (code: string) => String.fromCodePoint(...[...code].map((letter) => 0x1f1e6 + letter.charCodeAt(0) - 65))

/**
 * The phone number: the country code chosen in a list, the number typed after
 * it. `onNumber` receives the whole number, as the form expects it: what was
 * typed when it already starts with + or 00, otherwise the code followed by
 * the number without any leading zero ("06 12…" with France chosen is
 * "+336 12…"; "612…" with Spain is "+34612…").
 */
export function ChampTelephone({ label, error, onNumber, onBlur, name }: { label: string; error?: string; onNumber(value: string): void; onBlur?(): void; name?: string }) {
  const { t, i18n } = useTranslation()
  const [country, setCountry] = useState<string>('ES')
  const [local, setLocal] = useState('')
  const names = useMemo(() => {
    try {
      return new Intl.DisplayNames([i18n.language], { type: 'region' })
    } catch {
      return null
    }
  }, [i18n.language])
  const dial = COUNTRIES.find((entry) => entry.code === country)?.dial ?? '+34'
  const compose = (code: string, typed: string) => {
    const trimmed = typed.trim()
    if (!trimmed) return ''
    if (/^(\+|00)/.test(trimmed)) return trimmed
    return code + trimmed.replace(/^0+/, '')
  }
  const selectId = useId()
  return (
    <Champ
      label={label}
      name={name}
      type="tel"
      inputMode="tel"
      autoComplete="tel"
      dir="ltr"
      error={error}
      value={local}
      onBlur={onBlur}
      onChange={(event) => {
        setLocal(event.target.value)
        onNumber(compose(dial, event.target.value))
      }}
      // The code always sits before the number, on the left, in Arabic too.
      start={
        <div dir="ltr" className="relative flex shrink-0 items-center border-e border-white/10">
          {/* What shows: the flag and the code. The list itself, with the countries' names, opens over it. */}
          <span aria-hidden className="flex items-center gap-1.5 pe-7 ps-3.5 text-[0.9375rem] text-creme-100">
            <span className="text-[1.125rem] leading-none">{flag(country)}</span>
            {dial}
          </span>
          <svg aria-hidden viewBox="0 0 10 6" className="pointer-events-none absolute end-2.5 h-1.5 w-2.5 text-creme-100/50">
            <path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" strokeWidth="1.5" />
          </svg>
          <label htmlFor={selectId} className="sr-only">
            {t('auth.country')}
          </label>
          <select
            id={selectId}
            value={country}
            autoComplete="tel-country-code"
            onChange={(event) => {
              setCountry(event.target.value)
              const next = COUNTRIES.find((entry) => entry.code === event.target.value)?.dial ?? '+34'
              onNumber(compose(next, local))
            }}
            className="absolute inset-0 h-full w-full cursor-pointer appearance-none rounded-s-xl opacity-0 focus-visible:opacity-100 focus-visible:bg-transparent focus-visible:text-transparent focus-visible:ring-2 focus-visible:ring-[#F1B860]"
          >
            {COUNTRIES.map((entry) => (
              <option key={entry.code} value={entry.code} className="bg-sombre-900 text-creme-100">
                {flag(entry.code)} {entry.dial} · {names?.of(entry.code) ?? entry.code}
              </option>
            ))}
          </select>
        </div>
      }
    />
  )
}

type State = 'idle' | 'loading' | 'done'

/**
 * The main button, as on the sales page: amber, an arrow in a dark square, a
 * glint now and then, leaning a little towards the mouse. While the form is
 * sent it turns into a spinner, then into a check once it went through.
 */
export function BoutonEnvoi({ children, state, className }: { children: ReactNode; state: State; className?: string }) {
  const wrap = useRef<HTMLSpanElement>(null)
  const lean = (event: PointerEvent<HTMLSpanElement>) => {
    const node = wrap.current
    if (!node || event.pointerType !== 'mouse') return
    const box = node.getBoundingClientRect()
    const x = (event.clientX - (box.left + box.width / 2)) / (box.width / 2)
    const y = (event.clientY - (box.top + box.height / 2)) / (box.height / 2)
    node.style.transform = `translate3d(${(x * 5).toFixed(1)}px, ${(y * 3).toFixed(1)}px, 0)`
  }
  const busy = state !== 'idle'
  return (
    <span
      ref={wrap}
      onPointerMove={lean}
      onPointerLeave={() => wrap.current && (wrap.current.style.transform = '')}
      className={cn('block transition-transform duration-300 ease-out-strong motion-reduce:!transform-none', className)}
    >
      <button type="submit" disabled={busy} aria-busy={state === 'loading'} className="btn-or relative w-full disabled:cursor-default">
        <span className={cn('min-w-0 truncate transition-opacity duration-200', busy && 'opacity-0')}>{children}</span>
        <span aria-hidden className={cn('btn-or__fleche transition-opacity duration-200', busy && 'opacity-0')}>
          <ArrowRight className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
        </span>
        <span aria-hidden className={cn('absolute inset-0 grid place-items-center transition-[opacity,transform] duration-300 ease-out-strong', state === 'loading' ? 'opacity-100' : 'scale-75 opacity-0')}>
          <Loader2 className="h-5 w-5 animate-spin" strokeWidth={2} />
        </span>
        <span aria-hidden className={cn('absolute inset-0 grid place-items-center transition-[opacity,transform] duration-300 ease-out-strong', state === 'done' ? 'opacity-100' : 'scale-75 opacity-0')}>
          <Check className="h-6 w-6" strokeWidth={2.5} />
        </span>
      </button>
    </span>
  )
}
