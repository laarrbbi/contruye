import { useEffect, useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useUpdateProfile, useUser } from '@/api/auth'
import { apiFieldError } from '@/api/orders'
import { provinceLabel, useProvinces } from '@/api/provinces'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { LOCALES, LOCALE_NAMES, isRtl, setLocale, type Locale } from '@/i18n'
import { intlLocale } from '@/lib/utils'
import { AccountPanel, Field, SavedNote } from './AccountPanel'
import { EmailDialog } from './EmailDialog'

const NO_PROVINCE = 'none'

export function ProfileSection() {
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const { data: provinces } = useProvinces()
  const update = useUpdateProfile()
  const [emailOpen, setEmailOpen] = useState(false)
  const [saved, setSaved] = useState(false)
  const [form, setForm] = useState({ name: '', phone: '', province: NO_PROVINCE, locale: 'es' as Locale })

  useEffect(() => {
    if (!user) return
    setForm({
      name: user.name,
      phone: user.phone ?? '',
      province: user.province_id ? String(user.province_id) : NO_PROVINCE,
      locale: user.locale,
    })
  }, [user])

  if (!user) return null

  const edit = (patch: Partial<typeof form>) => {
    setSaved(false)
    setForm((current) => ({ ...current, ...patch }))
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    update.mutate(
      {
        name: form.name,
        phone: form.phone,
        province_id: form.province === NO_PROVINCE ? null : Number(form.province),
        locale: form.locale,
      },
      {
        onSuccess: () => {
          setSaved(true)
          // The language of the e-mails is the language of the screen too.
          if (form.locale !== i18n.language) void setLocale(form.locale)
        },
      },
    )
  }

  const since = new Intl.DateTimeFormat(intlLocale(i18n.language), { month: 'long', year: 'numeric' }).format(new Date(user.created_at))

  return (
    <div className="space-y-6">
      <AccountPanel title={t('account.profile.title')} lead={t('account.profile.memberSince', { date: since })}>
        <form onSubmit={submit} className="grid gap-5 sm:grid-cols-2" noValidate>
          <Field id="account-name" label={t('account.profile.name')} error={apiFieldError(update.error, 'name')}>
            <Input id="account-name" autoComplete="name" value={form.name} onChange={(e) => edit({ name: e.target.value })} required />
          </Field>
          <Field id="account-phone" label={t('account.profile.phone')} error={apiFieldError(update.error, 'phone')} hint={t('account.profile.phoneHint')}>
            <Input id="account-phone" type="tel" dir="ltr" autoComplete="tel" value={form.phone} onChange={(e) => edit({ phone: e.target.value })} required />
          </Field>
          <Field id="account-province" label={t('account.profile.province')} hint={t('account.profile.provinceHint')}>
            {/* Inside a form, Radix's hidden native select reports '' when the
                value is not among its options yet (the list still loading),
                which would wipe the choice: '' is ignored, and the select is
                remounted once the list is there. */}
            <Select
              key={provinces ? 'ready' : 'loading'}
              value={form.province}
              onValueChange={(value) => value !== '' && edit({ province: value })}
            >
              <SelectTrigger id="account-province">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NO_PROVINCE}>{t('account.profile.noProvince')}</SelectItem>
                {(provinces ?? []).map((province) => (
                  <SelectItem key={province.id} value={String(province.id)}>
                    {provinceLabel(province)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field id="account-locale" label={t('account.profile.language')} hint={t('account.profile.languageHint')}>
            <Select value={form.locale} onValueChange={(value) => edit({ locale: value as Locale })}>
              <SelectTrigger id="account-locale">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {LOCALES.map((locale) => (
                  <SelectItem key={locale} value={locale}>
                    <span lang={locale} dir={isRtl(locale) ? 'rtl' : 'ltr'}>
                      {LOCALE_NAMES[locale]}
                    </span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <div className="flex items-center gap-4 sm:col-span-2">
            <Button type="submit" disabled={update.isPending}>
              {t('account.save')}
            </Button>
            <SavedNote show={saved}>{t('account.saved')}</SavedNote>
          </div>
        </form>
      </AccountPanel>

      <AccountPanel
        title={t('account.profile.email')}
        lead={t('account.profile.emailLead')}
        actions={
          <Button variant="outline" size="sm" onClick={() => setEmailOpen(true)}>
            {t('account.profile.changeEmail')}
          </Button>
        }
      >
        <p dir="ltr" className="truncate text-start font-medium rtl:text-end">
          {user.email}
        </p>
      </AccountPanel>

      <EmailDialog open={emailOpen} onOpenChange={setEmailOpen} />
    </div>
  )
}
