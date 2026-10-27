import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, CheckCircle2, FileDown, Mail, Pencil, Search } from 'lucide-react'
import {
  useAdminInvoices,
  useInvoicingSettings,
  useSendInvoice,
  type AdminInvoice,
} from '@/api/admin'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { formatPrice } from '@/lib/utils'
import { formatDate, useDebouncedValue } from './lib'
import { InvoiceFormDialog, type InvoiceFormTarget } from './InvoiceFormDialog'

/**
 * Invoices cannot be issued until the seller block is complete. It is edited
 * in Entreprise & contact; here, only its state and the way there.
 */
function SellerStatus() {
  const { t } = useTranslation()
  const settings = useInvoicingSettings()

  if (!settings.data) return null

  return (
    <Card>
      <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
        {settings.data.complete ? (
          <p className="flex items-center gap-2 text-sm text-success">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            {t('admin.invoices.seller.complete')}
          </p>
        ) : (
          <p className="flex items-start gap-2 text-sm text-destructive">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>
              {t('admin.invoices.seller.incomplete')}{' '}
              {settings.data.missing.map((name) => t(`admin.business.fields.${name}`)).join(', ')}
            </span>
          </p>
        )}
        <Button asChild variant="outline" size="sm">
          <Link to="/admin/business">{t('admin.invoices.seller.edit')}</Link>
        </Button>
      </CardContent>
    </Card>
  )
}

function InvoiceRow({
  invoice,
  onEdit,
}: {
  invoice: AdminInvoice
  onEdit: (invoice: AdminInvoice) => void
}) {
  const { t, i18n } = useTranslation()
  const send = useSendInvoice()
  const [sendState, setSendState] = useState<'sent' | 'error' | null>(null)

  return (
    <TableRow>
      <TableCell className="font-medium tabular-nums">{invoice.number}</TableCell>
      <TableCell className="tabular-nums">{formatDate(invoice.issued_at, i18n.language)}</TableCell>
      <TableCell>
        <p className="font-medium">{invoice.buyer_name}</p>
        <p className="text-xs text-muted-foreground" dir="ltr">
          {invoice.order.user.email}
        </p>
      </TableCell>
      <TableCell className="tabular-nums">
        {formatPrice(invoice.total_gross_c / 100, invoice.currency, i18n.language)}
      </TableCell>
      <TableCell>
        <div className="flex items-center justify-end gap-1">
          {sendState === 'sent' && <span className="text-xs text-success">{t('admin.invoices.table.sent')}</span>}
          {sendState === 'error' && <span className="text-xs text-destructive">{t('common.error')}</span>}
          <Button asChild variant="ghost" size="icon" aria-label={t('admin.invoices.table.download')}>
            <a href={invoice.pdf_url}>
              <FileDown className="h-4 w-4" />
            </a>
          </Button>
          <Button
            variant="ghost"
            size="icon"
            aria-label={t('admin.invoices.table.send')}
            disabled={send.isPending}
            onClick={() =>
              send.mutate(invoice.id, {
                onSuccess: () => setSendState('sent'),
                onError: () => setSendState('error'),
              })
            }
          >
            <Mail className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            aria-label={t('admin.common.edit')}
            onClick={() => onEdit(invoice)}
          >
            <Pencil className="h-4 w-4" />
          </Button>
        </div>
      </TableCell>
    </TableRow>
  )
}

export function AdminInvoicesPage() {
  const { t } = useTranslation()

  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const debouncedSearch = useDebouncedValue(search)
  const filters = useMemo(() => ({ search: debouncedSearch, page }), [debouncedSearch, page])

  const { data, isLoading, isError, isFetching } = useAdminInvoices(filters)
  const invoices = data?.data ?? []
  const meta = data?.meta

  const [editTarget, setEditTarget] = useState<InvoiceFormTarget | null>(null)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">{t('admin.invoices.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('admin.invoices.subtitle')}</p>
      </div>

      <SellerStatus />

      <div className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-lg font-semibold">{t('admin.invoices.listTitle')}</h2>
          <div className="relative w-64">
            <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              type="search"
              className="ps-9"
              placeholder={t('admin.invoices.searchPlaceholder')}
              value={search}
              onChange={(event) => {
                setSearch(event.target.value)
                setPage(1)
              }}
            />
          </div>
        </div>

        {isLoading ? (
          <p className="py-8 text-center text-muted-foreground">{t('common.loading')}</p>
        ) : isError ? (
          <p className="py-8 text-center text-destructive">{t('common.error')}</p>
        ) : invoices.length === 0 ? (
          <div className="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
            {t('admin.invoices.empty')}
          </div>
        ) : (
          <div className="overflow-x-auto rounded-lg border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('admin.invoices.table.number')}</TableHead>
                  <TableHead>{t('admin.invoices.table.date')}</TableHead>
                  <TableHead>{t('admin.invoices.table.buyer')}</TableHead>
                  <TableHead>{t('admin.invoices.table.total')}</TableHead>
                  <TableHead>
                    <span className="sr-only">{t('admin.common.actions')}</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoices.map((invoice) => (
                  <InvoiceRow
                    key={invoice.id}
                    invoice={invoice}
                    onEdit={(inv) => setEditTarget({ invoice: inv })}
                  />
                ))}
              </TableBody>
            </Table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-end gap-3 text-sm">
            <span className="text-muted-foreground">
              {t('admin.common.pageOf', { page: meta.current_page, total: meta.last_page })}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1 || isFetching}
              onClick={() => setPage((current) => current - 1)}
            >
              {t('admin.common.previous')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page || isFetching}
              onClick={() => setPage((current) => current + 1)}
            >
              {t('admin.common.next')}
            </Button>
          </div>
        )}
      </div>

      <InvoiceFormDialog target={editTarget} onClose={() => setEditTarget(null)} />
    </div>
  )
}
