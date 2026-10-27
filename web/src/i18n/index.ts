import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import fr from './fr.json'
import { isLocale, rememberLocale, storedLocale, type Locale } from './locales'
import { isPublicPage, splitLocalePath } from './paths'

export { LOCALES, LOCALE_NAMES, type Locale } from './locales'

const RTL_LOCALES: readonly Locale[] = ['ar']

/**
 * On a public page, the URL decides: /ar is Arabic, /en English, and the
 * unprefixed pages are French. That is what search engines index and what
 * people share. Guessing from the browser there showed Googlebot, which
 * renders with an en-US browser, the English site on French URLs.
 *
 * Elsewhere (the app's own screens): the stored choice, then the browser's
 * preference, then French — so a first-time English speaker lands in English
 * without hunting for the switcher.
 */
function initialLocale(): Locale {
  const { locale: fromUrl, path } = splitLocalePath(window.location.pathname)
  if (fromUrl && isPublicPage(path)) {
    rememberLocale(fromUrl)
    return fromUrl
  }

  const stored = storedLocale()
  if (stored) return stored

  if (isPublicPage(path)) return 'fr'

  for (const tag of navigator.languages ?? [navigator.language]) {
    const primary = tag?.slice(0, 2).toLowerCase()
    if (isLocale(primary)) return primary
  }

  return 'fr'
}

const initial = initialLocale()

// French is bundled: it is the authored locale and every fallback. Arabic and
// English are fetched on demand, so a visitor downloads one language, not three.
const loaders: Record<Exclude<Locale, 'fr'>, () => Promise<{ default: Record<string, unknown> }>> = {
  ar: () => import('./ar.json'),
  en: () => import('./en.json'),
}

async function ensureLoaded(locale: Locale) {
  if (locale === 'fr' || i18n.hasResourceBundle(locale, 'translation')) return
  const bundle = await loaders[locale]()
  i18n.addResourceBundle(locale, 'translation', bundle.default)
}

i18n.use(initReactI18next).init({
  resources: {
    fr: { translation: fr },
  },
  lng: initial,
  // French is the authored locale: a key missing anywhere resolves there.
  fallbackLng: 'fr',
  interpolation: { escapeValue: false },
})

// Title and description belong to the page (PageTitleProvider), not here.
export function applyDirection(locale: string) {
  document.documentElement.lang = locale
  document.documentElement.dir = RTL_LOCALES.includes(locale as Locale) ? 'rtl' : 'ltr'
}

export async function setLocale(locale: Locale) {
  // Stored first, synchronously: a page change made right after this call
  // must already see the new choice.
  rememberLocale(locale)
  await ensureLoaded(locale)
  await i18n.changeLanguage(locale)
  applyDirection(locale)
}

// Direction and lang are right from the first paint; main.tsx renders once the
// visitor's language has arrived, so Arabic never flashes French and LTR first.
applyDirection(initial)

export const i18nReady: Promise<void> = ensureLoaded(initial)
  .then(() => i18n.changeLanguage(initial))
  .then(() => applyDirection(initial))
  // Offline or a failed chunk: render in French rather than not at all.
  .catch(() => {
    i18n.changeLanguage('fr')
    applyDirection('fr')
  })

export default i18n
