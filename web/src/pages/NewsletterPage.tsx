import { useEffect } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useNewsletterToken } from '@/api/newsletter'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'

/**
 * Where the newsletter's links land. Confirming happens here, on the page's
 * load (a mail scanner that opens the link does not run it); leaving takes a
 * click, so nobody is unsubscribed by a preview.
 */
export function NewsletterPage() {
  const { t, i18n } = useTranslation()
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const action = params.get('action') === 'unsubscribe' ? 'unsubscribe' : 'confirm'
  const confirm = useNewsletterToken('confirm')
  const unsubscribe = useNewsletterToken('unsubscribe')
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  usePageTitle(t('newsletter.title'))

  const { mutate: confirmToken } = confirm
  useEffect(() => {
    if (action === 'confirm' && token.length === 64) confirmToken(token)
  }, [action, token, confirmToken])

  const done = action === 'confirm' ? confirm : unsubscribe
  const invalid = token.length !== 64 || done.isError

  return (
    <div className="bg-creme-100 text-plume">
      <div className="container flex min-h-[60vh] max-w-xl flex-col justify-center gap-5 py-20">
        <p className="etiquette flex items-center gap-3 text-plume/70">
          <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
          {t('newsletter.title')}
        </p>

        {invalid ? (
          <h1 className="titre text-[clamp(1.75rem,1.3rem+1.6vw,2.6rem)]">{t('newsletter.page.invalid')}</h1>
        ) : action === 'confirm' ? (
          confirm.isSuccess ? (
            <>
              <h1 className="titre text-[clamp(1.75rem,1.3rem+1.6vw,2.6rem)]">{t('newsletter.page.confirmed')}</h1>
              <p className="text-lg text-plume/80">{t('newsletter.page.confirmedBody', { email: confirm.data.email })}</p>
            </>
          ) : (
            <h1 role="status" className="titre text-[clamp(1.5rem,1.2rem+1.2vw,2.2rem)] text-plume/70">{t('newsletter.page.confirming')}</h1>
          )
        ) : unsubscribe.isSuccess ? (
          <>
            <h1 className="titre text-[clamp(1.75rem,1.3rem+1.6vw,2.6rem)]">{t('newsletter.page.unsubscribed')}</h1>
            <p className="text-lg text-plume/80">{t('newsletter.page.unsubscribedBody', { email: unsubscribe.data.email })}</p>
          </>
        ) : (
          <>
            <h1 className="titre text-[clamp(1.75rem,1.3rem+1.6vw,2.6rem)]">{t('newsletter.page.unsubscribeTitle')}</h1>
            <p className="text-lg text-plume/80">{t('newsletter.page.unsubscribeBody')}</p>
            <button
              type="button"
              disabled={unsubscribe.isPending}
              onClick={() => unsubscribe.mutate(token)}
              className="self-start rounded-xl bg-plume px-6 py-3 font-semibold text-creme-100 transition-opacity hover:opacity-90 disabled:opacity-60"
            >
              {t('newsletter.page.unsubscribeButton')}
            </button>
          </>
        )}

        <Link to={localizedPath('/', locale)} className="mt-4 self-start font-medium text-plume underline decoration-or decoration-2 underline-offset-[6px]">
          {t('newsletter.page.back')}
        </Link>
      </div>
    </div>
  )
}
