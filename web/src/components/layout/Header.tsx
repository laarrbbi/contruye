import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  ArrowUpRight,
  Building2,
  Calculator,
  Check,
  Server,
  ChevronDown,
  Globe,
  Handshake,
  HardHat,
  LayoutDashboard,
  LogOut,
  Menu,
  Newspaper,
  ShieldCheck,
  UserRound,
  type LucideIcon,
} from 'lucide-react'
import { ConstruyeLogo } from '@/components/brand/ConstruyeMark'
import { Button } from '@/components/ui/button'
import { useLogout, useUser } from '@/api/auth'
import { LOCALES, LOCALE_NAMES, isRtl, setLocale, type Locale } from '@/i18n'
import { DATA_CENTERS_PATH, NEWS_PATH, SOLAR_CALCULATOR_PATH, isPublicPage, localizedPath, splitLocalePath } from '@/i18n/paths'
import { FICHAJE_EMPLEO_URL, FICHAJE_OBRAS_URL } from '@/lib/fichaje'
import { useHeaderTone } from '@/lib/headerTone'
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

interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  /** Other paths under which the item stays lit. */
  also?: RegExp
  /** Another site (Construye Fichaje): opens in a new tab, marked as such. */
  external?: boolean
  /** In the account menu only, not in the bar: there is no room for it beside the sections. */
  menuOnly?: boolean
  /** Not open yet: a small «Pronto» tag beside it. */
  soon?: boolean
}

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

  // Over the sales page's night sky the bar is a sheet of tracing paper: the
  // drawing shows through and the text turns white (lib/headerTone.ts).
  const tone = useHeaderTone()
  const night = tone === 'night'
  const quiet = night ? 'text-blanc hover:bg-blanc/10 hover:text-blanc focus-visible:ring-blanc focus-visible:ring-offset-nuit-950' : undefined

  const chooseLocale = (locale: Locale) => {
    if (locale === current) return
    // setLocale stores the choice before anything else, so the page below
    // already sees it. (API data is refetched on any language change: main.tsx.)
    void setLocale(locale)
    // A public page has one URL per language: go to this one's.
    const { path } = splitLocalePath(location.pathname)
    if (isPublicPage(path)) navigate(localizedPath(path, locale) + location.search + location.hash)
  }

  // The sections everyone can open: the plot valuator (Plantilla 1), the
  // news, the two sides of Construye Fichaje (a separate site), and the next
  // course, announced.
  const sections: NavItem[] = [
    { to: localizedPath(SOLAR_CALCULATOR_PATH, current), label: t('nav.calculator'), icon: Calculator },
    // NavLink keeps it lit on each piece of news too (/noticias/…).
    { to: localizedPath(NEWS_PATH, current), label: t('nav.news'), icon: Newspaper },
    { to: FICHAJE_EMPLEO_URL, label: t('nav.empleo'), icon: HardHat, external: true },
    { to: FICHAJE_OBRAS_URL, label: t('nav.obras'), icon: Building2, external: true },
    { to: localizedPath(DATA_CENTERS_PATH, current), label: t('nav.dataCenters'), icon: Server, soon: true },
  ]

  // The places of a signed-in user.
  const own: NavItem[] = user
    ? [
        // The player belongs to "my courses": it stays lit while learning.
        { to: '/dashboard', label: t('nav.myCourses'), icon: LayoutDashboard, also: /^\/learn(\/|$)/ },
        { to: '/partners', label: t('nav.partners'), icon: Handshake },
        { to: '/account', label: t('nav.account'), icon: UserRound, menuOnly: true },
        ...(user.role === 'admin' ? [{ to: '/admin', label: t('nav.admin'), icon: ShieldCheck, menuOnly: true }] : []),
      ]
    : []

  // On a wide screen (1280px and more) everything sits next to the logo, the
  // sections after «Mis cursos» and «Partners»; on a smaller one it moves into
  // a menu (the account menu once signed in).
  const bar: NavItem[] = (user ? [...own.slice(0, 2), ...sections, ...own.slice(2)] : sections).filter((item) => !item.menuOnly)

  const soonTag = (onNight: boolean) => (
    <span className={cn('ms-1 rounded-full bg-or/20 px-1.5 py-px text-[0.625rem] font-semibold uppercase tracking-wide', onNight ? 'text-or' : 'text-or-fonce')}>
      {t('nav.soon')}
    </span>
  )

  const barClass = (active: boolean) =>
    cn(
      'inline-flex items-center gap-1 whitespace-nowrap rounded-md px-2.5 py-2 text-sm font-medium transition-colors duration-150',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
      night
        ? cn('text-blanc hover:bg-blanc/10', active && 'bg-blanc/10')
        : active
          ? 'bg-primary/[0.07] text-primary'
          : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    )

  /** One entry of a dropdown menu: a page here, or the other site in a new tab. */
  const menuEntry = ({ to, label, icon: Icon, external, soon }: NavItem) => (
    <DropdownMenuItem key={to} asChild className="gap-2">
      {external ? (
        <a href={to} target="_blank" rel="noopener">
          <Icon className="h-4 w-4 text-muted-foreground" aria-hidden />
          <span className="flex-1">{label}</span>
          <ArrowUpRight className="h-3.5 w-3.5 text-muted-foreground rtl:-scale-x-100" aria-hidden />
          <span className="sr-only">{t('nav.newTab')}</span>
        </a>
      ) : (
        <Link to={to}>
          <Icon className="h-4 w-4 text-muted-foreground" aria-hidden />
          {label}
          {soon && soonTag(false)}
        </Link>
      )}
    </DropdownMenuItem>
  )

  return (
    <header
      className={cn(
        'sticky top-0 z-40 w-full border-b transition-colors duration-500 ease-out-strong',
        // On the sales page's paper the bar is the sheet itself, with no band:
        // the same cream, veiled enough that the text passing under it does
        // not show through the bar's own.
        night
          ? 'border-blanc/10 bg-sombre-950/55 text-blanc backdrop-blur'
          : tone === 'paper'
            ? 'border-transparent bg-creme-100/80 text-plume backdrop-blur-md'
            : 'bg-white/90 backdrop-blur-md supports-[backdrop-filter]:bg-white/75',
      )}
    >
      {/* On the player and the admin, the logo lines up with the sidebar
          below it; elsewhere it lines up with the page's own column. */}
      <div className={cn('flex h-16 items-center gap-6', appShell ? 'px-4 lg:px-6' : 'container')}>
        <Link to={localizedPath('/', current)} className="shrink-0">
          <span className="sr-only">{t('app.name')}</span>
          {/* Below 440px the wordmark gives way: logo, language, sign-in and
              sign-up did not fit a 390px phone and the whole site scrolled
              sideways. The mark alone still reads as Construye. */}
          <ConstruyeLogo variant={night ? 'inherit' : 'brand'} wordmarkClassName="hidden min-[440px]:flex" />
        </Link>

        <nav aria-label={t('nav.main')} className="-ms-2 hidden min-w-0 items-center gap-0.5 xl:flex">
          {bar.map(({ to, label, also, external, soon }) =>
            external ? (
              <a key={to} href={to} target="_blank" rel="noopener" className={barClass(false)}>
                {label}
                <ArrowUpRight className="h-3.5 w-3.5 opacity-60 rtl:-scale-x-100" aria-hidden />
                <span className="sr-only">{t('nav.newTab')}</span>
              </a>
            ) : (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) => barClass(isActive || (also?.test(location.pathname) ?? false))}
              >
                {label}
                {soon && soonTag(night)}
              </NavLink>
            ),
          )}
        </nav>

        <div className="ms-auto flex min-w-0 items-center gap-1 sm:gap-2">
          {/* A visitor's menu below the width where the sections fit in the bar. */}
          {!user && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className={cn('px-2 xl:hidden', quiet ?? 'text-muted-foreground hover:text-foreground')}>
                  <Menu className="h-5 w-5" aria-hidden />
                  <span className="sr-only">{t('nav.menu')}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-60">
                {sections.map(menuEntry)}
                {/* On a phone «Iniciar sesión» leaves the bar for here. */}
                <DropdownMenuSeparator className="sm:hidden" />
                <DropdownMenuItem asChild className="gap-2 sm:hidden">
                  <Link to="/login">
                    <UserRound className="h-4 w-4 text-muted-foreground" aria-hidden />
                    {t('nav.login')}
                  </Link>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              {/* The visible code is part of the spoken name ("FR, Changer de
                  langue"), so voice control users can say what they see. */}
              <Button variant="ghost" size="sm" className={cn('px-2 sm:px-3', quiet ?? 'text-muted-foreground hover:text-foreground')}>
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
                  className={cn(
                    'group flex items-center gap-1.5 rounded-full p-0.5 pe-1.5 transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2',
                    night ? 'hover:bg-blanc/10 focus-visible:ring-blanc data-[state=open]:bg-blanc/10' : 'hover:bg-muted focus-visible:ring-ring data-[state=open]:bg-muted',
                  )}
                >
                  <span
                    aria-hidden
                    className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground ring-2 ring-white"
                  >
                    {initials(user.name)}
                  </span>
                  <ChevronDown
                    aria-hidden
                    className={cn('h-4 w-4 transition-transform duration-200 group-data-[state=open]:rotate-180', night ? 'text-blanc/80' : 'text-muted-foreground')}
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
                {own.map(menuEntry)}
                {/* The sections, here too below the width where the bar has them. */}
                <DropdownMenuSeparator className="xl:hidden" />
                <div className="xl:hidden">{sections.map(menuEntry)}</div>
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
              <Button variant="ghost" size="sm" className={cn('hidden sm:inline-flex', quiet)} asChild>
                <Link to="/login">{t('nav.login')}</Link>
              </Button>
              <Button
                size="sm"
                className={cn(night && 'bg-blanc text-encre hover:bg-white focus-visible:ring-blanc focus-visible:ring-offset-nuit-950')}
                asChild
              >
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
