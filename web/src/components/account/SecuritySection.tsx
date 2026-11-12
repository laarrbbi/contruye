import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { LogOut, Monitor, Smartphone } from 'lucide-react'
import { useCloseOtherSessions, useCloseSession, useDeviceSessions, useUpdatePassword, type DeviceSession } from '@/api/account'
import { useLogout } from '@/api/auth'
import { apiFieldError } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { intlLocale } from '@/lib/utils'
import { AccountPanel, Field, SavedNote } from './AccountPanel'

const MOBILE = new Set(['iPhone', 'Android'])

function deviceName(session: DeviceSession, unknown: string) {
  const parts = [session.browser, session.os].filter(Boolean)
  return parts.length > 0 ? parts.join(' · ') : unknown
}

export function SecuritySection() {
  return (
    <div className="space-y-6">
      <PasswordPanel />
      <DevicesPanel />
    </div>
  )
}

function PasswordPanel() {
  const { t } = useTranslation()
  const update = useUpdatePassword()
  const [done, setDone] = useState(false)
  const [form, setForm] = useState({ current: '', password: '', confirmation: '' })
  const mismatch = form.confirmation !== '' && form.password !== form.confirmation

  const edit = (patch: Partial<typeof form>) => {
    setDone(false)
    setForm((current) => ({ ...current, ...patch }))
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (mismatch) return
    update.mutate(
      { current_password: form.current, password: form.password, password_confirmation: form.confirmation },
      {
        onSuccess: () => {
          setDone(true)
          setForm({ current: '', password: '', confirmation: '' })
        },
      },
    )
  }

  return (
    <AccountPanel title={t('account.security.passwordTitle')} lead={t('account.security.passwordLead')}>
      <form onSubmit={submit} className="grid gap-5 sm:grid-cols-2" noValidate>
        <Field id="security-current" className="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]" label={t('account.currentPassword')} error={apiFieldError(update.error, 'current_password')}>
          <Input id="security-current" type="password" autoComplete="current-password" value={form.current} onChange={(e) => edit({ current: e.target.value })} required />
        </Field>
        <Field id="security-new" label={t('account.security.newPassword')} hint={t('account.security.passwordHint')} error={apiFieldError(update.error, 'password')}>
          <Input id="security-new" type="password" autoComplete="new-password" value={form.password} onChange={(e) => edit({ password: e.target.value })} required />
        </Field>
        <Field id="security-confirm" label={t('account.security.confirmPassword')} error={mismatch ? t('validation.passwordMatch') : undefined}>
          <Input id="security-confirm" type="password" autoComplete="new-password" value={form.confirmation} onChange={(e) => edit({ confirmation: e.target.value })} required />
        </Field>
        <div className="flex flex-wrap items-center gap-4 sm:col-span-2">
          <Button type="submit" disabled={update.isPending || !form.current || !form.password || mismatch}>
            {t('account.security.passwordSave')}
          </Button>
          <SavedNote show={done}>{t('account.security.passwordSaved')}</SavedNote>
        </div>
      </form>
    </AccountPanel>
  )
}

function DevicesPanel() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const { data } = useDeviceSessions()
  const closeOne = useCloseSession()
  const closeOthers = useCloseOtherSessions()
  const logout = useLogout()
  const when = new Intl.DateTimeFormat(intlLocale(i18n.language), { dateStyle: 'medium', timeStyle: 'short' })

  const sessions = data?.sessions ?? []
  const others = sessions.filter((session) => !session.current)

  return (
    <AccountPanel
      title={t('account.security.devicesTitle')}
      lead={t('account.security.devicesLead', { count: data?.max_devices ?? 2 })}
      actions={
        others.length > 0 && (
          <Button variant="outline" size="sm" disabled={closeOthers.isPending} onClick={() => closeOthers.mutate()}>
            {t('account.security.closeOthers')}
          </Button>
        )
      }
    >
      {data && !data.supported ? (
        <p className="text-sm text-foreground/60">{t('account.security.noList')}</p>
      ) : (
        <ul className="divide-y divide-foreground/10">
          {sessions.map((session) => {
            const Icon = session.os && MOBILE.has(session.os) ? Smartphone : Monitor
            return (
              <li key={session.id} className="flex items-center gap-4 py-3.5 first:pt-0 last:pb-0">
                <Icon className="h-5 w-5 shrink-0 text-foreground/50" aria-hidden />
                <div className="min-w-0 flex-1">
                  <p className="font-medium">
                    {deviceName(session, t('account.security.unknownDevice'))}
                    {session.current && <span className="ms-2 text-sm font-normal text-primary">{t('account.security.thisDevice')}</span>}
                  </p>
                  <p className="text-sm text-foreground/55">
                    {t('account.security.lastActive', { date: when.format(new Date(session.last_active_at)) })}
                    {session.ip && (
                      <>
                        {' · '}
                        <span dir="ltr">{session.ip}</span>
                      </>
                    )}
                  </p>
                </div>
                {!session.current && (
                  <Button variant="ghost" size="sm" disabled={closeOne.isPending} onClick={() => closeOne.mutate(session.id)}>
                    {t('account.security.close')}
                  </Button>
                )}
              </li>
            )
          })}
        </ul>
      )}
      <div className="mt-6 border-t border-foreground/10 pt-5">
        <Button variant="ghost" className="-ms-3" onClick={() => logout.mutate(undefined, { onSuccess: () => navigate('/') })}>
          <LogOut className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
          {t('account.security.logout')}
        </Button>
      </div>
    </AccountPanel>
  )
}
