import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight } from 'lucide-react'
import type { CourseDetail } from '@/api/courses'
import { BANDE, H2, TEXTE } from '@/components/landing/kit/type'
import { SolarCalculator, type SolarUnlock } from '@/components/solar/SolarCalculator'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath, SOLAR_CALCULATOR_PATH } from '@/i18n/paths'
import { phaseModules } from '@/lib/modules'
import { cn, formatPrice } from '@/lib/utils'

/**
 * Before the syllabus and its prices, the course's first tool to try: what a
 * plot really costs, with its taxes. A sample of Module 1's template (four
 * inputs, three results); the rest of it is one click away, in Module 1.
 */
export function Calculadora({
  course,
  purchaseLink,
  checkoutPath,
}: {
  course: CourseDetail
  purchaseLink(target: string): { to: string; state?: { from: string } }
  checkoutPath: string
}) {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  const module = phaseModules(course.modules)[0]

  // Its owner opens the full template; anyone else buys Module 1 (or the course).
  const owned = course.full_access || module?.is_owned
  const unlock: SolarUnlock = owned && module
    ? { to: `/learn/${course.slug}/modules/${module.id}/plantilla`, label: t('solar.locked.open') }
    : {
        ...purchaseLink(module ? `${checkoutPath}?scope=modules&modules=${module.id}` : checkoutPath),
        label: module?.price_eur
          ? `${t('solar.locked.cta')} · ${t('solar.locked.ctaPrice', { price: formatPrice(module.price_eur, 'EUR', i18n.language) })}`
          : t('solar.locked.cta'),
      }

  return (
    <section id="calculadora" data-tone="paper" aria-labelledby="calc-titre" className="relative scroll-mt-16 bg-creme-100 text-plume">
      <div className={cn(BANDE, 'grid gap-12 lg:grid-cols-12 lg:gap-16')}>
        <div className="lg:col-span-5 lg:pt-6">
          <p className="etiquette flex items-center gap-3 text-plume/70">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {t('solar.eyebrow')} · {t('solar.free')}
          </p>
          <h2 id="calc-titre" className={cn(H2, 'mt-5 max-w-[15ch]')}>
            {t('solar.sectionTitle')}
          </h2>
          <p className={cn(TEXTE, 'mt-6 max-w-[34rem] text-plume/85')}>{t('solar.sectionLead')}</p>
          <Link
            to={localizedPath(SOLAR_CALCULATOR_PATH, locale)}
            className="group mt-8 inline-flex items-center gap-2 font-semibold text-plume underline decoration-or decoration-2 underline-offset-[6px] transition-colors hover:text-or-fonce"
          >
            {t('solar.pageLink')}
            <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100" aria-hidden />
          </Link>
        </div>
        <div className="min-w-0 lg:col-span-7">
          <SolarCalculator unlock={unlock} />
          <p className="mt-4 text-xs leading-relaxed text-plume/55">{t('solar.disclaimer')}</p>
        </div>
      </div>
    </section>
  )
}
