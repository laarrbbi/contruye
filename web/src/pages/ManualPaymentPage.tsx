import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import {
  Check,
  CircleAlert,
  CircleCheck,
  Copy,
  FileText,
  Hourglass,
  Upload,
} from 'lucide-react'
import {
  apiErrorMessage,
  apiFieldError,
  useOrder,
  useUploadReceipt,
  type Order,
} from '@/api/orders'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { cn, formatPrice } from '@/lib/utils'
import { hasContactChannel, useBusinessProfile } from '@/api/business'
import { ContactOptions } from '@/components/ContactOptions'

const MAX_RECEIPT_BYTES = 5 * 1024 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'application/pdf']

function CopyButton({ value }: { value: string }) {
  const { t } = useTranslation()
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      // Clipboard unavailable (permissions / http): silently ignore.
    }
  }

  return (
    <Button
      variant="ghost"
      size="icon"
      className="h-8 w-8 shrink-0"
      onClick={copy}
      aria-label={copied ? t('checkout.manual.copied') : t('checkout.manual.copy')}
    >
      {copied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
    </Button>
  )
}

function InstructionRow({ label, value, copyable = true }: { label: string; value: string; copyable?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3 py-2.5">
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        {/* Forced LTR: an IBAN reordered by bidi inside other text is a
            transfer that never arrives. */}
        <p dir="ltr" className="break-all text-start font-semibold tabular-nums">
          {value}
        </p>
      </div>
      {copyable && <CopyButton value={value} />}
    </div>
  )
}

function ReceiptUploader({ order, onUploaded }: { order: Order; onUploaded?: () => void }) {
  const { t } = useTranslation()
  const upload = useUploadReceipt(order.id)
  const inputRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [clientError, setClientError] = useState<string | null>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  useEffect(() => {
    if (!file || !file.type.startsWith('image/')) {
      setPreviewUrl(null)
      return
    }
    const url = URL.createObjectURL(file)
    setPreviewUrl(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const pick = (picked: File | undefined) => {
    setClientError(null)
    upload.reset()
    if (!picked) return
    if (!ACCEPTED_TYPES.includes(picked.type)) {
      setClientError(t('checkout.manual.uploadBadType'))
      return
    }
    if (picked.size > MAX_RECEIPT_BYTES) {
      setClientError(t('checkout.manual.uploadTooLarge'))
      return
    }
    setFile(picked)
  }

  const serverError = upload.isError
    ? apiFieldError(upload.error, 'receipt') ?? apiErrorMessage(upload.error) ?? t('common.error')
    : null

  return (
    <div className="space-y-3">
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className={cn(
          'flex w-full flex-col items-center gap-2 rounded-lg border-2 border-dashed p-6 text-center transition-colors',
          file ? 'border-primary/50 bg-primary/5' : 'border-border hover:border-primary/50',
        )}
      >
        {previewUrl ? (
          <img src={previewUrl} alt={file?.name ?? ''} className="max-h-40 rounded-md object-contain" />
        ) : file ? (
          <FileText className="h-10 w-10 text-primary" />
        ) : (
          <Upload className="h-10 w-10 text-muted-foreground" />
        )}
        <span className="text-sm font-medium">{file ? file.name : t('checkout.manual.uploadChoose')}</span>
        <span className="text-xs text-muted-foreground">{t('checkout.manual.uploadHint')}</span>
      </button>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,application/pdf"
        className="hidden"
        onChange={(e) => {
          pick(e.target.files?.[0])
          e.target.value = ''
        }}
      />

      {clientError && <p className="text-sm text-destructive">{clientError}</p>}
      {serverError && <p className="text-sm text-destructive">{serverError}</p>}

      <Button
        className="w-full"
        disabled={!file || upload.isPending}
        onClick={() =>
          file &&
          upload.mutate(file, {
            onSuccess: () => {
              setFile(null)
              onUploaded?.()
            },
          })
        }
      >
        {upload.isPending ? t('checkout.manual.uploading') : t('checkout.manual.uploadSubmit')}
      </Button>
    </div>
  )
}

export function ManualPaymentPage() {
  const { data: business } = useBusinessProfile()
  const canBeReached = hasContactChannel(business)
  const { id } = useParams<{ id: string }>()
  const { t, i18n } = useTranslation()
  usePageTitle(t('checkout.manual.title'))

  const orderId = Number(id)
  const validId = Number.isInteger(orderId) && orderId > 0
  const { data: order, isLoading, isError } = useOrder(validId ? orderId : null)

  const [reupload, setReupload] = useState(false)

  if (!validId) {
    return <Navigate to="/dashboard" replace />
  }

  if (isLoading) {
    return (
      <div className="container flex min-h-[50vh] items-center justify-center text-muted-foreground">
        {t('common.loading')}
      </div>
    )
  }

  if (isError || !order) {
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <p className="text-muted-foreground">{t('checkout.manual.notFound')}</p>
        <Button asChild variant="outline">
          <Link to="/orders">{t('orders.title')}</Link>
        </Button>
      </div>
    )
  }

  // This page only makes sense for manual (bank transfer / Bizum) orders.
  if (order.method !== 'manual_transfer' && order.method !== 'manual_bizum') {
    return <Navigate to="/dashboard" replace />
  }

  const locale = i18n.language
  const instructions = order.payment_instructions
  const amount = formatPrice(order.amount, order.currency, locale)

  if (order.status === 'paid') {
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <CircleCheck className="h-14 w-14 text-success" />
        <div className="space-y-1">
          <h1 className="text-2xl font-bold">{t('checkout.manual.paidTitle')}</h1>
          <p className="text-muted-foreground">{t('checkout.manual.paidHint')}</p>
        </div>
        <Button asChild size="lg">
          <Link to={`/learn/${order.course.slug}`}>{t('checkout.manual.startCourse')}</Link>
        </Button>
      </div>
    )
  }

  const awaitingReview = order.status === 'awaiting_review'
  const rejected = order.status === 'failed'
  const showUploader = !awaitingReview || reupload

  return (
    <div className="container max-w-2xl py-8 sm:py-12">
      <div className="mb-6 space-y-1">
        <h1 className="text-3xl font-bold">{t('checkout.manual.title')}</h1>
        <p className="text-muted-foreground">
          {t('orders.orderNumber', { id: order.id })} — {order.course.title}
        </p>
      </div>

      <div className="space-y-6">
        {/* Rejection alert */}
        {rejected && order.admin_note && (
          <div className="flex items-start gap-3 rounded-lg border border-destructive/50 bg-destructive/5 p-4">
            <CircleAlert className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
            <div className="space-y-1">
              <p className="font-semibold text-destructive">{t('checkout.manual.rejectedTitle')}</p>
              <p className="bidi-auto text-sm">{order.admin_note}</p>
              <p className="text-sm text-muted-foreground">{t('checkout.manual.rejectedHint')}</p>
            </div>
          </div>
        )}

        {/* Awaiting-review banner */}
        {awaitingReview && (
          <div className="flex items-start gap-3 rounded-lg border border-success/50 bg-success/5 p-4">
            <Hourglass className="mt-0.5 h-5 w-5 shrink-0 text-success" />
            <div className="space-y-1">
              <p className="font-semibold text-success">{t('checkout.manual.receiptSent')}</p>
              <p className="text-sm text-muted-foreground">{t('checkout.manual.receiptSentHint')}</p>
            </div>
          </div>
        )}

        {/* Transfer instructions */}
        {instructions && (
          <Card>
            <CardContent className="p-5">
              <p className="mb-2 text-sm text-muted-foreground">{t('checkout.manual.subtitle')}</p>

              <div className="mb-1 flex items-center justify-between gap-3 py-2">
                <div>
                  <p className="text-xs text-muted-foreground">{t('checkout.manual.amount')}</p>
                  <p className="text-3xl font-extrabold text-primary tabular-nums">{amount}</p>
                </div>
                <CopyButton value={String(order.amount)} />
              </div>

              <Separator />

              <div className="divide-y">
                {instructions.method === 'manual_transfer' ? (
                  <>
                    {instructions.iban && (
                      <InstructionRow label={t('checkout.manual.iban')} value={instructions.iban} />
                    )}
                    {instructions.holder && (
                      <InstructionRow label={t('checkout.manual.holder')} value={instructions.holder} />
                    )}
                  </>
                ) : (
                  instructions.bizum_phone && (
                    <InstructionRow label={t('checkout.manual.bizumPhone')} value={instructions.bizum_phone} />
                  )
                )}
                {/* Without the concept, the owner cannot tell whose money
                    arrived: it goes on every transfer and every Bizum. */}
                <InstructionRow label={t('checkout.manual.reference')} value={instructions.reference} />
              </div>
            </CardContent>
          </Card>
        )}

        {/* Purchased modules */}
        {order.scope === 'modules' && order.modules && order.modules.length > 0 && (
          <Card>
            <CardContent className="p-5">
              <div className="mb-3 flex items-center gap-2">
                <h2 className="font-bold">{t('checkout.manual.purchasedModules')}</h2>
                <Badge variant="secondary">{t('orders.scopeModules', { count: order.modules.length })}</Badge>
              </div>
              <ul className="divide-y text-sm">
                {order.modules.map((line) => (
                  <li key={line.id} className="flex items-baseline justify-between gap-3 py-2">
                    <span className="bidi-auto min-w-0">{line.title}</span>
                    <span className="shrink-0 font-medium tabular-nums">
                      {formatPrice(line.unit_price, order.currency, locale)}
                    </span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        )}

        {/* Receipt upload */}
        <Card>
          <CardContent className="space-y-4 p-5">
            <h2 className="font-bold">{t('checkout.manual.uploadTitle')}</h2>
            {showUploader ? (
              <ReceiptUploader order={order} onUploaded={() => setReupload(false)} />
            ) : (
              <Button variant="outline" className="w-full" onClick={() => setReupload(true)}>
                <Upload className="h-4 w-4" />
                {t('checkout.manual.uploadAnother')}
              </Button>
            )}
          </CardContent>
        </Card>

        {/* The one place a buyer who has already paid at the post office is
            left waiting on a human — give them the human. The order number is
            pre-written so the admin can match the transfer straight away. */}
        {canBeReached && (
          <Card>
            <CardContent className="space-y-3 p-5">
              <div>
                <h2 className="font-bold">{t('contact.paymentHelpTitle')}</h2>
                <p className="text-sm text-muted-foreground">{t('contact.paymentHelpBody')}</p>
              </div>
              <ContactOptions prefill={t('contact.prefillOrder', { id: order.id })} />
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  )
}
