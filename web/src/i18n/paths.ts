import type { Locale } from './locales'

/**
 * One URL per language for the public pages, the ones search engines index:
 * Spanish at the root (/, /cursos, /cursos/…, /calculadora-precio-solar, /noticias, /noticias/…), every other language under its
 * code (/en, /fr, /ar, /ru, /uk). The server renders the same URLs with their
 * own head (api/routes/seo.php, api/app/Support/LocalizedUrl.php).
 */
const PUBLIC_PAGE = /^\/(?:cursos(?:\/[^/]+)?|calculadora-precio-solar|noticias(?:\/[^/]+)?|data-centers)?\/?$/

/** '/en/cursos' → { locale: 'en', path: '/cursos' }; '/login' → { locale: null, path: '/login' }. */
export function splitLocalePath(pathname: string): { locale: Exclude<Locale, 'es'> | null; path: string } {
  const match = /^\/(en|fr|ar|ru|uk)(?=\/|$)(.*)$/.exec(pathname)
  if (!match) return { locale: null, path: pathname }
  return { locale: match[1] as Exclude<Locale, 'es'>, path: match[2] || '/' }
}

export function isPublicPage(path: string): boolean {
  return PUBLIC_PAGE.test(path)
}

export function localizedPath(path: string, locale: Locale): string {
  return locale === 'es' ? path : `/${locale}${path === '/' ? '' : path}`
}

/** The solar calculator's page (the server renders it too: SeoPageController::SOLAR_PATH). */
export const SOLAR_CALCULATOR_PATH = '/calculadora-precio-solar'

/** The news (SeoPageController::NEWS_PATH): the list, and one page per piece under it. */
export const NEWS_PATH = '/noticias'

/** The data centers course, coming soon (SeoPageController::DATA_CENTERS_PATH). */
export const DATA_CENTERS_PATH = '/data-centers'
