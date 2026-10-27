import { useEffect } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import i18n, { setLocale, type Locale } from '@/i18n'
import { storedLocale } from '@/i18n/locales'
import { localizedPath } from '@/i18n/paths'

/**
 * Wraps the public pages of one language (/…, /ar/…, /en/…) and keeps the
 * interface in the URL's language. Someone who chose Arabic or English and
 * opens a French URL goes to the same page at their language's URL instead;
 * search engines store no choice, so they always get the page they asked for.
 */
export function PublicLocale({ locale }: { locale: Locale }) {
  const location = useLocation()

  const stored = storedLocale()
  const redirect =
    locale === 'fr' && stored !== null && stored !== 'fr'
      ? localizedPath(location.pathname, stored) + location.search + location.hash
      : null

  // On arrival at a URL only. Reacting to a language change instead would
  // undo the switcher: the old page is still mounted when the language
  // changes, and would switch it straight back before the new URL loads.
  // (Hence the module's i18n, not useTranslation's, whose identity changes
  // with the language.)
  useEffect(() => {
    if (redirect === null && i18n.language !== locale) void setLocale(locale)
  }, [redirect, locale, location.pathname])

  if (redirect !== null) return <Navigate to={redirect} replace />
  return <Outlet />
}
