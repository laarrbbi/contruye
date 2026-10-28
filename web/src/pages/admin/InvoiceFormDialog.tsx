import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  useCreateInvoice,
  useInvoicingSettings,
  useUpdateInvoice,
  type AdminInvoice,
  type InvoicePayload,
} from '@/api/admin'
import { apiErrorMessage, apiFieldError } from '@/api/orders'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'

/** Spanish IVA rates: general, reducido, superreducido, exento. */
const IVA_RATES = [21, 10, 4, 0] as const

export interface InvoiceFormTarget {
  /** Create mode: the paid order to invoice. */
  orderId?: number
  /** Prefill for the buyer name (the account name). */
  defaultBuyerName?: string
  /** Edit mode: the existing invoice to correct. */
  invoice?: AdminInvoice
}

interface InvoiceFormDialogProps {
  target: InvoiceFormTarget | null
  onClose: () => void
}

/**
 * Issue (or correct) a factura. The buyer block is what the Reglamento de
 * facturación asks for: name + address always, the NIF when the buyer is a
 * business or a professional. Issuance is refused server-side until the
 * seller settings are complete — surfaced here with a link to the settings
 * page.
 */
export function InvoiceFormDialog({ target, onClose }: InvoiceFormDialogProps) {
  const { t } = useTranslation()
  const settings = useInvoicingSettings(target !== null)
  const create = useCreateInvoice()
  const update = useUpdateInvoice()

  const editing = target?.invoice ?? null
  const [form, setForm] = useState<InvoicePayload>({ buyer_name: '', buyer_address: '', iva_rate: 21 })
  const [error, setError] = useState<string | null>(null)
  // Per field, under the field: one line at the bottom of a long form did not
  // say which of the two required fields was empty.
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<'buyer_name' | 'buyer_address', string>>>({})

  useEffect(() => {
    if (target === null) return
    setError(null)
    setForm({
      buyer_name: editing?.buyer_name ?? target.defaultBuyerName ?? '',
      buyer_address: editing?.buyer_address ?? '',
      buyer_nif: editing?.buyer_nif ?? '',
      iva_rate: editing?.iva_rate ?? 21,
      notes: editing?.notes ?? '',
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [target?.orderId, editing?.id])

  const pending = create.isPending || update.isPending
  const settingsBlocked = !editing && settings.data !== undefined && !settings.data.complete

  const set = (field: keyof InvoicePayload) => (value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
    setFieldErrors((prev) => ({ ...prev, [field]: undefined }))
  }

  const submit = () => {
    const missing: typeof fieldErrors = {}
    if (form.buyer_name.trim() === '') missing.buyer_name = t('validation.required')
    if (form.buyer_address.trim() === '') missing.buyer_address = t('validation.required')
    setFieldErrors(missing)
    if (missing.buyer_name || missing.buyer_address) {
      document.getElementById(missing.buyer_name ? 'invoice-buyer-name' : 'invoice-buyer-address')?.focus()
      return
    }
    setError(null)

    const payload: InvoicePayload = {
      ...form,
      buyer_nif: form.buyer_nif || null,
      notes: form.notes || null,
    }

    const options = {
      onSuccess: onClose,
      onError: (err: unknown) => {
        const name = apiFieldError(err, 'buyer_name')
        const address = apiFieldError(err, 'buyer_address')
        if (name || address) setFieldErrors({ buyer_name: name, buyer_address: address })
        else setError(apiErrorMessage(err) ?? t('common.error'))
      },
    }

    if (editing) {
      update.mutate({ id: editing.id, payload }, options)
    } else if (target?.orderId !== undefined) {
      create.mutate({ orderId: target.orderId, payload }, options)
    }
  }

  const proField = (field: 'buyer_nif') => (
    <div className="space-y-1.5">
      <Label htmlFor={`invoice-${field}`}>{t(`admin.invoices.form.${field}`)}</Label>
      <Input
        id={`invoice-${field}`}
        value={(form[field] as string) ?? ''}
        onChange={(event) => set(field)(event.target.value)}
        maxLength={100}
        dir="ltr"
      />
    </div>
  )

  return (
    <Dialog open={target !== null} onOpenChange={(open) => !open && !pending && onClose()}>
      <DialogContent className="max-h-[85vh] max-w-lg overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {editing
              ? t('admin.invoices.form.editTitle', { number: editing.number })
              : t('admin.invoices.form.createTitle')}
          </DialogTitle>
          <DialogDescription>{t('admin.invoices.form.subtitle')}</DialogDescription>
        </DialogHeader>

        {settingsBlocked ? (
          <div className="space-y-3 rounded-md border border-destructive/40 bg-destructive/5 p-4 text-sm">
            <p>{t('admin.invoices.form.settingsIncomplete')}</p>
            <Button asChild size="sm" variant="outline" onClick={onClose}>
              {/* Straight to the page that holds the fields, not the invoices
                  page that only links on to it. */}
              <Link to="/admin/business">{t('admin.invoices.form.goToSettings')}</Link>
            </Button>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="invoice-buyer-name">{t('admin.invoices.form.buyerName')}</Label>
              <Input
                id="invoice-buyer-name"
                value={form.buyer_name}
                onChange={(event) => set('buyer_name')(event.target.value)}
                maxLength={190}
                aria-invalid={fieldErrors.buyer_name ? true : undefined}
                aria-describedby={fieldErrors.buyer_name ? 'invoice-buyer-name-error' : undefined}
                className={cn(fieldErrors.buyer_name && 'border-destructive')}
              />
              {fieldErrors.buyer_name && (
                <p id="invoice-buyer-name-error" className="text-sm text-destructive">{fieldErrors.buyer_name}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="invoice-buyer-address">{t('admin.invoices.form.buyerAddress')}</Label>
              <Textarea
                id="invoice-buyer-address"
                value={form.buyer_address}
                onChange={(event) => set('buyer_address')(event.target.value)}
                rows={2}
                maxLength={1000}
                aria-invalid={fieldErrors.buyer_address ? true : undefined}
                aria-describedby={fieldErrors.buyer_address ? 'invoice-buyer-address-error' : undefined}
                className={cn(fieldErrors.buyer_address && 'border-destructive')}
              />
              {fieldErrors.buyer_address && (
                <p id="invoice-buyer-address-error" className="text-sm text-destructive">{fieldErrors.buyer_address}</p>
              )}
            </div>

            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t('admin.invoices.form.proSection')}
              </p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {proField('buyer_nif')}
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="invoice-iva-rate">{t('admin.invoices.form.ivaRate')}</Label>
              <Select
                value={String(form.iva_rate)}
                onValueChange={(value) => setForm((prev) => ({ ...prev, iva_rate: Number(value) }))}
              >
                <SelectTrigger id="invoice-iva-rate" className="w-[220px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {IVA_RATES.map((rate) => (
                    <SelectItem key={rate} value={String(rate)}>
                      {t(`admin.invoices.form.iva_${rate}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="invoice-notes">{t('admin.invoices.form.notes')}</Label>
              <Textarea
                id="invoice-notes"
                value={form.notes ?? ''}
                onChange={(event) => set('notes')(event.target.value)}
                rows={2}
                maxLength={1000}
              />
            </div>

            {error && <p className="text-sm text-destructive">{error}</p>}

            <DialogFooter>
              <Button variant="outline" onClick={onClose} disabled={pending}>
                {t('common.cancel')}
              </Button>
              <Button onClick={submit} disabled={pending}>
                {pending
                  ? t('common.loading')
                  : editing
                    ? t('common.save')
                    : t('admin.invoices.form.submit')}
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
