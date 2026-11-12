import { useEffect, useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useUpdateProfile, useUser } from '@/api/auth'
import { apiFieldError } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { AccountPanel, Field, SavedNote } from './AccountPanel'

/**
 * What goes on the student's invoices. The owner issues each invoice; these
 * fill its buyer block in advance, so nobody has to ask for a NIF by e-mail.
 * An invoice already issued keeps the details it was issued with.
 */
export function BillingSection() {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const update = useUpdateProfile()
  const [saved, setSaved] = useState(false)
  const [form, setForm] = useState({ name: '', nif: '', address: '' })

  useEffect(() => {
    if (!user) return
    setForm({ name: user.billing?.name ?? '', nif: user.billing?.nif ?? '', address: user.billing?.address ?? '' })
  }, [user])

  if (!user) return null

  const edit = (patch: Partial<typeof form>) => {
    setSaved(false)
    setForm((current) => ({ ...current, ...patch }))
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    update.mutate(
      { billing_name: form.name, billing_nif: form.nif, billing_address: form.address },
      { onSuccess: () => setSaved(true) },
    )
  }

  return (
    <AccountPanel title={t('account.billing.title')} lead={t('account.billing.lead')}>
      <form onSubmit={submit} className="grid gap-5 sm:grid-cols-2" noValidate>
        <Field id="billing-name" label={t('account.billing.name')} hint={t('account.billing.nameHint')} error={apiFieldError(update.error, 'billing_name')}>
          <Input
            id="billing-name"
            autoComplete="organization"
            placeholder={user.name}
            value={form.name}
            onChange={(e) => edit({ name: e.target.value })}
          />
        </Field>
        <Field id="billing-nif" label={t('account.billing.nif')} hint={t('account.billing.nifHint')} error={apiFieldError(update.error, 'billing_nif')}>
          <Input
            id="billing-nif"
            dir="ltr"
            autoCapitalize="characters"
            spellCheck={false}
            value={form.nif}
            onChange={(e) => edit({ nif: e.target.value })}
          />
        </Field>
        <Field
          id="billing-address"
          className="sm:col-span-2"
          label={t('account.billing.address')}
          hint={t('account.billing.addressHint')}
          error={apiFieldError(update.error, 'billing_address')}
        >
          <Textarea
            id="billing-address"
            rows={3}
            autoComplete="street-address"
            value={form.address}
            onChange={(e) => edit({ address: e.target.value })}
          />
        </Field>
        <p className="text-sm leading-relaxed text-foreground/60 sm:col-span-2">{t('account.billing.note')}</p>
        <div className="flex items-center gap-4 sm:col-span-2">
          <Button type="submit" disabled={update.isPending}>
            {t('account.save')}
          </Button>
          <SavedNote show={saved}>{t('account.saved')}</SavedNote>
        </div>
      </form>
    </AccountPanel>
  )
}
