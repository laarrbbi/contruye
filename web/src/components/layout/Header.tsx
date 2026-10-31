import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, ChevronDown, Globe, LogOut, LayoutDashboard, Receipt, ShieldCheck } from 'lucide-react'
import { ConstruyeLogo } from '@/components/brand/ConstruyeMark'
import { Button } from '@/components/ui/button'
import { useLogout, useUser } from '@/api/auth'
import { LOCALES, LOCALE_NAMES, isRtl, setLocale, type Locale } from '@/i18n'
import { isPublicPage, localizedPath, splitLocalePath } from '@/i18n/paths'
import { cn } from '@/lib/utils'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

/** Pages laid out as an app, a sidebar against the screen's edge: the header spans the screen too. */
const APP_SHELL = /^\/(learn|admin)(\/|$)/

/** "Javier Morales" → "JM"; one word → its first letter. */
function initials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean)
  const letters = words.length > 1 ? [words[0], words[words.length - 1]] : words
  return letters.map((word) => Array.from(word)[0]?.toUpperCase() ?? '').join('')
}

export function Header() {
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const logout = useLogout()
  const navigate = useNavigate()
  const location = useLocation()

  const current = (LOCALES.find((locale) => locale === i18n.language) ?? 'es') as Locale
  const appShell = APP_SHELL.test(location.pathname)

  const chooseLocale = (locale: Locale) => {
    if (locale === current) return
    // setLocale stores the choice before anything else, so the page below
    // already sees it. (API data is refetched on any language change: main.tsx.)
    void setLocale(locale)
    // A public page has one URL per language: go to this one's.
    const { path } = splitLocalePath(location.pathname)
    if (isPublicPage(path)) navigate(localizedPath(path, locale) + location.search + location.hash)
  }

  // The places a signed-in user goes. On a wide screen they sit next to the
  // logo; on a phone they move into the account menu.
  const links = user
    ? [
        // The player belongs to "my courses": it stays lit while learning.
        { to: '/dashboard', label: t('nav.myCourses'), icon: LayoutDashboard, also: /^\/learn(\/|$)/ },
        { to: '/orders', label: t('nav.myOrders'), icon: Receipt },
        ...(user.role === 'admin' ? [{ to: '/admin', label: t('nav.admin'), icon: ShieldCheck }] : []),
      ]
    : []

  return (
    <header className="sticky top-0 z-40 w-full border-b bg-white/90 backdrop-blur-md supports-[backdrop-filter]:bg-white/75">
      {/* On the player and the admin, the logo lines up with the sidebar
          below it; elsewhere it lines up with the page's own column. */}
      <div className={cn('flex h-16 items-center gap-6', appShell ? 'px-4 lg:px-6' : 'container')}>
        <Link to={localizedPath('/', current)} className="shrink-0">
          <span className="sr-only">{t('app.name')}</span>
          {/* Below 440px the wordmark gives way: logo, language, sign-in and
              sign-up did not fit a 390px phone and the whole site scrolled
              sideways. The mark alone still reads as Construye. */}
          <ConstruyeLogo wordmarkClassName="hidden min-[440px]:flex" />
        </Link>

        {links.length > 0 && (
          <nav aria-label={t('nav.main')} className="hidden items-center gap-1 md:flex">
            {links.map(({ to, label, also }) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) => {
                  const active = isActive || (also?.test(location.pathname) ?? false)
                  return cn(
                    'rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    active
                      ? 'bg-primary/[0.07] text-primary'
                      : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                  )
                }}
              >
                {label}
              </NavLink>
            ))}
          </nav>
        )}

        <div className="ms-auto flex min-w-0 items-center gap-1 sm:gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              {/* The visible code is part of the spoken name ("FR, Changer de
                  langue"), so voice control users can say what they see. */}
              <Button variant="ghost" size="sm" className="px-2 text-muted-foreground hover:text-foreground sm:px-3">
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
                  {/* Each name in its own language and script, so a screen
                      reader says "Русский" in Russian, not spelled out. */}
                  <span lang={locale} dir={isRtl(locale) ? 'rtl' : 'ltr'}>
                    {LOCALE_NAMES[locale]}
                  </span>
                  {locale === current && <Check className="h-4 w-4 shrink-0 text-primary" />}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>

          {user ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button
                  type="button"
                  className="group flex items-center gap-1.5 rounded-full p-0.5 pe-1.5 transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring data-[state=open]:bg-muted"
                >
                  <span
                    aria-hidden
                    className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground ring-2 ring-white"
                  >
                    {initials(user.name)}
                  </span>
                  <ChevronDown
                    aria-hidden
                    className="h-4 w-4 text-muted-foreground transition-transform duration-200 group-data-[state=open]:rotate-180"
                  />
                  <span className="sr-only">
                    {t('nav.account')} — {user.name}
                  </span>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="font-normal">
                  <div className="truncate text-sm font-semibold">{user.name}</div>
                  {/* An address reads left to right in every language. */}
                  <div dir="ltr" className="truncate text-start text-xs text-muted-foreground rtl:text-end">
                    {user.email}
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {links.map(({ to, label, icon: Icon }) => (
                  <DropdownMenuItem key={to} asChild className="gap-2">
                    <Link to={to}>
                      <Icon className="h-4 w-4 text-muted-foreground" aria-hidden />
                      {label}
                    </Link>
                  </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="gap-2"
                  onSelect={() => logout.mutate(undefined, { onSuccess: () => navigate('/') })}
                >
                  <LogOut className="h-4 w-4 text-muted-foreground rtl:-scale-x-100" aria-hidden />
                  {t('nav.logout')}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
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
        </div>
      </div>
    </header>
  )
}
