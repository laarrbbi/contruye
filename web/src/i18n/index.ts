import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import es from './es.json'
import { isLocale, rememberLocale, storedLocale, type Locale } from './locales'
import { isPublicPage, splitLocalePath } from './paths'

export { LOCALES, LOCALE_NAMES, type Locale } from './locales'

/**
 * On a public page, the URL decides: /en is English and the unprefixed pages
 * are Spanish. That is what search engines index and what people share.
 * Guessing from the browser there showed Googlebot, which renders with an
 * en-US browser, the English site on Spanish URLs.
 *
 * Elsewhere (the app's own screens): the stored choice, then the browser's
 * preference, then Spanish — so a first-time English speaker lands in English
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

  if (isPublicPage(path)) return 'es'

  for (const tag of navigator.languages ?? [navigator.language]) {
    const primary = tag?.slice(0, 2).toLowerCase()
    if (isLocale(primary)) return primary
  }

  return 'es'
}

const initial = initialLocale()

// Spanish is bundled: it is the authored locale and every fallback. English
// is fetched on demand, so a visitor downloads one language, not two.
const loaders: Record<Exclude<Locale, 'es'>, () => Promise<{ default: Record<string, unknown> }>> = {
  en: () => import('./en.json'),
}

async function ensureLoaded(locale: Locale) {
  if (locale === 'es' || i18n.hasResourceBundle(locale, 'translation')) return
  const bundle = await loaders[locale]()
  i18n.addResourceBundle(locale, 'translation', bundle.default)
}

i18n.use(initReactI18next).init({
  resources: {
    es: { translation: es },
  },
  lng: initial,
  // Spanish is the authored locale: a key missing anywhere resolves there.
  fallbackLng: 'es',
  interpolation: { escapeValue: false },
})

// Title and description belong to the page (PageTitleProvider), not here.
// Both interface languages read left to right; the app keeps its logical
// (start/end) layout, so a right-to-left language could be added later.
export function applyDirection(locale: string) {
  document.documentElement.lang = locale
  document.documentElement.dir = 'ltr'
}

export async function setLocale(locale: Locale) {
  // Stored first, synchronously: a page change made right after this call
  // must already see the new choice.
  rememberLocale(locale)
  await ensureLoaded(locale)
  await i18n.changeLanguage(locale)
  applyDirection(locale)
}

// Language is right from the first paint; main.tsx renders once the visitor's
// language has arrived, so English never flashes Spanish first.
applyDirection(initial)

export const i18nReady: Promise<void> = ensureLoaded(initial)
  .then(() => i18n.changeLanguage(initial))
  .then(() => applyDirection(initial))
  // Offline or a failed chunk: render in Spanish rather than not at all.
  .catch(() => {
    i18n.changeLanguage('es')
    applyDirection('es')
  })

export default i18n
