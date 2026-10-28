import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, Globe, LogOut, LayoutDashboard, Receipt, ShieldCheck } from 'lucide-react'
import { ConstruyeLogo } from '@/components/brand/ConstruyeMark'
import { Button } from '@/components/ui/button'
import { useLogout, useUser } from '@/api/auth'
import { LOCALES, LOCALE_NAMES, setLocale, type Locale } from '@/i18n'
import { isPublicPage, localizedPath, splitLocalePath } from '@/i18n/paths'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

export function Header() {
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const logout = useLogout()
  const navigate = useNavigate()
  const location = useLocation()

  const current = (LOCALES.find((locale) => locale === i18n.language) ?? 'es') as Locale

  const chooseLocale = (locale: Locale) => {
    if (locale === current) return
    // setLocale stores the choice before anything else, so the page below
    // already sees it. (API data is refetched on any language change: main.tsx.)
    void setLocale(locale)
    // A public page has one URL per language: go to this one's.
    const { path } = splitLocalePath(location.pathname)
    if (isPublicPage(path)) navigate(localizedPath(path, locale) + location.search + location.hash)
  }

  return (
    <header className="sticky top-0 z-40 w-full border-b bg-white/95 backdrop-blur">
      <div className="container flex h-16 items-center justify-between">
        <Link to={localizedPath('/', current)} className="shrink-0">
          <span className="sr-only">{t('app.name')}</span>
          {/* Below 440px the wordmark gives way: logo, language, sign-in and
              sign-up did not fit a 390px phone and the whole site scrolled
              sideways. The mark alone still reads as Construye. */}
          <ConstruyeLogo wordmarkClassName="hidden min-[440px]:flex" />
        </Link>

        {/* Labels collapse to icons on phones but stay readable by screen
            readers (sr-only, not hidden). */}
        <nav className="flex min-w-0 items-center gap-1 sm:gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              {/* The visible code is part of the spoken name ("FR, Changer de
                  langue"), so voice control users can say what they see. */}
              <Button variant="ghost" size="sm" className="px-2 sm:px-3">
                <Globe className="h-4 w-4" aria-hidden />
                <span className="font-semibold uppercase">{current}</span>
                <span className="sr-only">{t('nav.changeLanguage')}</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-[10rem]">
              {LOCALES.map((locale) => (
                <DropdownMenuItem
                  key={locale}
                  onSelect={() => chooseLocale(locale)}
                  className="justify-between gap-3"
                >
                  {LOCALE_NAMES[locale]}
                  {locale === current && <Check className="h-4 w-4 shrink-0 text-primary" />}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>

          {user ? (
            <>
              <Button variant="ghost" size="sm" className="px-2 sm:px-3" asChild>
                <Link to="/dashboard">
                  <LayoutDashboard className="h-4 w-4" />
                  <span className="sr-only sm:not-sr-only">{t('nav.myCourses')}</span>
                </Link>
              </Button>
              <Button variant="ghost" size="sm" className="px-2 sm:px-3" asChild>
                <Link to="/orders">
                  <Receipt className="h-4 w-4" />
                  <span className="sr-only md:not-sr-only">{t('nav.myOrders')}</span>
                </Link>
              </Button>
              {user.role === 'admin' && (
                <Button variant="ghost" size="sm" className="px-2 sm:px-3" asChild>
                  <Link to="/admin">
                    <ShieldCheck className="h-4 w-4 md:hidden" />
                    <span className="sr-only md:not-sr-only">{t('nav.admin')}</span>
                  </Link>
                </Button>
              )}
              <Button
                variant="outline"
                size="sm"
                className="px-2 sm:px-3"
                onClick={() => logout.mutate(undefined, { onSuccess: () => navigate('/') })}
              >
                <LogOut className="h-4 w-4 rtl:-scale-x-100" />
                <span className="sr-only sm:not-sr-only">{t('nav.logout')}</span>
              </Button>
            </>
          ) : (
            <>
              <Button variant="ghost" size="sm" asChild>
                <Link to="/login">{t('nav.login')}</Link>
              </Button>
              <Button size="sm" asChild>
                <Link to="/register">
                  {/* One label at a time, so a screen reader hears one. */}
                  <span className="sm:hidden">{t('nav.registerShort')}</span>
                  <span className="hidden sm:inline">{t('nav.register')}</span>
                </Link>
              </Button>
            </>
          )}
        </nav>
      </div>
    </header>
  )
}
