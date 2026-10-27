import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, ChevronLeft, ChevronRight, Download, FileDown, FilePlus2, FileText, Mail, Search, X } from 'lucide-react'
import {
  adminOrdersExportUrl,
  useAdminOrder,
  useAdminOrders,
  useApproveOrder,
  useGrantOrder,
  useRejectOrder,
  useSendInvoice,
  type AdminOrder,
  type AdminOrderFilters,
  type OrderMethod,
  type OrderStatus,
} from '@/api/admin'
import { apiErrorMessage } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { formatPrice } from '@/lib/utils'
import { formatDate, useDebouncedValue, useMethodLabel } from './lib'
import { InvoiceFormDialog, type InvoiceFormTarget } from './InvoiceFormDialog'
import { OrderStatusBadge, ReceiptWaiting } from './orderMeta'

const STATUSES: OrderStatus[] = ['pending', 'awaiting_review', 'paid', 'failed', 'refunded']
const METHODS: OrderMethod[] = ['chargily', 'stripe', 'manual_ccp', 'manual_baridimob', 'cash']

const ALL = 'all'

type ConfirmAction = { order: AdminOrder; type: 'approve' | 'grant' }

function OrderDetailDialog({
  orderId,
  onOpenChange,
}: {
  orderId: number | null
  onOpenChange: (open: boolean) => void
}) {
  const { t, i18n } = useTranslation()
  const methodLabel = useMethodLabel()
  const { data: order, isLoading } = useAdminOrder(orderId)

  const [invoiceTarget, setInvoiceTarget] = useState<InvoiceFormTarget | null>(null)
  const sendInvoice = useSendInvoice()
  const [sendState, setSendState] = useState<'sent' | 'error' | null>(null)

  // The dialog is mounted once for every order — the "sent" badge must not
  // survive from one order to the next.
  useEffect(() => setSendState(null), [orderId])

  return (
    <>
    <Dialog open={orderId !== null} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-lg overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('admin.orders.detail.title', { id: orderId ?? '' })}</DialogTitle>
          <DialogDescription>{order?.course.title}</DialogDescription>
        </DialogHeader>

        {isLoading || !order ? (
          <p className="py-8 text-center text-muted-foreground">{t('common.loading')}</p>
        ) : (
          <div className="space-y-4 text-sm">
            <dl className="grid grid-cols-2 gap-x-4 gap-y-3">
              <div className="col-span-2">
                <dt className="text-xs text-muted-foreground">{t('admin.orders.table.customer')}</dt>
                <dd className="font-medium">{order.user.name}</dd>
                <dd dir="ltr" className="text-start text-muted-foreground">
                  {order.user.email}
                  {order.user.phone ? ` · ${order.user.phone}` : ''}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.orders.table.amount')}</dt>
                <dd className="tabular-nums">{formatPrice(order.amount, order.currency, i18n.language)}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.orders.table.status')}</dt>
                <dd>
                  <OrderStatusBadge status={order.status} />
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.orders.table.method')}</dt>
                <dd>{methodLabel(order.method)}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.orders.detail.scope')}</dt>
                <dd>
                  {order.scope === 'full'
                    ? t('admin.orders.detail.fullAccess')
                    : t('admin.orders.detail.modulesScope')}
                </dd>
              </div>
              {order.discount_percent > 0 && (
                <div>
                  <dt className="text-xs text-muted-foreground">{t('admin.orders.detail.discount')}</dt>
                  <dd dir="ltr" className="text-start">
                    -{order.discount_percent}%
                  </dd>
                </div>
              )}
              <div>
                <dt className="text-xs text-muted-foreground">{t('admin.orders.detail.createdAt')}</dt>
                <dd>{formatDate(order.created_at, i18n.language)}</dd>
              </div>
              {order.paid_at && (
                <div>
                  <dt className="text-xs text-muted-foreground">{t('admin.orders.detail.paidAt')}</dt>
                  <dd>{formatDate(order.paid_at, i18n.language)}</dd>
                </div>
              )}
            </dl>

            {order.scope === 'modules' && order.modules && order.modules.length > 0 && (
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('admin.orders.detail.modules')}</p>
                <ul className="divide-y rounded-lg border">
                  {order.modules.map((module) => (
                    <li key={module.id} className="flex items-center justify-between gap-2 px-3 py-2">
                      <span className="min-w-0 truncate">{module.title ?? `#${module.id}`}</span>
                      <span className="shrink-0 tabular-nums text-muted-foreground">
                        {formatPrice(module.unit_price, order.currency, i18n.language)}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {order.admin_note && (
              <div>
                <p className="text-xs text-muted-foreground">{t('admin.orders.detail.adminNote')}</p>
                <p className="rounded-md bg-secondary/50 p-2">{order.admin_note}</p>
              </div>
            )}

            {order.has_receipt && order.receipt_url && (
              <Button asChild variant="outline" size="sm">
                <a href={order.receipt_url} target="_blank" rel="noreferrer">
                  <FileText className="h-4 w-4" />
                  {t('admin.orders.actions.viewReceipt')}
                </a>
              </Button>
            )}

            {/* Facture: promised "sur demande" on the sales page — issued here. */}
            {order.status === 'paid' && (
              <div className="rounded-lg border bg-secondary/30 p-3">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('admin.invoices.orderSection.title')}
                </p>
                {order.invoice ? (
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <p className="font-medium tabular-nums">{order.invoice.number}</p>
                      <p className="text-xs text-muted-foreground">
                        {formatDate(order.invoice.issued_at, i18n.language)}
                      </p>
                    </div>
                    <div className="flex items-center gap-1.5">
                      {sendState === 'sent' && (
                        <span className="text-xs text-success">{t('admin.invoices.table.sent')}</span>
                      )}
                      {sendState === 'error' && (
                        <span className="text-xs text-destructive">{t('common.error')}</span>
                      )}
                      <Button asChild variant="outline" size="sm">
                        <a href={order.invoice.pdf_url}>
                          <FileDown className="h-4 w-4" />
                          {t('admin.invoices.table.download')}
                        </a>
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={sendInvoice.isPending}
                        onClick={() =>
                          sendInvoice.mutate(order.invoice!.id, {
                            onSuccess: () => setSendState('sent'),
                            onError: () => setSendState('error'),
                          })
                        }
                      >
                        <Mail className="h-4 w-4" />
                        {t('admin.invoices.table.send')}
                      </Button>
                    </div>
                  </div>
                ) : (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      setInvoiceTarget({ orderId: order.id, defaultBuyerName: order.user.name })
                    }
                  >
                    <FilePlus2 className="h-4 w-4" />
                    {t('admin.invoices.orderSection.create')}
                  </Button>
                )}
              </div>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>

    <InvoiceFormDialog target={invoiceTarget} onClose={() => setInvoiceTarget(null)} />
    </>
  )
}

function RejectDialog({
  order,
  onOpenChange,
}: {
  order: AdminOrder | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const reject = useRejectOrder()
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  const submit = () => {
    const trimmed = reason.trim()
    if (!trimmed) {
      setError(t('validation.required'))
      return
    }
    if (!order) return
    reject.mutate(
      { id: order.id, reason: trimmed },
      {
        onSuccess: () => {
          setReason('')
          onOpenChange(false)
        },
        onError: (err) => setError(apiErrorMessage(err) ?? t('common.error')),
      },
    )
  }

  return (
    <Dialog
      open={order !== null}
      onOpenChange={(next) => {
        if (reject.isPending) return
        if (!next) {
          setReason('')
          setError(null)
        }
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('admin.orders.reject.title')}</DialogTitle>
          <DialogDescription>{t('admin.orders.reject.subtitle')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="reject-reason">{t('admin.orders.reject.reason')}</Label>
          <Textarea
            id="reject-reason"
            rows={3}
            maxLength={1000}
            value={reason}
            onChange={(event) => {
              setReason(event.target.value)
              setError(null)
            }}
            placeholder={t('admin.orders.reject.placeholder')}
          />
          {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={reject.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            onClick={submit}
            disabled={reject.isPending}
          >
            {reject.isPending ? t('common.loading') : t('admin.orders.actions.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function AdminOrdersPage() {
  const { t, i18n } = useTranslation()
  const methodLabel = useMethodLabel()

  const [search, setSearch] = useState('')
  // ?status=awaiting_review: the dashboard's "to review" tile lands here.
  const [searchParams] = useSearchParams()
  const [status, setStatus] = useState<OrderStatus | typeof ALL>(() => {
    const fromUrl = searchParams.get('status')
    return STATUSES.includes(fromUrl as OrderStatus) ? (fromUrl as OrderStatus) : ALL
  })
  const [method, setMethod] = useState<OrderMethod | typeof ALL>(ALL)
  const [page, setPage] = useState(1)

  const [detailId, setDetailId] = useState<number | null>(null)
  const [rejectTarget, setRejectTarget] = useState<AdminOrder | null>(null)
  const [confirm, setConfirm] = useState<ConfirmAction | null>(null)
  const [confirmError, setConfirmError] = useState<string | null>(null)

  const debouncedSearch = useDebouncedValue(search)
  const filters: AdminOrderFilters = {
    status: status === ALL ? '' : status,
    method: method === ALL ? '' : method,
    search: debouncedSearch || undefined,
    page,
  }
  const { data, isLoading, isError, isFetching } = useAdminOrders(filters)
  const approve = useApproveOrder()
  const grant = useGrantOrder()

  const orders = data?.data ?? []
  const meta = data?.meta

  const resetPage = () => setPage(1)

  const runConfirm = () => {
    if (!confirm) return
    setConfirmError(null)
    const mutation = confirm.type === 'approve' ? approve : grant
    mutation.mutate(confirm.order.id, {
      onSuccess: () => setConfirm(null),
      onError: (err) => setConfirmError(apiErrorMessage(err) ?? t('common.error')),
    })
  }

  const confirmPending = approve.isPending || grant.isPending

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">{t('admin.orders.title')}</h1>
        <Button asChild variant="outline">
          <a href={adminOrdersExportUrl(filters)}>
            <Download className="h-4 w-4" />
            {t('admin.orders.export')}
          </a>
        </Button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="relative min-w-[220px] flex-1">
          <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
          <Input
            type="search"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              resetPage()
            }}
            placeholder={t('admin.orders.searchPlaceholder')}
            className="ps-9"
          />
        </div>
        <Select
          value={status}
          onValueChange={(value) => {
            setStatus(value as OrderStatus | typeof ALL)
            resetPage()
          }}
        >
          <SelectTrigger className="w-[180px]" aria-label={t('admin.orders.table.status')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>{t('admin.orders.allStatuses')}</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`admin.status.${s}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={method}
          onValueChange={(value) => {
            setMethod(value as OrderMethod | typeof ALL)
            resetPage()
          }}
        >
          <SelectTrigger className="w-[180px]" aria-label={t('admin.orders.table.method')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>{t('admin.orders.allMethods')}</SelectItem>
            {METHODS.map((m) => (
              <SelectItem key={m} value={m}>
                {methodLabel(m)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : orders.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          {t('admin.orders.empty')}
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('admin.orders.table.customer')}</TableHead>
                  {/* One course today, and it is in the detail dialog: below
                      1440px the column gives its width to the actions, which
                      were clipped off the right edge at 1280px. */}
                  <TableHead className="hidden min-[1440px]:table-cell">{t('admin.orders.table.course')}</TableHead>
                  <TableHead>{t('admin.orders.table.amount')}</TableHead>
                  <TableHead>{t('admin.orders.table.method')}</TableHead>
                  <TableHead>{t('admin.orders.table.status')}</TableHead>
                  <TableHead>{t('admin.orders.table.date')}</TableHead>
                  <TableHead className="text-end">
                    <span className="sr-only">{t('admin.common.actions')}</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.map((order) => (
                  <TableRow
                    key={order.id}
                    className="cursor-pointer"
                    onClick={() => setDetailId(order.id)}
                  >
                    <TableCell>
                      <div className="font-medium leading-tight">{order.user.name}</div>
                      <div className="text-xs text-muted-foreground" dir="ltr">
                        {order.user.email}
                      </div>
                    </TableCell>
                    <TableCell className="hidden max-w-[200px] truncate min-[1440px]:table-cell">{order.course.title}</TableCell>
                    <TableCell className="whitespace-nowrap tabular-nums">
                      {formatPrice(order.amount, order.currency, i18n.language)}
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">
                      {methodLabel(order.method)}
                    </TableCell>
                    <TableCell>
                      <OrderStatusBadge status={order.status} />
                      {order.status === 'awaiting_review' && order.receipt_uploaded_at && (
                        <ReceiptWaiting since={order.receipt_uploaded_at} className="mt-1" />
                      )}
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">
                      {formatDate(order.created_at, i18n.language)}
                    </TableCell>
                    <TableCell onClick={(event) => event.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {order.status === 'awaiting_review' && (
                          <>
                            <Button
                              variant="ghost"
                              size="icon"
                              aria-label={t('admin.orders.actions.approve')}
                              onClick={() => {
                                setConfirmError(null)
                                setConfirm({ order, type: 'approve' })
                              }}
                            >
                              <Check className="h-4 w-4 text-success" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              aria-label={t('admin.orders.actions.reject')}
                              onClick={() => setRejectTarget(order)}
                            >
                              <X className="h-4 w-4 text-destructive" />
                            </Button>
                          </>
                        )}
                        {(order.status === 'pending' || order.status === 'failed') && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setConfirmError(null)
                              setConfirm({ order, type: 'grant' })
                            }}
                          >
                            {t('admin.orders.actions.grant')}
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm text-muted-foreground">
                {t('admin.common.pageOf', { page: meta.current_page, total: meta.last_page })}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page <= 1 || isFetching}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                  {t('admin.common.previous')}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page >= meta.last_page || isFetching}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('admin.common.next')}
                  <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      <OrderDetailDialog orderId={detailId} onOpenChange={(open) => !open && setDetailId(null)} />
      <RejectDialog order={rejectTarget} onOpenChange={(open) => !open && setRejectTarget(null)} />

      <AlertDialog
        open={confirm !== null}
        onOpenChange={(open) => {
          if (!open && !confirmPending) {
            setConfirm(null)
            setConfirmError(null)
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {confirm?.type === 'approve'
                ? t('admin.orders.confirm.approveTitle')
                : t('admin.orders.confirm.grantTitle')}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {confirm?.type === 'approve'
                ? t('admin.orders.confirm.approveBody', { name: confirm?.order.user.name ?? '' })
                : t('admin.orders.confirm.grantBody', { name: confirm?.order.user.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {confirmError && <p className="text-sm text-destructive">{confirmError}</p>}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={confirmPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              disabled={confirmPending}
              onClick={(event) => {
                event.preventDefault()
                runConfirm()
              }}
            >
              {confirmPending ? t('common.loading') : t('common.confirm')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
