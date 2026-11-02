import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { PASSWORD_MIN_LENGTH } from '@/lib/passwordRules'
import { isAxiosError } from 'axios'
import { useRegister } from '@/api/auth'
import type { Locale } from '@/api/types'
import { safeRedirect } from '@/lib/redirect'
import { BoutonEnvoi, Champ, ChampSecret, ChampTelephone, Coche, Erreur, Solidite } from './fields'

// The form's rules, its request and its error handling are RegisterPage's,
// unchanged.

// Mirrors normalisePhone() and PHONE_PATTERN in
// api/app/Http/Requests/Auth/RegisterRequest.php, step for step. A form looser
// than the API lets a number through only to have it refused after the round
// trip; a stricter one turns away a buyer the API would take.
const normalisePhone = (v: string) =>
  v
    // Listed one by one, not \s, which PHP reads as a different set: ASCII
    // whitespace, the no-break spaces of a number pasted from a web page, the
    // direction marks a number copied out of a right-to-left chat is wrapped
    // in, dots and dashes.
    .replace(/[\t\n\v\f\r \u00A0\u061C\u1680\u2000-\u200F\u2028-\u202F\u205F\u2066-\u2069\u3000\uFEFF.-]/g, '')
    // "+33 (0)6 …": the bracketed zero is not dialled from abroad.
    .replace(/\(0\)/g, '')
    .replace(/[()]/g, '')
    .replace(/^00/, '+')

// A Spanish mobile as dialled at home (6 or 7, then eight digits), or "+" and
// 8 to 15 digits (E.164), with Spain's own +34 still held to the mobile rule,
// and +33 never followed by the French home zero, which no French number has.
const PHONE_PATTERN = /^(?:[67]\d{8}|\+34[67]\d{8}|\+(?!34|330)[1-9]\d{7,14})$/

const schema = z
  .object({
    name: z.string().min(2, 'validation.required'),
    email: z.string().min(1, 'validation.required').email('validation.email'),
    phone: z
      .string()
      .min(1, 'validation.required')
      .transform(normalisePhone)
      .pipe(z.string().regex(PHONE_PATTERN, 'validation.phone')),
    password: z.string().min(PASSWORD_MIN_LENGTH, 'validation.passwordMin'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'validation.passwordMatch',
    path: ['password_confirmation'],
  })

type FormValues = z.input<typeof schema>

export function RegisterForm({ onLogin, onWelcome, active, welcomeMs }: { onLogin: { pathname: string; search: string }; onWelcome(name: string): void; active: boolean; welcomeMs: number }) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const registerMutation = useRegister()
  const [done, setDone] = useState(false)

  const {
    register,
    handleSubmit,
    setError,
    setValue,
    watch,
    formState: { errors, isSubmitted },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { phone: '' } })

  const onSubmit = (values: FormValues) => {
    const parsed = schema.parse(values)
    registerMutation.mutate(
      { ...parsed, locale: i18n.language as Locale },
      {
        // The sales page sent them here to buy. Take them there, after a short welcome.
        onSuccess: () => {
          setDone(true)
          onWelcome(parsed.name)
          window.setTimeout(() => navigate(safeRedirect(location.search, location.state), { replace: true }), welcomeMs)
        },
        onError: (error) => {
          if (isAxiosError(error) && error.response?.status === 422) {
            const fieldErrors = error.response.data.errors as Record<string, string[]> | undefined
            for (const [field, messages] of Object.entries(fieldErrors ?? {})) {
              setError(field as keyof FormValues, { message: messages[0] })
            }
          }
        },
      },
    )
  }

  /** Field message: i18n key when it came from the schema, raw text from the API. */
  const msg = (name: keyof FormValues) => {
    const message = errors[name]?.message
    if (!message) return undefined
    return message.startsWith('validation.') ? t(message) : message
  }

  const serverError =
    registerMutation.isError && !isAxiosError(registerMutation.error)
      ? t('common.error')
      : registerMutation.isError && isAxiosError(registerMutation.error) &&
          registerMutation.error.response?.status !== 422
        ? t('common.error')
        : null

  const password = watch('password') ?? ''
  const confirmation = watch('password_confirmation') ?? ''
  const matches = confirmation.length > 0 && confirmation === password
  const tab = active ? undefined : -1
  // The phone field is registered by name; its value is the whole number, set by ChampTelephone.
  const phone = register('phone')

  return (
    <div>
      <h1 tabIndex={-1} className="titre text-[1.875rem] text-creme-100">{t('auth.registerTitle')}</h1>
      <p className="mt-1.5 text-[0.9375rem] text-creme-100/60">{t('auth.registerSubtitle')}</p>
      <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-4" noValidate>
        <Champ label={t('auth.name')} autoComplete="name" error={msg('name')} tabIndex={tab} {...register('name')} />
        <Champ label={t('auth.email')} type="email" autoComplete="email" dir="ltr" error={msg('email')} tabIndex={tab} {...register('email')} />
        <ChampTelephone
          label={t('auth.phone')}
          name={phone.name}
          error={msg('phone')}
          onNumber={(value) => setValue('phone', value, { shouldDirty: true, shouldValidate: isSubmitted })}
        />
        <div>
          <ChampSecret label={t('auth.password')} autoComplete="new-password" error={msg('password')} tabIndex={tab} {...register('password')} />
          <Solidite value={password} />
        </div>
        <ChampSecret
          label={t('auth.passwordConfirmation')}
          autoComplete="new-password"
          error={msg('password_confirmation')}
          tabIndex={tab}
          extra={matches ? <Coche label={t('auth.passwordsMatch')} /> : undefined}
          {...register('password_confirmation')}
        />

        {serverError && <Erreur id="inscription-erreur">{serverError}</Erreur>}

        <BoutonEnvoi state={done ? 'done' : registerMutation.isPending ? 'loading' : 'idle'}>{t('auth.submitRegister')}</BoutonEnvoi>
      </form>
      <p className="mt-7 text-center text-[0.9375rem]">
        <Link to={onLogin} state={location.state} className="rounded font-semibold text-[#F1B860] underline-offset-4 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#F1B860]">
          {t('nav.login')}
        </Link>
      </p>
    </div>
  )
}
