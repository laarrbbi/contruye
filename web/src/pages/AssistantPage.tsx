import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, BookOpenText, Calculator, Coins, Languages, LockKeyhole } from 'lucide-react'
import { useUser } from '@/api/auth'
import { useAssistantStatus, usePublicAssistant } from '@/api/assistant'
import { AssistantChat } from '@/components/assistant/AssistantChat'
import { TEXTE } from '@/components/landing/kit/type'
import { NewsletterSignup } from '@/components/newsletter/NewsletterSignup'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'
import { cn } from '@/lib/utils'

const FEATURES = [
  { key: 'course', icon: BookOpenText },
  { key: 'templates', icon: Calculator },
  { key: 'language', icon: Languages },
  { key: 'private', icon: LockKeyhole },
  { key: 'credits', icon: Coins },
] as const

/**
 * Nicolás, the course's AI assistant. Until it opens to students the page
 * says what it will do, as built (constitution I: no date, no price), and
 * offers the newsletter (source «assistant»). An admin sees the chat as soon
 * as the key is set, marked as a preview; a student sees it once it is
 * launched (switched on, and its evaluation passed). The server renders the
 * same words for search engines (seo/assistant).
 */
export function AssistantPage() {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  const { data: user } = useUser()
  const { data: status } = useAssistantStatus(Boolean(user))
  const { data: opened } = usePublicAssistant()
  usePageTitle(t('assistantPage.seoTitle'), t('assistantPage.seoDescription'))

  const chat = Boolean(user && status?.available)
  const launched = Boolean(status?.launched ?? opened?.launched)

  return (
    <div className="bg-creme-100 text-plume">
      <section
        data-tone="night"
        className="theme-night relative overflow-hidden bg-[radial-gradient(110%_90%_at_80%_10%,#12253F,#0B1A2E_55%,#070F1C)] text-creme-100"
      >
        <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:48px_48px]" />
        <div className={cn('container relative', chat ? 'py-12 lg:py-16' : 'py-20 lg:py-32')}>
          <div className="max-w-3xl">
            <p className="etiquette flex items-center gap-3 text-or">
              <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
              {launched ? t('nav.assistant') : t('assistantPage.eyebrow')}
            </p>
            <h1 className="titre mt-6 text-balance text-[clamp(2.2rem,1.3rem+3.6vw,4.25rem)]">{t('assistantPage.title')}</h1>
            <p className={cn(TEXTE, 'mt-6 max-w-2xl text-creme-100/80')}>{t('assistantPage.lead')}</p>
            {!chat && !launched && (
              <div className="mt-12 max-w-xl">
                <p className="mb-4 text-[1.0625rem] leading-relaxed text-creme-100/85">{t('assistantPage.notify')}</p>
                <NewsletterSignup source="assistant" tone="night" />
              </div>
            )}
          </div>
        </div>
      </section>

      {chat && status ? (
        <section data-tone="paper" className="container py-8 lg:py-12">
          {status.preview && (
            <p className="mb-5 rounded-lg border border-or/40 bg-or/10 px-4 py-3 text-sm text-plume">{t('assistantPage.preview')}</p>
          )}
          <AssistantChat status={status} />
        </section>
      ) : (
        <>
          <Access launched={launched} signedIn={Boolean(user)} reason={status?.reason ?? null} locale={locale} />
          <section data-tone="paper" className="container py-14 lg:py-20">
            <h2 className="titre text-[clamp(1.6rem,1.2rem+1.4vw,2.4rem)]">{t('assistantPage.featuresTitle')}</h2>
            <ul className="mt-8 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
              {FEATURES.map(({ key, icon: Icon }) => (
                <li key={key}>
                  <Icon className="h-6 w-6 text-primary" aria-hidden strokeWidth={1.75} />
                  <h3 className="mt-3 text-lg font-semibold">{t(`assistantPage.features.${key}.title`)}</h3>
                  <p className="mt-1.5 leading-relaxed text-plume/75">{t(`assistantPage.features.${key}.body`)}</p>
                </li>
              ))}
            </ul>
            <p className="mt-10 max-w-2xl text-sm text-plume/60">{t('assistantPage.disclaimer')}</p>

            <p className="etiquette mt-14 text-plume/60">{t('assistantPage.meanwhile')}</p>
            <Link to={localizedPath('/', locale)} className="group mt-4 inline-flex items-center gap-2 text-lg font-semibold text-primary">
              {t('assistantPage.current')}
              <ArrowRight className="h-5 w-5 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" aria-hidden />
            </Link>
          </section>
        </>
      )}
    </div>
  )
}

/** Once launched: how to reach the chat for whoever cannot see it yet. */
function Access({ launched, signedIn, reason, locale }: { launched: boolean; signedIn: boolean; reason: string | null; locale: Locale }) {
  const { t } = useTranslation()
  if (!launched) return null

  const [text, action, to] = !signedIn
    ? [t('assistantPage.signIn'), t('assistantPage.signInAction'), '/login']
    : reason === 'not_enrolled'
      ? [t('assistantPage.enrolOnly'), t('assistantPage.enrolAction'), localizedPath('/', locale)]
      : [null, null, null]
  if (!text || !to) return null

  return (
    <section data-tone="paper" className="container pt-10">
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card px-5 py-4">
        <p className="font-medium">{text}</p>
        <Link to={to} className="inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground">
          {action}
        </Link>
      </div>
    </section>
  )
}
