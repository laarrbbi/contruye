import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BadgeCheck, CircleDollarSign, GraduationCap, Inbox, TrendingUp, Users } from 'lucide-react'
import { useAdminStats } from '@/api/admin'
import { Card, CardContent } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatPrice } from '@/lib/utils'
import { formatDate, useMethodLabel } from './lib'
import { OrderStatusBadge, RECEIPT_REVIEW_HOURS } from './orderMeta'

function StatCard({
  icon: Icon,
  label,
  value,
  hint,
  accent,
  alert,
}: {
  icon: typeof Users
  label: string
  value: string
  hint?: string
  accent?: boolean
  /** Shown in red under the hint: something is late. */
  alert?: string
}) {
  return (
    <Card>
      <CardContent className="flex items-start gap-4 p-5">
        <div
          className={
            accent
              ? 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground'
              : 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-primary'
          }
        >
          <Icon className="h-5 w-5" />
        </div>
        <div className="min-w-0">
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="text-2xl font-bold leading-tight">{value}</p>
          {hint && <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>}
          {alert && <p className="mt-0.5 text-xs font-semibold text-destructive">{alert}</p>}
        </div>
      </CardContent>
    </Card>
  )
}

export function AdminDashboardPage() {
  const { t, i18n } = useTranslation()
  const { data: stats, isLoading, isError } = useAdminStats()
  const methodLabel = useMethodLabel()

  if (isLoading) {
    return <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
  }
  if (isError || !stats) {
    return <p className="py-12 text-center text-destructive">{t('common.error')}</p>
  }

  const revenue = formatPrice(stats.revenue_this_month.EUR, 'EUR', i18n.language)

  const methodRows = Object.entries(stats.orders_by_method)

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">{t('admin.dashboard.title')}</h1>

      {/* Stat cards */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard
          icon={CircleDollarSign}
          label={t('admin.dashboard.revenueThisMonth')}
          value={revenue}
        />
        <StatCard icon={Users} label={t('admin.dashboard.students')} value={String(stats.students_total)} />
        <StatCard
          icon={GraduationCap}
          label={t('admin.dashboard.enrollments')}
          value={String(stats.enrollments_total)}
          hint={t('admin.dashboard.enrollmentsThisMonth', { count: stats.enrollments_this_month })}
        />
        <StatCard
          icon={TrendingUp}
          label={t('admin.dashboard.completionRate')}
          value={`${stats.completion_rate}%`}
        />
        {stats.orders_awaiting_review > 0 ? (
          <Link
            to="/admin/orders?status=awaiting_review"
            className="rounded-xl outline-none ring-primary focus-visible:ring-2"
          >
            <StatCard
              icon={Inbox}
              label={t('admin.dashboard.awaitingReview')}
              value={String(stats.orders_awaiting_review)}
              hint={t('admin.dashboard.reviewNow')}
              alert={
                stats.orders_awaiting_review_overdue > 0
                  ? t('admin.dashboard.overdue', {
                      count: stats.orders_awaiting_review_overdue,
                      hours: RECEIPT_REVIEW_HOURS,
                    })
                  : undefined
              }
              accent
            />
          </Link>
        ) : (
          <StatCard
            icon={BadgeCheck}
            label={t('admin.dashboard.awaitingReview')}
            value="0"
            hint={t('admin.dashboard.allClear')}
          />
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        {/* Recent orders */}
        {/* min-w-0: the table scrolls inside its card instead of widening the page. */}
        <section className="min-w-0 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-lg font-semibold">{t('admin.dashboard.recentOrders')}</h2>
            <Link to="/admin/orders" className="text-sm font-medium text-primary hover:underline">
              {t('admin.dashboard.viewAllOrders')}
            </Link>
          </div>
          {stats.recent_orders.length === 0 ? (
            <div className="rounded-lg border border-dashed py-12 text-center text-muted-foreground">
              {t('admin.dashboard.noOrders')}
            </div>
          ) : (
            <div className="overflow-x-auto rounded-lg border bg-card">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('admin.orders.table.customer')}</TableHead>
                    <TableHead>{t('admin.orders.table.amount')}</TableHead>
                    <TableHead>{t('admin.orders.table.status')}</TableHead>
                    <TableHead>{t('admin.orders.table.date')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {stats.recent_orders.map((order) => (
                    <TableRow key={order.id}>
                      <TableCell>
                        <div className="font-medium leading-tight">{order.user.name}</div>
                        <div className="text-xs text-muted-foreground" dir="ltr">
                          {order.user.email}
                        </div>
                      </TableCell>
                      <TableCell className="whitespace-nowrap tabular-nums">
                        {formatPrice(order.amount, order.currency, i18n.language)}
                      </TableCell>
                      <TableCell>
                        <OrderStatusBadge status={order.status} />
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">
                        {formatDate(order.created_at, i18n.language)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </section>

        {/* Orders by method */}
        <section className="space-y-3">
          <h2 className="text-lg font-semibold">{t('admin.dashboard.byMethod')}</h2>
          <Card>
            <CardContent className="space-y-3 p-5">
              {methodRows.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('admin.dashboard.noOrders')}</p>
              ) : (
                methodRows.map(([method, counts]) => (
                  <div key={method} className="flex items-center justify-between gap-3 text-sm">
                    <span className="min-w-0 truncate">{methodLabel(method as never)}</span>
                    <span className="shrink-0 tabular-nums text-muted-foreground">
                      {t('admin.dashboard.paidOfTotal', { paid: counts.paid, total: counts.total })}
                    </span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </section>
      </div>
    </div>
  )
}
