import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlarmClock } from 'lucide-react'
import type { CourseDetail } from '@/api/courses'
import { phaseModules } from '@/lib/modules'
import { cn, formatPrice, intlLocale } from '@/lib/utils'

/**
 * The course's price, the way a buyer reads it at a glance: the price, a
 * higher price struck out, the saving, and how long the price lasts.
 *
 * Every figure is real (constitution I):
 * - during a campaign, the struck price is the course's price and the
 *   countdown runs to the campaign's own end, the same for everyone;
 * - otherwise, the struck price is what the modules cost bought one by one
 *   (the sum of their prices), which is what full access saves.
 */
export function coursePrices(course: Pick<CourseDetail, 'price_eur' | 'campaign' | 'modules'>) {
  const campaign = course.campaign && new Date(course.campaign.ends_at).getTime() > Date.now() ? course.campaign : null
  const price = campaign ? Math.round((course.price_eur * (100 - campaign.percent_off)) / 100) : course.price_eur
  const itemized = phaseModules(course.modules).reduce((sum, module) => sum + (module.price_eur ?? 0), 0)
  const struck = campaign ? course.price_eur : itemized > course.price_eur ? itemized : null
  const percent = struck ? Math.round((1 - price / struck) * 100) : 0

  return { campaign, price, struck, percent }
}

/** "19 días", "23 horas", "40 minutos": the largest unit left, in the reader's language. */
function timeLeft(endsAt: string, lang: string): string | null {
  const ms = new Date(endsAt).getTime() - Date.now()
  if (ms <= 0) return null
  const hours = ms / 3_600_000
  const [value, unit] = hours >= 48 ? [Math.floor(hours / 24), 'day'] : hours >= 1 ? [Math.floor(hours), 'hour'] : [Math.max(1, Math.floor(ms / 60_000)), 'minute']

  return new Intl.NumberFormat(intlLocale(lang), { style: 'unit', unit, unitDisplay: 'long' }).format(value)
}

export function PriceTag({
  course,
  size = 'lg',
  dark = false,
  className,
}: {
  course: Pick<CourseDetail, 'price_eur' | 'campaign' | 'modules'>
  size?: 'lg' | 'md' | 'sm'
  /** On a dark background: the countdown in a lighter red. */
  dark?: boolean
  className?: string
}) {
  const { t, i18n } = useTranslation()
  const lang = i18n.language
  const { campaign, price, struck, percent } = coursePrices(course)
  const [left, setLeft] = useState(() => (campaign ? timeLeft(campaign.ends_at, lang) : null))

  useEffect(() => {
    if (!campaign) return setLeft(null)
    const tick = () => setLeft(timeLeft(campaign.ends_at, lang))
    tick()
    const timer = window.setInterval(tick, 30_000)
    return () => window.clearInterval(timer)
  }, [campaign, lang])

  const money = (amount: number) => formatPrice(amount, 'EUR', lang)

  return (
    <div className={cn('min-w-0', className)}>
      <p className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
        <span
          className={cn(
            'titre leading-none tabular-nums',
            size === 'lg' && 'text-[2.25rem] lg:text-[2.75rem]',
            size === 'md' && 'text-[1.75rem]',
            size === 'sm' && 'text-[1.25rem]',
          )}
        >
          {money(price)}
        </span>
        {struck !== null && (
          <>
            <s className={cn('tabular-nums opacity-60', size === 'sm' ? 'text-sm' : 'text-lg')}>
              <span className="sr-only">{t('pricing.tag.before')} </span>
              {money(struck)}
            </s>
            {size !== 'sm' && percent > 0 && (
              <span className="text-[0.9375rem] opacity-70">
                {campaign ? t('pricing.tag.off', { percent }) : t('pricing.tag.vsModules', { percent })}
              </span>
            )}
          </>
        )}
      </p>
      {campaign && left && size !== 'sm' && (
        <p className={cn('mt-2 flex items-center gap-1.5 text-sm font-medium', dark ? 'text-[#ff8f80]' : 'text-[#b42318]')}>
          <AlarmClock aria-hidden className="h-4 w-4 shrink-0" strokeWidth={1.75} />
          {t('pricing.tag.left', { time: left })}
        </p>
      )}
    </div>
  )
}
