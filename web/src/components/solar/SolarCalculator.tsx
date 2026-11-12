import { useEffect, useId, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Lock } from 'lucide-react'
import { apiErrorMessage } from '@/api/orders'
import { useSolarCalculation, useSolarOptions, type SolarInput, type SolarResult } from '@/api/solar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn, intlLocale } from '@/lib/utils'

export interface SolarUnlock {
  to: string
  state?: { from: string }
  /** The button's words: «Desbloquear…» with Module 1's price, or «Abrir…» for its owner. */
  label: string
}

/** "500.000" while typing in Spanish, "500,000" in English: digits only, grouped as the reader writes them. */
function NumberField({
  id,
  value,
  onChange,
  suffix,
  locale,
}: {
  id: string
  value: number
  onChange(value: number): void
  suffix: string
  locale: string
}) {
  const format = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 })
  // Left to right in every language, the unit after the number: as amounts read everywhere.
  return (
    <div dir="ltr" className="relative">
      <input
        id={id}
        dir="ltr"
        inputMode="numeric"
        autoComplete="off"
        value={value > 0 ? format.format(value) : ''}
        onChange={(event) => onChange(Number(event.target.value.replace(/\D/g, '').slice(0, 10)) || 0)}
        className="h-12 w-full rounded-xl border border-plume/15 bg-white pe-14 ps-4 text-start text-[1.0625rem] font-medium tabular-nums text-plume outline-none transition-colors focus-visible:border-plume/40 focus-visible:ring-2 focus-visible:ring-or/40"
      />
      <span className="pointer-events-none absolute inset-y-0 end-4 flex items-center text-sm text-plume/55">{suffix}</span>
    </div>
  )
}

/**
 * The public sample of Plantilla 1 · Compra del solar: four of its inputs and
 * three of its results, calculated by the package's engine on the server as
 * the visitor types. What the full template adds is listed, locked, with the
 * way to Module 1.
 */
export function SolarCalculator({ unlock, className }: { unlock: SolarUnlock; className?: string }) {
  const { t, i18n } = useTranslation()
  const locale = intlLocale(i18n.language)
  const ids = useId()
  const options = useSolarOptions()
  const calculation = useSolarCalculation()
  const [input, setInput] = useState<SolarInput | null>(null)
  const [result, setResult] = useState<SolarResult | null>(null)
  const latest = useRef(0)

  // The template's own defaults, once they arrive.
  useEffect(() => {
    if (options.data && input === null) setInput({ ...options.data.defaults })
  }, [options.data, input])

  // Calculated a moment after the last keystroke; an older answer never replaces a newer one.
  const { mutate } = calculation
  useEffect(() => {
    if (!input || input.price <= 0 || input.buildable_m2 <= 0) return
    const ticket = ++latest.current
    const timer = window.setTimeout(() => {
      mutate(input, { onSuccess: (data) => ticket === latest.current && setResult(data) })
    }, 350)
    return () => window.clearTimeout(timer)
  }, [input, mutate])

  const money = (value: number, digits = 0) =>
    new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR', minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value)
  const percent = (value: number) => new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 2 }).format(value)

  const edit = (patch: Partial<SolarInput>) => setInput((current) => (current ? { ...current, ...patch } : current))
  const defaulted = options.data?.defaulted ?? []
  const label = 'block text-[0.8125rem] font-medium text-plume/70'

  return (
    <div
      className={cn(
        'overflow-hidden rounded-[1.5rem] bg-[linear-gradient(165deg,#FFFDF8,#F6F0E4)] text-plume ring-1 ring-plume/[0.08]',
        'shadow-[0_1px_1px_rgb(7_15_28/0.12),0_30px_60px_-30px_rgb(7_15_28/0.45)]',
        className,
      )}
    >
      {/* The four inputs. */}
      <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
        <div className="space-y-2 sm:col-span-2">
          <label htmlFor={`${ids}-community`} className={label}>
            {t('solar.form.community')}
          </label>
          {/* Remounted with the list: Radix shows the value only when its item exists. */}
          <Select
            key={options.data ? 'ready' : 'loading'}
            value={input?.community}
            onValueChange={(community) => community !== '' && edit({ community })}
          >
            <SelectTrigger id={`${ids}-community`} className="h-12 rounded-xl border-plume/15 bg-white text-[1.0625rem] font-medium">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {(options.data?.communities ?? []).map((community) => (
                <SelectItem key={community} value={community}>
                  {community}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <fieldset className="space-y-2 sm:col-span-2">
          <legend className={label}>{t('solar.form.seller')}</legend>
          <div role="radiogroup" className="grid grid-cols-2 gap-1 rounded-xl bg-plume/[0.06] p-1">
            {[
              { company: false, text: t('solar.form.sellerPerson') },
              { company: true, text: t('solar.form.sellerCompany') },
            ].map(({ company, text }) => (
              <button
                key={String(company)}
                type="button"
                role="radio"
                aria-checked={input?.seller_company === company}
                onClick={() => edit({ seller_company: company })}
                className={cn(
                  'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors duration-150',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-or/50',
                  input?.seller_company === company ? 'bg-white text-plume shadow-sm' : 'text-plume/60 hover:text-plume',
                )}
              >
                {text}
              </button>
            ))}
          </div>
        </fieldset>

        <div className="space-y-2">
          <label htmlFor={`${ids}-price`} className={label}>
            {t('solar.form.price')}
          </label>
          <NumberField id={`${ids}-price`} value={input?.price ?? 0} onChange={(price) => edit({ price })} suffix="€" locale={locale} />
        </div>
        <div className="space-y-2">
          <label htmlFor={`${ids}-buildable`} className={label}>
            {t('solar.form.buildable')}
          </label>
          <NumberField id={`${ids}-buildable`} value={input?.buildable_m2 ?? 0} onChange={(buildable_m2) => edit({ buildable_m2 })} suffix="m²" locale={locale} />
        </div>
      </div>

      {/* The three results: the night panel of the page, so they read first. */}
      <div aria-live="polite" className="theme-night mx-3 rounded-[1.125rem] bg-[linear-gradient(160deg,#16294A,#0B1A2E_60%,#070F1C)] p-5 text-creme-100 sm:mx-4 sm:p-6">
        <p className="text-[0.8125rem] font-medium text-creme-100/70">{t('solar.result.landCost')}</p>
        <p className={cn('mt-1 text-[clamp(2rem,1.5rem+2vw,2.75rem)] font-semibold tabular-nums tracking-tight transition-opacity duration-200', calculation.isPending && 'opacity-60')}>
          {result ? money(result.land_cost) : '—'}
        </p>
        <div className="mt-4 grid gap-4 border-t border-creme-100/10 pt-4 sm:grid-cols-2">
          <div>
            <p className="text-[0.8125rem] text-creme-100/70">{t('solar.result.perM2')}</p>
            <p className="mt-0.5 text-lg font-semibold tabular-nums">
              {result ? (
                <>
                  {money(result.per_m2, 2)} <span className="text-sm font-normal text-creme-100/60">/ m²</span>
                </>
              ) : (
                '—'
              )}
            </p>
          </div>
          <div>
            <p className="text-[0.8125rem] text-creme-100/70">{t('solar.result.taxRate', { tax: result?.tax ?? (input?.seller_company ? 'AJD' : 'ITP') })}</p>
            <p className="mt-0.5 text-lg font-semibold tabular-nums">{result ? percent(result.tax_rate) : '—'}</p>
          </div>
        </div>
        {calculation.isError && (
          <p role="alert" className="mt-4 text-sm text-creme-100/80">
            {apiErrorMessage(calculation.error) ?? t('solar.result.error')}
          </p>
        )}
      </div>

      {/* What the full template adds: named, not shown. */}
      <div className="p-5 sm:p-7">
        <p className="flex items-center gap-2 text-sm font-semibold">
          <Lock className="h-4 w-4 text-or-fonce" aria-hidden />
          {t('solar.locked.title')}
        </p>
        <ul className="mt-3 divide-y divide-plume/[0.07]">
          {[
            t('solar.locked.iva'),
            input?.seller_company === false ? 'ITP' : 'AJD',
            t('solar.locked.notary'),
            t('solar.locked.cashDay'),
            t('solar.locked.excel'),
          ].map((item) => (
            <li key={item} className="flex items-center justify-between gap-4 py-2.5 text-sm text-plume/75">
              <span>{item}</span>
              <span aria-hidden className="h-2.5 w-20 shrink-0 rounded-full bg-plume/[0.08]" />
            </li>
          ))}
        </ul>
        <Link
          to={unlock.to}
          state={unlock.state}
          className="group mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-or px-5 py-3.5 text-[0.9375rem] font-semibold text-plume transition-[filter,transform] duration-150 hover:brightness-105 active:scale-[0.99]"
        >
          {unlock.label}
          <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" aria-hidden />
        </Link>
        {defaulted.length === 3 && (
          <p className="mt-4 text-xs leading-relaxed text-plume/55">
            {t('solar.defaultsNote', {
              notary: percent(defaulted[0].value),
              plusvalia: money(defaulted[1].value),
              dd: money(defaulted[2].value),
            })}
          </p>
        )}
      </div>
    </div>
  )
}
