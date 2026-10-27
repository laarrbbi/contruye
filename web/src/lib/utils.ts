import { type ClassValue, clsx } from 'clsx'
import type { TFunction } from 'i18next'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Maps an interface locale to the BCP-47 tag used for numbers and dates.
 * Spain is the market: Spanish is es-ES, and English is en-GB, which keeps
 * day-first dates instead of switching a buyer in Spain to US conventions.
 */
export function intlLocale(locale: string | undefined): string {
  if (locale === 'en') return 'en-GB'
  return 'es-ES'
}

/**
 * Prices are whole euros. Grouping is forced so 1490 reads "1.490 €", as the
 * server's e-mails and pages write it (App\Support\Money): browsers follow
 * CLDR, which leaves four-digit Spanish amounts ungrouped.
 */
export function formatPrice(amount: number, currency: 'EUR', locale: string) {
  return new Intl.NumberFormat(intlLocale(locale), {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
    useGrouping: 'always',
  }).format(amount)
}

export function formatNumber(value: number, locale: string) {
  return new Intl.NumberFormat(intlLocale(locale)).format(value)
}

export function formatDuration(seconds: number, t: TFunction): string | null {
  // Zero means the lesson has not been filmed yet — the column defaults to 0.
  // Rendering "0 min" on every lesson makes a full programme look empty, so
  // callers get null and show nothing instead.
  if (!seconds || seconds <= 0) return null

  const h = Math.floor(seconds / 3600)
  const m = Math.round((seconds % 3600) / 60)
  if (h > 0) return t('common.durationHM', { h, m: String(m).padStart(2, '0') })
  return t('common.durationM', { m })
}

/** Total runtime of a course, or null while nothing has been filmed. */
export function formatTotalDuration(totalSeconds: number | null | undefined, t: TFunction) {
  return formatDuration(totalSeconds ?? 0, t)
}
