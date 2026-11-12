import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, MailCheck } from 'lucide-react'
import { z } from '@/lib/zod'
import { useForgotPassword } from '@/api/auth'
import { BoutonEnvoi, Champ, Erreur } from './fields'

// The form's rule and its request are ForgotPasswordPage's, unchanged.
const schema = z.object({
  email: z.string().min(1, 'validation.required').email('validation.email'),
})

type FormValues = z.infer<typeof schema>

export function ForgotForm({ onBack, active }: { onBack: { pathname: string; search: string }; active: boolean }) {
  const { t } = useTranslation()
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
    <div>
      <Link
        to={onBack}
        tabIndex={active ? undefined : -1}
        className="-my-2 -ms-1 inline-flex items-center gap-1.5 rounded-lg px-1 py-3 text-[0.875rem] text-creme-100/65 transition-colors hover:text-[#F1B860] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]"
      >
        <ArrowLeft aria-hidden className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
        {t('auth.backToLogin')}
      </Link>
      <h1 tabIndex={-1} className="titre mt-4 text-[1.875rem] text-creme-100">{t('auth.resetPassword')}</h1>
      <p className="mt-1.5 text-[0.9375rem] leading-relaxed text-creme-100/60">{t('auth.forgotShort')}</p>
      {forgotPassword.isSuccess ? (
        // Same words whether or not the address has an account.
        <p role="status" className="mt-7 flex items-start gap-3 rounded-xl bg-[#5DD39A]/10 p-4 text-[0.9375rem] leading-relaxed text-creme-100/90 ring-1 ring-[#5DD39A]/25">
          <MailCheck aria-hidden className="mt-0.5 h-5 w-5 shrink-0 text-[#5DD39A]" strokeWidth={1.75} />
          {t('auth.resetLinkSent')}
        </p>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-4" noValidate>
          <Champ label={t('auth.email')} type="email" autoComplete="email" dir="ltr" error={errors.email ? t(errors.email.message!) : undefined} tabIndex={active ? undefined : -1} {...register('email')} />

          {forgotPassword.isError && <Erreur id="oubli-erreur">{t('common.error')}</Erreur>}

          <BoutonEnvoi state={forgotPassword.isPending ? 'loading' : 'idle'}>{t('auth.sendResetLink')}</BoutonEnvoi>
        </form>
      )}
    </div>
  )
}
