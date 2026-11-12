import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight } from 'lucide-react'
import { useCourse } from '@/api/courses'
import { useSolarOptions } from '@/api/solar'
import { BANDE, H2, TEXTE } from '@/components/landing/kit/type'
import { NewsletterSignup } from '@/components/newsletter/NewsletterSignup'
import { SolarCalculator } from '@/components/solar/SolarCalculator'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath, SOLAR_CALCULATOR_PATH } from '@/i18n/paths'
import { MAIN_COURSE_SLUG } from '@/lib/course'
import { phaseModules } from '@/lib/modules'
import { cn, formatPrice, intlLocale } from '@/lib/utils'

/** The questions, in the order the server's page shows them (SeoPageController::SOLAR_FAQ). */
const FAQ = ['taxes', 'vat', 'perM2', 'plusvalia', 'rates', 'full'] as const

/**
 * The solar calculator's own page, for people who search what a plot costs:
 * the calculator, how the cost is made up, the ITP and AJD of every
 * community as the template applies them, the questions, and the course.
 * The server renders the same words for search engines (seo/solar-calculator);
 * where it does not (the Vercel preview), the page writes its own structured
 * data, so Google reads the tool and its questions either way.
 */
export function SolarCalculatorPage() {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  const options = useSolarOptions()
  const { data: course } = useCourse(MAIN_COURSE_SLUG)
  usePageTitle(t('solar.seoTitle'), t('solar.seoDescription'))

  const percent = (value: number) => new Intl.NumberFormat(intlLocale(locale), { style: 'percent', maximumFractionDigits: 2 }).format(value)
  const vat = options.data ? percent(options.data.vat) : ''
  const reviewed = options.data?.reviewed_at
    ? new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'long' }).format(new Date(options.data.reviewed_at))
    : null
  const module = course ? phaseModules(course.modules)[0] : undefined
  const home = localizedPath('/', locale)

  // Structured data, unless the server already wrote it into the page's head.
  useEffect(() => {
    if (!options.data || document.head.querySelector('script[type="application/ld+json"]')) return
    const url = window.location.origin + localizedPath(SOLAR_CALCULATOR_PATH, locale)
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.page = 'solar'
    script.text = JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'WebApplication',
          name: t('solar.pageTitle'),
          description: t('solar.seoDescription'),
          url,
          inLanguage: locale,
          applicationCategory: 'FinanceApplication',
          operatingSystem: 'Web',
          isAccessibleForFree: true,
          offers: { '@type': 'Offer', price: '0', priceCurrency: 'EUR' },
        },
        {
          '@type': 'FAQPage',
          mainEntity: FAQ.map((key) => ({
            '@type': 'Question',
            name: t(`solar.faq.${key}.q`),
            acceptedAnswer: { '@type': 'Answer', text: t(`solar.faq.${key}.a`, { vat }) },
          })),
        },
      ],
    }).replace(/</g, '\\u003c')
    document.head.appendChild(script)
    return () => script.remove()
  }, [options.data, locale, t, vat])

  return (
    <div className="bg-creme-100 text-plume">
      <section data-tone="paper" className={cn(BANDE, 'grid gap-12 !pt-16 lg:grid-cols-12 lg:gap-16 lg:!pt-24')}>
        <header className="lg:col-span-5">
          <p className="etiquette flex items-center gap-3 text-plume/70">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {t('solar.eyebrow')} · {t('solar.free')}
          </p>
          <h1 className="titre mt-5 text-balance text-[clamp(2.4rem,1.4rem+3.6vw,4.2rem)]">{t('solar.pageTitle')}</h1>
          <p className={cn(TEXTE, 'mt-6 text-plume/85')}>{t('solar.lead')}</p>

          <h2 className="mt-12 text-lg font-semibold">{t('solar.how.title')}</h2>
          <dl className="mt-4 space-y-4 text-[0.9375rem] leading-relaxed">
            {(
              [
                ['itpTerm', 'itp', ': '],
                ['ajdTerm', 'ajd', ': '],
                ['costTerm', 'cost', ' = '],
                ['perM2Term', 'perM2', ' = '],
              ] as const
            ).map(([term, text, joint]) => (
              <div key={term} className="border-s-2 border-or/60 ps-4">
                <dt className="inline font-semibold">{t(`solar.how.${term}`)}</dt>
                {joint}
                <dd className="inline text-plume/80">{t(`solar.how.${text}`, { vat })}</dd>
              </div>
            ))}
          </dl>
        </header>

        <div className="min-w-0 lg:col-span-7">
          <SolarCalculator
            unlock={{
              to: `${home}#programme`,
              label: module?.price_eur
                ? `${t('solar.locked.cta')} · ${t('solar.locked.ctaPrice', { price: formatPrice(module.price_eur, 'EUR', locale) })}`
                : t('solar.locked.cta'),
            }}
          />
          <p className="mt-4 text-xs leading-relaxed text-plume/55">{t('solar.disclaimer')}</p>
        </div>
      </section>

      {/* The rates of every community, as the template applies them. */}
      <section className="container pb-20 lg:pb-28">
        <h2 className={cn(H2, 'max-w-[22ch]')}>{t('solar.rates.title')}</h2>
        {reviewed && <p className="mt-4 max-w-2xl text-plume/70">{t('solar.rates.lead', { date: reviewed })}</p>}
        <div className="mt-8 overflow-x-auto rounded-2xl ring-1 ring-plume/10">
          <table className="w-full min-w-[36rem] border-collapse bg-white/70 text-start text-[0.9375rem]">
            <thead>
              <tr className="border-b border-plume/10 text-[0.8125rem] text-plume/60">
                <th scope="col" className="px-4 py-3 text-start font-medium">{t('solar.rates.community')}</th>
                <th scope="col" className="px-4 py-3 text-end font-medium">{t('solar.rates.itp')}</th>
                <th scope="col" className="px-4 py-3 text-end font-medium">{t('solar.rates.ajd')}</th>
                <th scope="col" className="px-4 py-3 text-start font-medium">{t('solar.rates.note')}</th>
              </tr>
            </thead>
            <tbody>
              {(options.data?.regions ?? []).map((region) => (
                <tr key={region.name} className="border-b border-plume/[0.06] last:border-0">
                  <th scope="row" className="px-4 py-3 text-start font-medium">{region.name}</th>
                  <td className="px-4 py-3 text-end tabular-nums">{percent(region.itp)}</td>
                  <td className="px-4 py-3 text-end tabular-nums">{percent(region.ajd)}</td>
                  {/* The package's notes are Spanish, whatever the page's language. */}
                  <td lang="es" className="px-4 py-3 text-sm text-plume/65">{region.note}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="container grid gap-10 border-t border-plume/15 py-20 lg:grid-cols-12 lg:gap-16 lg:py-28">
        <h2 className={cn(H2, 'lg:col-span-5')}>{t('solar.faq.title')}</h2>
        <Accordion type="multiple" className="lg:col-span-7">
          {FAQ.map((key) => (
            <AccordionItem key={key} value={key} className="border-plume/15">
              <AccordionTrigger className="text-start text-[1.0625rem] font-semibold hover:no-underline">{t(`solar.faq.${key}.q`)}</AccordionTrigger>
              <AccordionContent className="text-[0.9375rem] leading-relaxed text-plume/80">{t(`solar.faq.${key}.a`, { vat })}</AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </section>

      {/* The newsletter: when the rates change, the people who use the calculator hear of it. */}
      <section className="container grid gap-8 border-t border-plume/15 py-16 lg:grid-cols-12 lg:gap-16 lg:py-20">
        <div className="lg:col-span-5">
          <h2 className="titre text-[clamp(1.6rem,1.25rem+1.2vw,2.25rem)]">{t('newsletter.title')}</h2>
          <p className="mt-4 text-plume/80">{t('newsletter.lead')}</p>
        </div>
        <div className="flex items-end lg:col-span-7">
          <NewsletterSignup source="calculator" tone="paper" className="w-full" />
        </div>
      </section>

      <section className="theme-night bg-[linear-gradient(160deg,#16294A,#0B1A2E_60%,#070F1C)] text-creme-100">
        <div className="container flex flex-col gap-6 py-16 sm:flex-row sm:items-end sm:justify-between lg:py-20">
          <div className="max-w-2xl">
            <h2 className="titre text-[clamp(1.75rem,1.3rem+1.6vw,2.6rem)]">{t('solar.course.title')}</h2>
            <p className="mt-4 text-creme-100/80">{t('solar.course.body')}</p>
          </div>
          <Link
            to={home}
            className="group inline-flex shrink-0 items-center gap-2 rounded-xl bg-or px-6 py-3.5 font-semibold text-plume transition-[filter] hover:brightness-105"
          >
            {t('solar.course.cta')}
            <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100" aria-hidden />
          </Link>
        </div>
      </section>
    </div>
  )
}
