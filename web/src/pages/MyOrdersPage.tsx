import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { FileDown, Receipt, Upload } from 'lucide-react'
import { useOrders, type Order, type OrderStatus } from '@/api/orders'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { formatPrice, intlLocale } from '@/lib/utils'

const STATUS_VARIANT: Record<OrderStatus, 'default' | 'secondary' | 'destructive' | 'success' | 'outline'> = {
  pending: 'outline',
  awaiting_review: 'secondary',
  paid: 'success',
  failed: 'destructive',
  refunded: 'outline',
}

function isManual(order: Order) {
  return order.method === 'manual_ccp' || order.method === 'manual_baridimob'
}

function OrderAction({ order }: { order: Order }) {
  const { t } = useTranslation()

  if (order.status === 'paid') {
    return (
      <div className="flex flex-col items-end gap-1.5">
        <Button size="sm" asChild>
          <Link to={`/learn/${order.course.slug}`}>{t('orders.access')}</Link>
        </Button>
        {order.invoice && (
          <Button size="sm" variant="ghost" asChild className="text-muted-foreground">
            {/* Plain navigation so the Sanctum cookie rides along. */}
            <a href={order.invoice.url} title={order.invoice.number}>
              <FileDown className="h-3.5 w-3.5" />
              {t('orders.invoice')}
              <span className="hidden tabular-nums sm:inline">{order.invoice.number}</span>
            </a>
          </Button>
        )}
      </div>
    )
  }

  if (isManual(order) && (order.status === 'pending' || order.status === 'failed')) {
    return (
      <Button size="sm" variant="outline" asChild>
        <Link to={`/orders/${order.id}/pay`}>
          <Upload className="h-3.5 w-3.5" />
          {t('orders.sendReceipt')}
        </Link>
      </Button>
    )
  }

  if (isManual(order) && order.status === 'awaiting_review') {
    return (
      <Button size="sm" variant="ghost" asChild>
        <Link to={`/orders/${order.id}/pay`}>{t('orders.viewInstructions')}</Link>
      </Button>
    )
  }

  return null
}

export function MyOrdersPage() {
  const { t, i18n } = useTranslation()
  usePageTitle(t('orders.title'))
  const { data: orders, isLoading } = useOrders()

  const locale = i18n.language
  const dateFormatter = new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'medium' })

  return (
    <div className="container max-w-4xl py-8 sm:py-12">
      <h1 className="mb-8 text-3xl font-bold">{t('orders.title')}</h1>

      {isLoading ? (
        <p className="text-muted-foreground">{t('common.loading')}</p>
      ) : !orders?.length ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
            <Receipt className="h-10 w-10 text-muted-foreground" />
            <p className="text-muted-foreground">{t('orders.empty')}</p>
            <Button asChild>
              <Link to="/">{t('orders.browse')}</Link>
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => (
            <Card key={order.id}>
              <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                {order.course.cover_image && (
                  <img
                    src={order.course.cover_image}
                    alt=""
                    className="hidden aspect-video w-28 shrink-0 rounded-md object-cover sm:block"
                  />
                )}

                <div className="min-w-0 flex-1 space-y-1.5">
                  <p className="bidi-auto font-semibold leading-snug">{order.course.title}</p>
                  <p className="text-xs text-muted-foreground">
                    {t('orders.orderNumber', { id: order.id })} · {dateFormatter.format(new Date(order.created_at))}{' '}
                    · {t(`orders.method.${order.method}`)}
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                      {order.scope === 'full'
                        ? t('orders.scopeFull')
                        : t('orders.scopeModules', { count: order.modules?.length ?? 0 })}
                    </Badge>
                    <Badge variant={STATUS_VARIANT[order.status]}>{t(`orders.status.${order.status}`)}</Badge>
                  </div>
                </div>

                <div className="flex shrink-0 items-center justify-between gap-4 sm:flex-col sm:items-end sm:justify-center">
                  <p className="text-lg font-bold tabular-nums">
                    {formatPrice(order.amount, order.currency, locale)}
                  </p>
                  <OrderAction order={order} />
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
