import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { isAxiosError } from 'axios'
import { useLogin } from '@/api/auth'
import { safeRedirect } from '@/lib/redirect'
import { BoutonEnvoi, Champ, ChampSecret, Erreur } from './fields'

// The form's rules and its request are LoginPage's, unchanged.
const schema = z.object({
  email: z.string().min(1, 'validation.required').email('validation.email'),
  password: z.string().min(1, 'validation.required'),
})

type FormValues = z.infer<typeof schema>

/** How long the check shows on the button before the next page. */
const CHECK_MS = 650

export function LoginForm({ onForgot, onRegister, active }: { onForgot: string; onRegister: { pathname: string; search: string }; active: boolean }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const login = useLogin()
  const [done, setDone] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = (values: FormValues) => {
    login.mutate(values, {
      onSuccess: () => {
        // The same destination as before; the check shows a moment first.
        setDone(true)
        window.setTimeout(() => navigate(safeRedirect(location.search, location.state), { replace: true }), CHECK_MS)
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
    <div>
      <h1 tabIndex={-1} className="titre text-[1.875rem] text-creme-100">{t('auth.loginTitle')}</h1>
      <p className="mt-1.5 text-[0.9375rem] text-creme-100/60">{t('auth.loginSubtitle')}</p>
      <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-4" noValidate>
        <Champ label={t('auth.email')} type="email" autoComplete="email" dir="ltr" error={errors.email ? t(errors.email.message!) : undefined} tabIndex={active ? undefined : -1} {...register('email')} />
        <ChampSecret label={t('auth.password')} autoComplete="current-password" error={errors.password ? t(errors.password.message!) : undefined} tabIndex={active ? undefined : -1} {...register('password')} />

        <div className="flex justify-end">
          <Link to={onForgot} className="rounded text-[0.875rem] text-creme-100/65 underline-offset-4 transition-colors hover:text-[#F1B860] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]">
            {t('auth.forgotPassword')}
          </Link>
        </div>

        {serverError && <Erreur id="connexion-erreur">{serverError}</Erreur>}

        <BoutonEnvoi state={done ? 'done' : login.isPending ? 'loading' : 'idle'}>{t('auth.submitLogin')}</BoutonEnvoi>
      </form>
      <p className="mt-7 text-center text-[0.9375rem]">
        <Link to={onRegister} state={location.state} className="rounded font-semibold text-[#F1B860] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]">
          {t('nav.register')}
        </Link>
      </p>
    </div>
  )
}
