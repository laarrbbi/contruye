import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight } from 'lucide-react'
import { TEXTE } from '@/components/landing/kit/type'
import { NewsletterSignup } from '@/components/newsletter/NewsletterSignup'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'
import { cn } from '@/lib/utils'

/**
 * The next course, announced and nothing more: a course on building data
 * centers is being prepared. No programme, no price, no date, because none
 * exists yet (constitution I). Whoever wants to know when it opens signs up
 * to the newsletter (source «datacenters», so the owner can count them). The
 * server renders the same page for search engines (seo/data-centers).
 */
export function DataCentersPage() {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  usePageTitle(t('dataCenters.seoTitle'), t('dataCenters.seoDescription'))

  return (
    <div className="bg-creme-100 text-plume">
      <section
        data-tone="night"
        className="theme-night relative overflow-hidden bg-[radial-gradient(110%_90%_at_80%_10%,#12253F,#0B1A2E_55%,#070F1C)] text-creme-100"
      >
        {/* The plan grid of the night sections. */}
        <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:48px_48px]" />
        <div className="container relative py-20 lg:py-32">
          <div className="max-w-3xl">
            <p className="etiquette flex items-center gap-3 text-or">
              <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
              {t('dataCenters.eyebrow')}
            </p>
            <h1 className="titre mt-6 text-balance text-[clamp(2.4rem,1.3rem+4vw,4.75rem)]">{t('dataCenters.title')}</h1>
            <p className={cn(TEXTE, 'mt-6 max-w-2xl text-creme-100/80')}>{t('dataCenters.lead')}</p>

            <div className="mt-12 max-w-xl">
              <p className="mb-4 text-[1.0625rem] leading-relaxed text-creme-100/85">{t('dataCenters.notify')}</p>
              <NewsletterSignup source="datacenters" tone="night" />
            </div>
          </div>
        </div>
      </section>

      <section data-tone="paper" className="container py-14 lg:py-20">
        <p className="etiquette text-plume/60">{t('dataCenters.meanwhile')}</p>
        <Link to={localizedPath('/', locale)} className="group mt-4 inline-flex items-center gap-2 text-lg font-semibold text-primary">
          {t('dataCenters.current')}
          <ArrowRight className="h-5 w-5 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" aria-hidden />
        </Link>
      </section>
    </div>
  )
}
