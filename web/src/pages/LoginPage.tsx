import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { isAxiosError } from 'axios'
import { Button } from '@/components/ui/button'
import { FloatingInput } from '@/components/ui/floating-input'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { useLogin } from '@/api/auth'
import { safeRedirect } from '@/lib/redirect'

const schema = z.object({
  email: z.string().min(1, 'validation.required').email('validation.email'),
  password: z.string().min(1, 'validation.required'),
})

type FormValues = z.infer<typeof schema>

export function LoginPage() {
  const { t } = useTranslation()
  usePageTitle(t('auth.loginTitle'))
  const navigate = useNavigate()
  const location = useLocation()
  const login = useLogin()

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = (values: FormValues) => {
    login.mutate(values, {
      onSuccess: () => {
        navigate(safeRedirect(location.search, location.state), { replace: true })
      },
    })
  }

  const serverError =
    login.isError && isAxiosError(login.error) && login.error.response?.status === 422
      ? t('auth.invalidCredentials')
      : login.isError
        ? t('common.error')
        : null

  return (
    <AuthLayout
      title={t('auth.loginTitle')}
      subtitle={t('auth.loginSubtitle')}
      footer={
        <p className="text-muted-foreground">
          {t('auth.noAccount')}{' '}
          <Link to={{ pathname: '/register', search: location.search }} state={location.state} className="font-medium text-primary hover:underline">
            {t('nav.register')}
          </Link>
        </p>
      }
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
        <FloatingInput
          label={t('auth.email')}
          type="email"
          autoComplete="email"
          dir="ltr"
          error={errors.email ? t(errors.email.message!) : undefined}
          {...register('email')}
        />
        <FloatingInput
          label={t('auth.password')}
          type="password"
          autoComplete="current-password"
          dir="ltr"
          error={errors.password ? t(errors.password.message!) : undefined}
          {...register('password')}
        />

        {serverError && <p className="text-sm text-destructive">{serverError}</p>}

        <Button type="submit" size="lg" className="w-full font-semibold" disabled={login.isPending}>
          {login.isPending ? t('common.loading') : t('auth.submitLogin')}
        </Button>

        <p className="text-center text-sm">
          <Link to="/forgot-password" className="text-primary hover:underline">
            {t('auth.forgotPassword')}
          </Link>
        </p>
      </form>
    </AuthLayout>
  )
}
