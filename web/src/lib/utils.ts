import { type ClassValue, clsx } from 'clsx'
import type { TFunction } from 'i18next'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Maps an interface locale to the BCP-47 tag used for numbers and dates.
 * Algeria is the market, so every variant is the local one — including
 * en-DZ, which keeps day-first dates and the local currency placement
 * rather than switching an Algerian buyer to US conventions.
 */
export function intlLocale(locale: string | undefined): string {
  if (locale === 'ar') return 'ar-DZ'
  if (locale === 'en') return 'en-DZ'
  return 'fr-DZ'
}

export function formatPrice(amount: number, currency: 'DZD' | 'EUR', locale: string) {
  return new Intl.NumberFormat(intlLocale(locale), {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
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
