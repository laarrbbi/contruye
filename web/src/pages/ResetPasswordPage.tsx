import type { ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { PASSWORD_MIN_LENGTH } from '@/lib/passwordRules'
import { ArrowLeft, ArrowRight, MailCheck } from 'lucide-react'
import { NuitAuth } from '@/pages/auth/NuitAuth'
import { BoutonEnvoi, Champ, ChampSecret, Erreur } from '@/pages/auth/fields'
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
    <Link
      to="/login"
      className="-ms-1 inline-flex items-center gap-1.5 rounded-lg px-1 py-1 text-[0.875rem] text-creme-100/65 transition-colors hover:text-[#F1B860] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]"
    >
      <ArrowLeft aria-hidden className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
      {t('auth.backToLogin')}
    </Link>
  )
  // A link dressed as the main button (amber, the arrow in its dark square).
  const lienOr = (to: string, label: string) => (
    <Link to={to} className="btn-or w-full">
      <span className="min-w-0 truncate">{label}</span>
      <span aria-hidden className="btn-or__fleche">
        <ArrowRight className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
      </span>
    </Link>
  )
  const carte = (children: ReactNode) => (
    <NuitAuth>
      <div className="verre p-6 sm:p-9">
        {backToLogin}
        <h1 className="titre mt-4 text-[1.875rem] text-creme-100">{t('auth.resetPassword')}</h1>
        {children}
      </div>
    </NuitAuth>
  )

  // Opened without the token or the address (a truncated link, or typed by
  // hand): say so now rather than after the visitor has typed a password.
  if (!token || !email) {
    return carte(
      <div className="mt-6 space-y-6">
        <p className="text-[0.9375rem] leading-relaxed text-creme-100/70">{t('auth.resetLinkIncomplete')}</p>
        {lienOr('/forgot-password', t('auth.requestNewLink'))}
      </div>,
    )
  }

  return carte(
    resetPassword.isSuccess ? (
      <div className="mt-6 space-y-6">
        <p role="status" className="flex items-start gap-3 rounded-xl bg-[#5DD39A]/10 p-4 text-[0.9375rem] leading-relaxed text-creme-100/90 ring-1 ring-[#5DD39A]/25">
          <MailCheck aria-hidden className="mt-0.5 h-5 w-5 shrink-0 text-[#5DD39A]" strokeWidth={1.75} />
          {t('auth.passwordResetSuccess')}
        </p>
        {lienOr('/login', t('auth.submitLogin'))}
      </div>
    ) : (
      <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-4" noValidate>
        {/* The account being reset, read-only. Also what lets a password
            manager save the new password against the right account. */}
        <Champ label={t('auth.email')} type="email" autoComplete="username" dir="ltr" value={email} readOnly className="text-creme-100/60" />
        <ChampSecret
          label={t('auth.newPassword')}
          autoComplete="new-password"
          error={errors.password ? t(errors.password.message!) : passwordError}
          {...register('password')}
        />
        <ChampSecret
          label={t('auth.passwordConfirmation')}
          autoComplete="new-password"
          error={errors.password_confirmation ? t(errors.password_confirmation.message!) : undefined}
          {...register('password_confirmation')}
        />

        {/* An expired link cannot succeed however often the form is sent:
            offer the way out right where the error is. */}
        {linkError && (
          <div className="space-y-1.5">
            <Erreur id="reinitialisation-lien">{linkError}</Erreur>
            <Link to="/forgot-password" className="ms-1 rounded text-[0.875rem] font-semibold text-[#F1B860] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]">
              {t('auth.requestNewLink')}
            </Link>
          </div>
        )}
        {otherError && <Erreur id="reinitialisation-erreur">{otherError}</Erreur>}

        <BoutonEnvoi state={resetPassword.isPending ? 'loading' : 'idle'}>{t('auth.resetPassword')}</BoutonEnvoi>
      </form>
    ),
  )
}
