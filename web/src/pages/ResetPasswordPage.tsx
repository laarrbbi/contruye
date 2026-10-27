import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { PASSWORD_MIN_LENGTH } from '@/lib/passwordRules'
import { Button } from '@/components/ui/button'
import { FloatingInput } from '@/components/ui/floating-input'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { useResetPassword } from '@/api/auth'
import { apiFieldError } from '@/api/orders'

const schema = z
  .object({
    password: z.string().min(PASSWORD_MIN_LENGTH, 'validation.passwordMin'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'validation.passwordMatch',
    path: ['password_confirmation'],
  })

type FormValues = z.infer<typeof schema>

export function ResetPasswordPage() {
  const { t } = useTranslation()
  usePageTitle(t('auth.resetPassword'))
  const [searchParams] = useSearchParams()
  const resetPassword = useResetPassword()

  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = (values: FormValues) => {
    resetPassword.mutate({ token, email, ...values })
  }

  // The API answers 422 on `email` when the link is expired or already used,
  // and on `password` when the password is refused (e.g. found in a leak).
  const linkError = apiFieldError(resetPassword.error, 'email')
  const passwordError = apiFieldError(resetPassword.error, 'password')
  const otherError = resetPassword.isError && !linkError && !passwordError ? t('common.error') : null

  const backToLogin = (
    <Link to="/login" className="font-medium text-primary hover:underline">
      {t('auth.backToLogin')}
    </Link>
  )

  // Opened without the token or the address (a truncated link, or typed by
  // hand): say so now rather than after the visitor has typed a password.
  if (!token || !email) {
    return (
      <AuthLayout title={t('auth.resetPassword')} footer={backToLogin}>
        <div className="space-y-4">
          <p className="text-sm leading-relaxed text-muted-foreground">{t('auth.resetLinkIncomplete')}</p>
          <Button asChild size="lg" className="w-full font-semibold">
            <Link to="/forgot-password">{t('auth.requestNewLink')}</Link>
          </Button>
        </div>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout title={t('auth.resetPassword')} subtitle={t('auth.resetPasswordSubtitle')} footer={backToLogin}>
      {resetPassword.isSuccess ? (
        <div className="space-y-4">
          <p role="status" className="rounded-lg border bg-muted/40 p-4 text-sm leading-relaxed">
            {t('auth.passwordResetSuccess')}
          </p>
          <Button asChild size="lg" className="w-full font-semibold">
            <Link to="/login">{t('auth.submitLogin')}</Link>
          </Button>
        </div>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
          {/* The account being reset, read-only. Also what lets a password
              manager save the new password against the right account. */}
          <FloatingInput
            label={t('auth.email')}
            type="email"
            autoComplete="username"
            dir="ltr"
            value={email}
            readOnly
            className="text-muted-foreground"
          />
          <FloatingInput
            label={t('auth.newPassword')}
            type="password"
            autoComplete="new-password"
            dir="ltr"
            error={errors.password ? t(errors.password.message!) : passwordError}
            {...register('password')}
          />
          <FloatingInput
            label={t('auth.passwordConfirmation')}
            type="password"
            autoComplete="new-password"
            dir="ltr"
            error={errors.password_confirmation ? t(errors.password_confirmation.message!) : undefined}
            {...register('password_confirmation')}
          />

          {/* An expired link cannot succeed however often the form is sent:
              offer the way out right where the error is. */}
          {linkError && (
            <div className="space-y-1">
              <p className="text-sm text-destructive">{linkError}</p>
              <Link to="/forgot-password" className="text-sm font-medium text-primary hover:underline">
                {t('auth.requestNewLink')}
              </Link>
            </div>
          )}
          {otherError && <p className="text-sm text-destructive">{otherError}</p>}

          <Button type="submit" size="lg" className="w-full font-semibold" disabled={resetPassword.isPending}>
            {resetPassword.isPending ? t('common.loading') : t('auth.resetPassword')}
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
