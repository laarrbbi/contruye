import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Check } from 'lucide-react'
import { apiFieldError } from '@/api/orders'
import { useNewsletterSignup, type NewsletterSource } from '@/api/newsletter'
import { cn } from '@/lib/utils'

/**
 * The newsletter's form: one field, double opt-in. What the address is for
 * and how to leave are said under the button, before anything is sent.
 */
export function NewsletterSignup({ source, tone, className }: { source: NewsletterSource; tone: 'night' | 'paper'; className?: string }) {
  const { t, i18n } = useTranslation()
  const signup = useNewsletterSignup()
  const [email, setEmail] = useState('')
  // A field people never see; a bot that fills every input fills this one too.
  const [website, setWebsite] = useState('')
  const night = tone === 'night'
  const id = `newsletter-${source}`

  const submit = (event: FormEvent) => {
    event.preventDefault()
    signup.mutate({ email, locale: i18n.language, source, website })
  }

  if (signup.isSuccess) {
    return (
      <p role="status" className={cn('flex items-start gap-3 text-[1.0625rem] leading-relaxed', night ? 'text-creme-100' : 'text-plume', className)}>
        <Check className="mt-1 h-5 w-5 shrink-0 text-or" aria-hidden />
        {t('newsletter.done')}
      </p>
    )
  }

  const fieldError = apiFieldError(signup.error, 'email')

  return (
    <form onSubmit={submit} noValidate className={className}>
      <label htmlFor={id} className="sr-only">
        {t('newsletter.email')}
      </label>
      <div className={cn('flex flex-col gap-2 rounded-2xl p-1.5 sm:flex-row', night ? 'bg-creme-100/[0.08] ring-1 ring-creme-100/15' : 'bg-white ring-1 ring-plume/10')}>
        <input
          id={id}
          type="email"
          dir="ltr"
          required
          autoComplete="email"
          placeholder={t('newsletter.placeholder')}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          aria-invalid={fieldError ? true : undefined}
          aria-describedby={`${id}-notice`}
          className={cn(
            'h-12 min-w-0 flex-1 rounded-xl bg-transparent px-4 text-start text-[1.0625rem] outline-none',
            night ? 'text-creme-100 placeholder:text-creme-100/40' : 'text-plume placeholder:text-plume/40',
          )}
        />
        <input
          type="text"
          name="website"
          tabIndex={-1}
          autoComplete="off"
          aria-hidden
          value={website}
          onChange={(event) => setWebsite(event.target.value)}
          className="absolute -left-[9999px] h-0 w-0 opacity-0"
        />
        <button
          type="submit"
          disabled={signup.isPending || email.trim() === ''}
          className="group inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-or px-6 font-semibold text-plume transition-[filter,opacity] duration-150 hover:brightness-105 disabled:opacity-60"
        >
          {signup.isPending ? t('newsletter.sending') : t('newsletter.submit')}
          <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100" aria-hidden />
        </button>
      </div>
      {(fieldError || signup.isError) && (
        <p role="alert" className={cn('mt-3 text-sm', night ? 'text-creme-100/90' : 'text-destructive')}>
          {fieldError ?? t('newsletter.error')}
        </p>
      )}
      <p id={`${id}-notice`} className={cn('mt-3 text-xs leading-relaxed', night ? 'text-creme-100/55' : 'text-plume/55')}>
        {t('newsletter.notice')}
      </p>
    </form>
  )
}
