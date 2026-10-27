import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import type { OrderMethod } from '@/api/admin'
import { intlLocale } from '@/lib/utils'

/**
 * Maps a Laravel 422 validation error onto react-hook-form fields.
 * Returns a generic error message to display (or null when every error
 * was attached to a field / there is nothing to show).
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fallbackMessage: string,
  fieldMap?: Record<string, Path<T>>,
  /** Fields the form actually registers. Anything else has nowhere to render. */
  knownFields?: readonly string[],
): string | null {
  if (isAxiosError(error) && error.response?.status === 422) {
    const data = error.response.data as { message?: string; errors?: Record<string, string[]> }
    const entries = Object.entries(data.errors ?? {})
    if (entries.length > 0) {
      const orphaned: string[] = []

      for (const [field, messages] of entries) {
        const target = (fieldMap?.[field] ?? field) as Path<T>

        // Setting an error on a field the form does not register attaches it
        // to nothing: the dialog refuses to close and says why nowhere. Show
        // those messages instead of swallowing them.
        if (knownFields && !knownFields.includes(target as string)) {
          orphaned.push(messages[0])
          continue
        }

        setError(target, { type: 'server', message: messages[0] })
      }

      return orphaned.length > 0 ? orphaned.join(' ') : null
    }
    return data.message ?? fallbackMessage
  }
  return fallbackMessage
}

/** Formats an ISO date (or date-time) string for display in the current locale. */
export function formatDate(iso: string | null | undefined, locale: string): string {
  if (!iso) return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(intlLocale(locale))
}

/** Extracts the YYYY-MM-DD part of an ISO string for <input type="date">. */
export function toDateInputValue(iso: string | null | undefined): string {
  if (!iso) return ''
  return iso.slice(0, 10)
}

export { useDebouncedValue } from '@/hooks/useDebouncedValue'

/** Hook returning a localized label for a payment method (admin.method.*). */
export function useMethodLabel() {
  const { t } = useTranslation()
  return (method: OrderMethod) => t(`admin.method.${method}`)
}
