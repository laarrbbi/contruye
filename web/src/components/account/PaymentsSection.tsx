import { useTranslation } from 'react-i18next'
import { Building2, CreditCard, Smartphone, type LucideIcon } from 'lucide-react'
import { useCheckoutMethods, useOrders, type OrderMethod } from '@/api/orders'
import { intlLocale } from '@/lib/utils'
import { AccountPanel } from './AccountPanel'
import { OrdersList } from './OrdersList'

const METHOD_ICONS: Partial<Record<OrderMethod, LucideIcon>> = {
  stripe: CreditCard,
  manual_transfer: Building2,
  manual_bizum: Smartphone,
}

/**
 * The orders, then the ways to pay. No card is ever stored: Stripe takes it
 * on its own page at each payment, so there is no saved card to show or to
 * remove, and the page says so rather than pretending to a wallet.
 */
export function PaymentsSection() {
  const { t, i18n } = useTranslation()
  const { data: available } = useCheckoutMethods()
  const { data: orders } = useOrders()
  const date = new Intl.DateTimeFormat(intlLocale(i18n.language), { dateStyle: 'medium' })

  // The last time each method was used for an order that went through.
  const lastUsed = new Map<OrderMethod, string>()
  for (const order of orders ?? []) {
    if (order.status !== 'paid') continue
    const when = order.paid_at ?? order.created_at
    const previous = lastUsed.get(order.method)
    if (!previous || when > previous) lastUsed.set(order.method, when)
  }

  const methods = [...new Set<OrderMethod>([...(available ?? []), ...lastUsed.keys()])].filter((method) => method !== 'cash')

  return (
    <div className="space-y-6">
      <AccountPanel bare title={t('account.orders.title')} lead={t('account.orders.lead')}>
        <OrdersList />
      </AccountPanel>

      <AccountPanel title={t('account.methods.title')} lead={t('account.methods.lead')}>
        <ul className="grid gap-3 sm:grid-cols-2">
          {methods.map((method) => {
            const Icon = METHOD_ICONS[method] ?? CreditCard
            const used = lastUsed.get(method)
            return (
              <li key={method} className="flex gap-3 rounded-xl bg-foreground/[0.03] p-4">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-foreground/70 ring-1 ring-foreground/10">
                  <Icon className="h-4 w-4" aria-hidden />
                </span>
                <div className="min-w-0 space-y-1">
                  <p className="font-medium">{t(`orders.method.${method}`)}</p>
                  <p className="text-sm leading-relaxed text-foreground/60">{t(`account.methods.${method}`)}</p>
                  <p className="text-xs text-foreground/50">
                    {used ? t('account.methods.lastUsed', { date: date.format(new Date(used)) }) : t('account.methods.notUsed')}
                  </p>
                </div>
              </li>
            )
          })}
        </ul>
      </AccountPanel>
    </div>
  )
}
