import { Suspense, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { RouteFallback } from '@/components/RouteFallback'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { BookOpen, Handshake, LayoutDashboard, Megaphone, Menu, Receipt, ShoppingCart, Star, TicketPercent, Users, X, Building2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

interface NavItem {
  to: string
  end?: boolean
  labelKey: string
  icon: typeof LayoutDashboard
}

const navItems: NavItem[] = [
  { to: '/admin', end: true, labelKey: 'admin.nav.dashboard', icon: LayoutDashboard },
  { to: '/admin/courses', labelKey: 'admin.nav.courses', icon: BookOpen },
  { to: '/admin/orders', labelKey: 'admin.nav.orders', icon: ShoppingCart },
  { to: '/admin/invoices', labelKey: 'admin.nav.invoices', icon: Receipt },
  { to: '/admin/students', labelKey: 'admin.nav.students', icon: Users },
  { to: '/admin/reviews', labelKey: 'admin.nav.reviews', icon: Star },
  { to: '/admin/campaigns', labelKey: 'admin.nav.campaigns', icon: Megaphone },
  { to: '/admin/partners', labelKey: 'admin.nav.partners', icon: Handshake },
  { to: '/admin/coupons', labelKey: 'admin.nav.coupons', icon: TicketPercent },
  { to: '/admin/business', labelKey: 'admin.nav.business', icon: Building2 },
]

function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()

  return (
    <nav aria-label={t('admin.title')} className="flex flex-col gap-1 p-4">
      {navItems.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.end}
          onClick={onNavigate}
          className={({ isActive }) =>
            cn(
              'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
              isActive
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:bg-secondary hover:text-secondary-foreground',
            )
          }
        >
          <item.icon className="h-4 w-4 shrink-0" />
          {t(item.labelKey)}
        </NavLink>
      ))}
    </nav>
  )
}

export function AdminLayout() {
  const { t } = useTranslation()
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  const activeItem = [...navItems]
    .reverse()
    .find((item) => (item.end ? location.pathname === item.to : location.pathname.startsWith(item.to)))
  // One title for every admin page: "Commandes · Administration — Ebni Academy".
  usePageTitle(activeItem ? `${t(activeItem.labelKey)} · ${t('admin.title')}` : t('admin.title'))

  return (
    <div className="flex min-h-[calc(100vh-4rem)]">
      {/* Desktop sidebar */}
      <aside className="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 border-e bg-card md:block">
        <div className="px-7 pt-6 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          {t('admin.title')}
        </div>
        <SidebarNav />
      </aside>

      <div className="min-w-0 flex-1">
        {/* Mobile top bar */}
        <div className="border-b bg-card md:hidden">
          <div className="flex h-12 items-center justify-between px-4">
            <span className="text-sm font-semibold">
              {t('admin.title')}
              {activeItem ? ` · ${t(activeItem.labelKey)}` : ''}
            </span>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => setMobileOpen((open) => !open)}
              aria-label={t('admin.nav.toggleMenu')}
              aria-expanded={mobileOpen}
            >
              {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </Button>
          </div>
          {mobileOpen && (
            <div className="border-t">
              <SidebarNav onNavigate={() => setMobileOpen(false)} />
            </div>
          )}
        </div>

        {/* Not a <main>: this already sits inside the site layout's <main>,
            and a page has exactly one. */}
        <div className="p-4 sm:p-6 lg:p-8">
          {/* Suspense here too, so the admin sidebar stays while a page loads. */}
          <Suspense fallback={<RouteFallback />}>
            <Outlet />
          </Suspense>
        </div>
      </div>
    </div>
  )
}
