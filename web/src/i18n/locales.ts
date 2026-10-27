/** Every locale the interface ships in, in switcher order. */
export const LOCALES = ['fr', 'ar', 'en'] as const
export type Locale = (typeof LOCALES)[number]

/** Endonyms: a language is always offered in its own language. */
export const LOCALE_NAMES: Record<Locale, string> = {
  fr: 'Français',
  ar: 'العربية',
  en: 'English',
}

export function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (LOCALES as readonly string[]).includes(value)
}

/** The language the visitor chose last, if storage is readable. */
export function storedLocale(): Locale | null {
  try {
    const stored = localStorage.getItem('locale')
    return isLocale(stored) ? stored : null
  } catch {
    return null
  }
}

export function rememberLocale(locale: Locale) {
  try {
    localStorage.setItem('locale', locale)
  } catch {
    // Private mode or storage blocked: the URL still carries the language.
  }
}
