import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { FileDown } from 'lucide-react'
import { useOrders } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { intlLocale } from '@/lib/utils'
import { AccountPanel } from './AccountPanel'

/** Every invoice issued to the student, newest first, each as its PDF. */
export function InvoicesSection() {
  const { t, i18n } = useTranslation()
  const { data: orders, isLoading } = useOrders()
  const locale = intlLocale(i18n.language)
  const date = new Intl.DateTimeFormat(locale, { dateStyle: 'medium' })
  // Invoices are in cents and show them: an invoice is exact to the cent.
  const money = new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' })

  const invoices = (orders ?? [])
    .filter((order) => order.invoice)
    .map((order) => ({ order, invoice: order.invoice! }))
    .sort((a, b) => b.invoice.issued_at.localeCompare(a.invoice.issued_at))

  return (
    <AccountPanel title={t('account.invoices.title')} lead={t('account.invoices.lead')}>
      {isLoading ? (
        <p className="text-sm text-foreground/60">{t('common.loading')}</p>
      ) : invoices.length === 0 ? (
        <p className="text-sm text-foreground/60">{t('account.invoices.empty')}</p>
      ) : (
        <ul className="divide-y divide-foreground/10">
          {invoices.map(({ order, invoice }) => (
            <li key={invoice.number} className="flex flex-wrap items-center gap-x-6 gap-y-2 py-4 first:pt-0 last:pb-0">
              <div className="min-w-0 flex-1">
                <p className="font-medium tabular-nums" dir="ltr">
                  {invoice.number}
                </p>
                <p className="bidi-auto truncate text-sm text-foreground/60">
                  {date.format(new Date(invoice.issued_at))} ·{' '}
                  {order.scope === 'full' ? order.course.title : t('orders.scopeModules', { count: order.modules?.length ?? 0 })}
                </p>
              </div>
              <p className="font-semibold tabular-nums">{money.format(invoice.total_gross_c / 100)}</p>
              <Button size="sm" variant="outline" asChild>
                {/* Plain navigation so the session cookie rides along. */}
                <a href={invoice.url}>
                  <FileDown className="h-4 w-4" aria-hidden />
                  {t('account.invoices.download')}
                </a>
              </Button>
            </li>
          ))}
        </ul>
      )}
      <p className="mt-6 text-sm leading-relaxed text-foreground/60">
        {t('account.invoices.hint')}{' '}
        <Link to="/account/billing" className="font-medium text-primary underline-offset-2 hover:underline">
          {t('account.nav.billing')}
        </Link>
      </p>
    </AccountPanel>
  )
}
