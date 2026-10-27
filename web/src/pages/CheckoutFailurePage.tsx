import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { CircleX } from 'lucide-react'
import { useOrder } from '@/api/orders'
import { Button } from '@/components/ui/button'

export function CheckoutFailurePage() {
  const { t } = useTranslation()
  usePageTitle(t('checkout.failure.title'))
  const [searchParams] = useSearchParams()

  const orderId = Number(searchParams.get('order'))
  const validId = Number.isInteger(orderId) && orderId > 0
  const { data: order } = useOrder(validId ? orderId : null)

  return (
    <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
      <div className="flex h-20 w-20 items-center justify-center rounded-full bg-destructive/10">
        <CircleX className="h-10 w-10 text-destructive" />
      </div>
      <div className="max-w-md space-y-2">
        <h1 className="text-3xl font-bold">{t('checkout.failure.title')}</h1>
        <p className="text-muted-foreground">{t('checkout.failure.hint')}</p>
        {order && <p className="bidi-auto font-semibold">{order.course.title}</p>}
      </div>
      <div className="flex flex-wrap items-center justify-center gap-3">
        {order && (
          <Button asChild size="lg">
            <Link to={`/checkout/${order.course.slug}`}>{t('checkout.failure.retry')}</Link>
          </Button>
        )}
        <Button asChild variant="outline">
          <Link to="/orders">{t('checkout.failure.viewOrders')}</Link>
        </Button>
      </div>
    </div>
  )
}
