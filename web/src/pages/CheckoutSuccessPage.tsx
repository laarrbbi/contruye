import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CircleAlert, Hourglass, LoaderCircle, PartyPopper, RefreshCw } from 'lucide-react'
import { useOrder } from '@/api/orders'
import { Button } from '@/components/ui/button'

const POLL_INTERVAL_MS = 2500
const POLL_TIMEOUT_MS = 90_000

export function CheckoutSuccessPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()

  const orderId = Number(searchParams.get('order'))
  const validId = Number.isInteger(orderId) && orderId > 0

  const [timedOut, setTimedOut] = useState(false)
  useEffect(() => {
    const timer = setTimeout(() => setTimedOut(true), POLL_TIMEOUT_MS)
    return () => clearTimeout(timer)
  }, [])

  // Poll every 2.5s while the payment provider's webhook has not settled the
  // order yet; give up (but keep the page usable) after 90 seconds.
  const { data: order, isLoading, isError, refetch, isFetching } = useOrder(
    validId ? orderId : null,
    (query) => {
      if (timedOut) return false
      const status = query.state.data?.status
      return status === undefined || status === 'pending' ? POLL_INTERVAL_MS : false
    },
  )

  if (!validId || isError) {
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <CircleAlert className="h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">{t('common.error')}</p>
        <Button asChild variant="outline">
          <Link to="/orders">{t('checkout.success.viewOrders')}</Link>
        </Button>
      </div>
    )
  }

  if (isLoading || !order) {
    return (
      <div className="container flex min-h-[50vh] items-center justify-center text-muted-foreground">
        {t('common.loading')}
      </div>
    )
  }

  if (order.status === 'paid') {
    return (
      <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-success/10">
          <PartyPopper className="h-10 w-10 text-success" />
        </div>
        <div className="space-y-2">
          <h1 className="text-3xl font-bold">{t('checkout.success.paidTitle')}</h1>
          <p className="text-muted-foreground">{t('checkout.success.paidHint')}</p>
          <p className="bidi-auto font-semibold">{order.course.title}</p>
        </div>
        <Button asChild size="lg" className="font-semibold">
          <Link to={`/learn/${order.course.slug}`}>{t('checkout.success.start')}</Link>
        </Button>
      </div>
    )
  }

  if (order.status === 'failed') {
    return (
      <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-destructive/10">
          <CircleAlert className="h-10 w-10 text-destructive" />
        </div>
        <div className="space-y-2">
          <h1 className="text-3xl font-bold">{t('checkout.success.failedTitle')}</h1>
          <p className="text-muted-foreground">{t('checkout.success.failedHint')}</p>
        </div>
        <div className="flex flex-wrap items-center justify-center gap-3">
          <Button asChild size="lg">
            <Link to={`/checkout/${order.course.slug}`}>{t('checkout.success.retry')}</Link>
          </Button>
          <Button asChild variant="outline">
            <Link to="/orders">{t('checkout.success.viewOrders')}</Link>
          </Button>
        </div>
      </div>
    )
  }

  if (order.status === 'pending') {
    if (timedOut) {
      return (
        <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-secondary">
            <Hourglass className="h-10 w-10 text-muted-foreground" />
          </div>
          <div className="max-w-md space-y-2">
            <h1 className="text-2xl font-bold">{t('checkout.success.timeoutTitle')}</h1>
            <p className="text-muted-foreground">{t('checkout.success.timeoutHint')}</p>
          </div>
          <div className="flex flex-wrap items-center justify-center gap-3">
            <Button onClick={() => refetch()} disabled={isFetching}>
              <RefreshCw className={isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
              {t('checkout.success.refresh')}
            </Button>
            <Button asChild variant="outline">
              <Link to="/orders">{t('checkout.success.viewOrders')}</Link>
            </Button>
          </div>
        </div>
      )
    }

    return (
      <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
        <LoaderCircle className="h-12 w-12 animate-spin text-primary" />
        <div className="space-y-2">
          <h1 className="text-2xl font-bold">{t('checkout.success.checking')}</h1>
          <p className="text-muted-foreground">{t('checkout.success.checkingHint')}</p>
        </div>
      </div>
    )
  }

  // awaiting_review / refunded: not a webhook-confirmed flow, point to orders.
  return (
    <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
      <div className="flex h-20 w-20 items-center justify-center rounded-full bg-secondary">
        <Hourglass className="h-10 w-10 text-muted-foreground" />
      </div>
      <div className="max-w-md space-y-2">
        <h1 className="text-2xl font-bold">{t('checkout.success.reviewTitle')}</h1>
        <p className="text-muted-foreground">{t('checkout.success.reviewHint')}</p>
      </div>
      <Button asChild variant="outline">
        <Link to="/orders">{t('checkout.success.viewOrders')}</Link>
      </Button>
    </div>
  )
}
