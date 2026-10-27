import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, CheckCircle2, Globe } from 'lucide-react'
import {
  useInvoicingSettings,
  useSaveInvoicingSettings,
  type InvoicingSeller,
} from '@/api/admin'
import { apiErrorMessage } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/** Mandatory mentions of an Algerian invoice — issuance is blocked without them. */
const LEGAL_FIELDS: (keyof InvoicingSeller)[] = [
  'business_name',
  'legal_form',
  'capital',
  'address',
  'rc',
  'nif',
  'nis',
  'ai',
]

/** How buyers reach a human. Optional; each one appears on the site once filled. */
const CONTACT_FIELDS: (keyof InvoicingSeller)[] = ['whatsapp', 'phone', 'email', 'support_hours']

/** Fiscal-only: printed on invoices, never on the public site. */
const PRIVATE_FIELDS: (keyof InvoicingSeller)[] = ['bank_details']

/** Shown publicly (footer) — so the owner knows what a visitor will see. */
const PUBLIC_ON_SITE = new Set<keyof InvoicingSeller>([
  'business_name',
  'legal_form',
  'capital',
  'address',
  'rc',
  'nif',
  'whatsapp',
  'phone',
  'email',
  'support_hours',
])

const EMPTY: InvoicingSeller = {
  business_name: '',
  legal_form: '',
  capital: '',
  address: '',
  rc: '',
  nif: '',
  nis: '',
  ai: '',
  phone: '',
  email: '',
  bank_details: '',
  whatsapp: '',
  support_hours: '',
}

/**
 * The business identity, in one place: the seller block of every invoice and
 * the identity and contact shown in the site footer, FAQ and payment pages.
 * Anything left empty simply does not appear — the site never shows a sample
 * value in its place.
 */
export function BusinessSettingsCard() {
  const { t } = useTranslation()
  const settings = useInvoicingSettings()
  const save = useSaveInvoicingSettings()

  const [draft, setDraft] = useState<InvoicingSeller | null>(null)
  const [message, setMessage] = useState<'saved' | string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof InvoicingSeller, string>>>({})

  // Never let typing start against EMPTY while the fetch is in flight — a
  // single keystroke would snapshot blanks over saved values.
  const loaded = settings.data !== undefined
  const seller: InvoicingSeller = { ...EMPTY, ...(draft ?? settings.data?.seller ?? EMPTY) }

  const field = (name: keyof InvoicingSeller, required = false) => (
    <div key={name} className="space-y-1.5">
      <Label htmlFor={`seller-${name}`} className="flex items-center gap-1.5">
        {t(`admin.business.fields.${name}`)}
        {required && (
          <span className="text-destructive" aria-hidden>
            *
          </span>
        )}
        {PUBLIC_ON_SITE.has(name) && (
          <Globe className="h-3 w-3 text-muted-foreground" aria-label={t('admin.business.publicHint')} />
        )}
      </Label>
      <Input
        id={`seller-${name}`}
        value={seller[name]}
        disabled={!loaded}
        placeholder={t(`admin.business.placeholders.${name}`, { defaultValue: '' })}
        dir={['whatsapp', 'phone', 'email', 'rc', 'nif', 'nis', 'ai'].includes(name) ? 'ltr' : undefined}
        aria-required={required || undefined}
        aria-invalid={Boolean(fieldErrors[name])}
        aria-describedby={fieldErrors[name] ? `seller-${name}-error` : undefined}
        onChange={(event) => {
          setMessage(null)
          setFieldErrors((errors) => ({ ...errors, [name]: undefined }))
          setDraft({ ...seller, [name]: event.target.value })
        }}
        maxLength={500}
      />
      {fieldErrors[name] && (
        <p id={`seller-${name}-error`} className="text-xs text-destructive">
          {fieldErrors[name]}
        </p>
      )}
    </div>
  )

  const submit = () =>
    save.mutate(seller, {
      onSuccess: () => {
        setDraft(null)
        setFieldErrors({})
        setMessage('saved')
      },
      onError: (err) => {
        const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors
        if (errors) {
          setFieldErrors(
            Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])) as Partial<
              Record<keyof InvoicingSeller, string>
            >,
          )
          setMessage(t('admin.business.fixErrors'))
          // The save bar is pinned to the bottom; the field at fault may be
          // scrolled out of sight. Take the admin to the first one.
          requestAnimationFrame(() =>
            document.querySelector<HTMLInputElement>('input[id^="seller-"][aria-invalid="true"]')?.focus(),
          )
        } else {
          setMessage(apiErrorMessage(err) ?? t('common.error'))
        }
      },
    })

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="space-y-2">
          <CardTitle className="text-lg">{t('admin.business.contactTitle')}</CardTitle>
          <p className="text-sm text-muted-foreground">{t('admin.business.contactHint')}</p>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {CONTACT_FIELDS.map((name) => field(name))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="space-y-2">
          <CardTitle className="text-lg">{t('admin.business.legalTitle')}</CardTitle>
          <p className="text-sm text-muted-foreground">{t('admin.business.legalHint')}</p>
          {settings.data &&
            (settings.data.complete ? (
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
            ))}
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          {LEGAL_FIELDS.map((name) => field(name, true))}
          {PRIVATE_FIELDS.map((name) => field(name))}
        </CardContent>
      </Card>

      <div className="sticky bottom-0 -mx-1 flex items-center gap-3 border-t bg-background/95 px-1 py-3 backdrop-blur">
        <Button onClick={submit} disabled={save.isPending || draft === null || !loaded}>
          {save.isPending ? t('common.loading') : t('common.save')}
        </Button>
        {message === 'saved' && <p className="text-sm text-success">{t('admin.business.saved')}</p>}
        {message !== null && message !== 'saved' && <p className="text-sm text-destructive">{message}</p>}
        <p className="ms-auto hidden items-center gap-1.5 text-xs text-muted-foreground sm:flex">
          <Globe className="h-3 w-3" />
          {t('admin.business.publicHint')}
        </p>
      </div>
    </div>
  )
}
