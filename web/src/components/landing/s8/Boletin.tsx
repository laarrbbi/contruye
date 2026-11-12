import { useTranslation } from 'react-i18next'
import { H3, TEXTE } from '@/components/landing/kit/type'
import { NewsletterSignup } from '@/components/newsletter/NewsletterSignup'
import { cn } from '@/lib/utils'

/**
 * The newsletter, after the questions: the night of that section carried on,
 * one field and what it is for.
 */
export function Boletin() {
  const { t } = useTranslation()

  return (
    <section id="boletin" data-tone="night" aria-labelledby="boletin-titre" className="relative scroll-mt-16 bg-[#14243A] text-creme-100">
      <div className="container grid gap-10 border-t border-creme-100/10 py-20 lg:grid-cols-12 lg:gap-16 lg:py-28">
        <div className="lg:col-span-5">
          <p className="etiquette flex items-center gap-3 text-creme-100/70">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {t('newsletter.eyebrow')}
          </p>
          <h2 id="boletin-titre" className={cn(H3, 'mt-5')}>
            {t('newsletter.title')}
          </h2>
          <p className={cn(TEXTE, 'mt-5 text-creme-100/80')}>{t('newsletter.lead')}</p>
        </div>
        <div className="flex items-end lg:col-span-7">
          <NewsletterSignup source="home" tone="night" className="w-full" />
        </div>
      </div>
    </section>
  )
}
