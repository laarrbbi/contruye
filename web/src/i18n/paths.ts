import type { Locale } from './locales'

/**
 * One URL per language for the public pages, the ones search engines index:
 * French at the root (/, /formations, /courses/…), Arabic under /ar, English
 * under /en. The server renders the same URLs with their own head
 * (api/routes/seo.php, api/app/Support/LocalizedUrl.php).
 */
const PUBLIC_PAGE = /^\/(?:formations|courses\/[^/]+)?\/?$/

/** '/ar/formations' → { locale: 'ar', path: '/formations' }; '/login' → { locale: null, path: '/login' }. */
export function splitLocalePath(pathname: string): { locale: Exclude<Locale, 'fr'> | null; path: string } {
  const match = /^\/(ar|en)(?=\/|$)(.*)$/.exec(pathname)
  if (!match) return { locale: null, path: pathname }
  return { locale: match[1] as Exclude<Locale, 'fr'>, path: match[2] || '/' }
}

export function isPublicPage(path: string): boolean {
  return PUBLIC_PAGE.test(path)
}

export function localizedPath(path: string, locale: Locale): string {
  return locale === 'fr' ? path : `/${locale}${path === '/' ? '' : path}`
}
