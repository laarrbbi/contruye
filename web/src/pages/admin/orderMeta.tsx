import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import type { OrderStatus } from '@/api/admin'
import { cn } from '@/lib/utils'

type BadgeVariant = 'default' | 'secondary' | 'success' | 'destructive' | 'outline'

const STATUS_VARIANT: Record<OrderStatus, BadgeVariant> = {
  pending: 'secondary',
  awaiting_review: 'default',
  paid: 'success',
  failed: 'destructive',
  refunded: 'outline',
}

/** Colored badge for an order status, labelled from admin.status.*. */
export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  const { t } = useTranslation()
  return <Badge variant={STATUS_VARIANT[status]}>{t(`admin.status.${status}`)}</Badge>
}

/** Mirrors Order::RECEIPT_REVIEW_HOURS: past this, a waiting receipt is flagged. */
export const RECEIPT_REVIEW_HOURS = 48

/**
 * How long a receipt has waited for review. The buyer has already sent the
 * money; past 48 h it turns red so it cannot be missed in the list.
 */
export function ReceiptWaiting({ since, className }: { since: string; className?: string }) {
  const { t } = useTranslation()
  const hours = Math.max(0, Math.floor((Date.now() - new Date(since).getTime()) / 3_600_000))
  const overdue = hours >= RECEIPT_REVIEW_HOURS
  return (
    <span className={cn('block text-xs', overdue ? 'font-semibold text-destructive' : 'text-muted-foreground', className)}>
      {hours < 24
        ? t('admin.orders.waitingHours', { count: hours })
        : t('admin.orders.waitingDays', { count: Math.floor(hours / 24) })}
    </span>
  )
}
