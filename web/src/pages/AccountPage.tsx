import { NavLink, Navigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CreditCard, Download, FileText, IdCard, ShieldCheck, UserRound, type LucideIcon } from 'lucide-react'
import { useUser } from '@/api/auth'
import { PageHead } from '@/components/inside/PageHead'
import { BillingSection } from '@/components/account/BillingSection'
import { DataSection } from '@/components/account/DataSection'
import { InvoicesSection } from '@/components/account/InvoicesSection'
import { PaymentsSection } from '@/components/account/PaymentsSection'
import { ProfileSection } from '@/components/account/ProfileSection'
import { SecuritySection } from '@/components/account/SecuritySection'
import { usePageTitle } from '@/hooks/usePageTitle'
import { cn } from '@/lib/utils'

const SECTIONS = ['profile', 'billing', 'invoices', 'orders', 'security', 'data'] as const
type Section = (typeof SECTIONS)[number]

const ICONS: Record<Section, LucideIcon> = {
  profile: UserRound,
  billing: IdCard,
  invoices: FileText,
  orders: CreditCard,
  security: ShieldCheck,
  data: Download,
}

function isSection(value: string | undefined): value is Section {
  return SECTIONS.includes(value as Section)
}

/**
 * Everything about the student's account in one place: their details, what
 * goes on their invoices, the invoices themselves, their orders and how they
 * pay, the devices they are signed in on, and a copy of their data. One
 * address per section (/account/invoices…), so an e-mail can link straight to
 * the right one.
 */
export function AccountPage() {
  const { t } = useTranslation()
  const { section } = useParams()
  const { data: user } = useUser()
  const current: Section = isSection(section) ? section : 'profile'
  usePageTitle(`${t(`account.nav.${current}`)} · ${t('account.title')}`)

  if (section !== undefined && !isSection(section)) return <Navigate to="/account" replace />

  return (
    <div className="container py-12 sm:py-16 lg:py-20">
      <PageHead eyebrow={t('account.eyebrow')} title={t('account.title')} lead={user?.name} />

      <div className="grid gap-8 lg:grid-cols-[13.5rem_minmax(0,1fr)] lg:gap-12">
        {/* A column of links on a wide screen; a row that scrolls on a phone. */}
        <nav aria-label={t('account.title')} className="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:overflow-visible lg:px-0">
          <ul className="flex gap-1 lg:sticky lg:top-24 lg:flex-col">
            {SECTIONS.map((key) => {
              const Icon = ICONS[key]
              return (
                <li key={key} className="shrink-0">
                  <NavLink
                    to={key === 'profile' ? '/account' : `/account/${key}`}
                    end
                    className={({ isActive }) =>
                      cn(
                        'flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        isActive || (key === 'profile' && current === 'profile')
                          ? 'bg-foreground/[0.06] text-foreground'
                          : 'text-foreground/60 hover:bg-foreground/[0.04] hover:text-foreground',
                      )
                    }
                  >
                    <Icon className="h-4 w-4 shrink-0" aria-hidden />
                    {t(`account.nav.${key}`)}
                  </NavLink>
                </li>
              )
            })}
          </ul>
        </nav>

        <div className="min-w-0 max-w-3xl">
          {current === 'profile' && <ProfileSection />}
          {current === 'billing' && <BillingSection />}
          {current === 'invoices' && <InvoicesSection />}
          {current === 'orders' && <PaymentsSection />}
          {current === 'security' && <SecuritySection />}
          {current === 'data' && <DataSection />}
        </div>
      </div>
    </div>
  )
}
