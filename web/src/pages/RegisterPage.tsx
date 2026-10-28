import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from '@/lib/zod'
import { PASSWORD_MIN_LENGTH } from '@/lib/passwordRules'
import { isAxiosError } from 'axios'
import { Button } from '@/components/ui/button'
import { FloatingInput } from '@/components/ui/floating-input'
import { AuthLayout } from '@/components/layout/AuthLayout'
import { useRegister } from '@/api/auth'
import type { Locale } from '@/api/types'
import { safeRedirect } from '@/lib/redirect'

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
// 8 to 15 digits (E.164), with Spain's own +34 still held to the mobile rule.
const PHONE_PATTERN = /^(?:[67]\d{8}|\+34[67]\d{8}|\+(?!34)[1-9]\d{7,14})$/

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

export function RegisterPage() {
  const { t, i18n } = useTranslation()
  usePageTitle(t('auth.registerTitle'))
  const navigate = useNavigate()
  const location = useLocation()
  const registerMutation = useRegister()

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const onSubmit = (values: FormValues) => {
    const parsed = schema.parse(values)
    registerMutation.mutate(
      { ...parsed, locale: i18n.language as Locale },
      {
        // The sales page sent them here to buy. Take them there.
        onSuccess: () => navigate(safeRedirect(location.search, location.state), { replace: true }),
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

  return (
    <AuthLayout
      title={t('auth.registerTitle')}
      subtitle={t('auth.registerSubtitle')}
      footer={
        <p className="text-muted-foreground">
          {t('auth.haveAccount')}{' '}
          <Link to={{ pathname: '/login', search: location.search }} state={location.state} className="font-medium text-primary hover:underline">
            {t('nav.login')}
          </Link>
        </p>
      }
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
        <FloatingInput
          label={t('auth.name')}
          autoComplete="name"
          error={msg('name')}
          {...register('name')}
        />
        <FloatingInput
          label={t('auth.email')}
          type="email"
          autoComplete="email"
          dir="ltr"
          error={msg('email')}
          {...register('email')}
        />
        {/* The example, shown once the field has focus and the label has
            moved out of the way, is what tells a buyer abroad to type the
            country code. Any non-empty placeholder keeps the label's
            :placeholder-shown trick working. */}
        <FloatingInput
          label={t('auth.phone')}
          placeholder={t('auth.phonePlaceholder')}
          className="focus:placeholder:text-muted-foreground"
          type="tel"
          inputMode="tel"
          autoComplete="tel"
          dir="ltr"
          error={msg('phone')}
          {...register('phone')}
        />
        <FloatingInput
          label={t('auth.password')}
          type="password"
          autoComplete="new-password"
          dir="ltr"
          error={msg('password')}
          {...register('password')}
        />
        <FloatingInput
          label={t('auth.passwordConfirmation')}
          type="password"
          autoComplete="new-password"
          dir="ltr"
          error={msg('password_confirmation')}
          {...register('password_confirmation')}
        />

        {serverError && <p className="text-sm text-destructive">{serverError}</p>}

        <Button type="submit" size="lg" className="w-full font-semibold" disabled={registerMutation.isPending}>
          {registerMutation.isPending ? t('common.loading') : t('auth.submitRegister')}
        </Button>
      </form>
    </AuthLayout>
  )
}
