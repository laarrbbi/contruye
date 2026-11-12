import { useEffect, useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useUpdateEmail } from '@/api/account'
import { apiFieldError } from '@/api/orders'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Field } from './AccountPanel'

/** The e-mail is the sign-in: it changes only with the current password. */
export function EmailDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const update = useUpdateEmail()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  useEffect(() => {
    if (!open) return
    setEmail('')
    setPassword('')
    update.reset()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const submit = (event: FormEvent) => {
    event.preventDefault()
    update.mutate({ email, current_password: password }, { onSuccess: () => onOpenChange(false) })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <form onSubmit={submit} className="space-y-5" noValidate>
          <DialogHeader>
            <DialogTitle>{t('account.profile.emailDialogTitle')}</DialogTitle>
            <DialogDescription>{t('account.profile.emailDialogBody')}</DialogDescription>
          </DialogHeader>
          <Field id="account-new-email" label={t('account.profile.newEmail')} error={apiFieldError(update.error, 'email')}>
            <Input id="account-new-email" type="email" dir="ltr" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </Field>
          <Field id="account-email-password" label={t('account.currentPassword')} error={apiFieldError(update.error, 'current_password')}>
            <Input
              id="account-email-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </Field>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={update.isPending || !email || !password}>
              {t('account.profile.emailSave')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
