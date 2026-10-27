import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { Button } from '@/components/ui/button'
import { FloatingInput } from '@/components/ui/floating-input'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { useForgotPassword } from '@/api/auth'

const schema = z.object({
  email: z.string().min(1, 'validation.required').email('validation.email'),
})

type FormValues = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  usePageTitle(t('auth.resetPassword'))
  const forgotPassword = useForgotPassword()

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = (values: FormValues) => {
    forgotPassword.mutate(values)
  }

  return (
    <AuthLayout
      title={t('auth.resetPassword')}
      subtitle={t('auth.forgotPasswordSubtitle')}
      footer={
        <Link to="/login" className="font-medium text-primary hover:underline">
          {t('auth.backToLogin')}
        </Link>
      }
    >
      {forgotPassword.isSuccess ? (
        // Same words whether or not the address has an account.
        <p role="status" className="rounded-lg border bg-muted/40 p-4 text-sm leading-relaxed">
          {t('auth.resetLinkSent')}
        </p>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
          <FloatingInput
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            dir="ltr"
            error={errors.email ? t(errors.email.message!) : undefined}
            {...register('email')}
          />

          {forgotPassword.isError && <p className="text-sm text-destructive">{t('common.error')}</p>}

          <Button type="submit" size="lg" className="w-full font-semibold" disabled={forgotPassword.isPending}>
            {forgotPassword.isPending ? t('common.loading') : t('auth.sendResetLink')}
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
