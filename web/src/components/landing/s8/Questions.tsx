import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { hasContactChannel, useBusinessProfile } from '@/api/business'
import { ContactOptions } from '@/components/ContactOptions'
import { Apparait, Mots } from '@/components/landing/kit/pieces'
import { useVu, vars } from '@/components/landing/kit/tools'
import { BANDE, H2, TEXTE } from '@/components/landing/kit/type'
import faqContent from '@/content/faq.json'
import { paymentMethodList } from '@/lib/payments'
import { cn } from '@/lib/utils'

// Shared with the server, which puts the same questions in the page's
// structured data (api SeoPageController): the keys do not change.
const FAQ_KEYS = faqContent.keys

/**
 * S8, the last questions before buying, and a human to ask the others.
 *
 * A soft night with two halos, blue and amber, and the questions in one large
 * floating card of frosted glass. Each question is a line with a round "+" at
 * its end; opening it turns the question amber and the "+" into an amber
 * "×", and the answer unfolds. One open at a time. On the left, a small pill
 * and the title. The halos stand still: the glass blurs what is behind it,
 * and a moving background would make it repaint at every frame.
 */
export function Questions() {
  const { t } = useTranslation()
  const { data: business } = useBusinessProfile()
  const reachable = hasContactChannel(business)
  const [open, setOpen] = useState<string | null>(null)
  const head = useVu<HTMLDivElement>()

  const items = FAQ_KEYS
    // "How do I reach support?" promises a phone and WhatsApp: asked only
    // once a channel is configured.
    .filter((key) => key !== 'support' || reachable)
    .map((key) => ({
      id: key,
      question: t(`course.faq.${key}.q`),
      answer:
        key === 'payment'
          ? [t('course.faq.payment.a', { methods: paymentMethodList(t) }), reachable && t('payments.cash')].filter(Boolean).join(' ')
          : t(`course.faq.${key}.a`),
    }))

  return (
    <section id="questions" data-tone="night" aria-labelledby="s8-titre" className="relative scroll-mt-16 overflow-hidden bg-[linear-gradient(180deg,#0B1A2E,#0F2038_55%,#14243A)] text-creme-100" style={vars({ '--focus': '#F6F2EA' })}>
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -start-[18%] top-[-12%] h-[60vmax] w-[60vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.28),transparent_62%)]" />
        <div className="absolute -end-[14%] bottom-[-22%] h-[62vmax] w-[62vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.22),transparent_60%)]" />
      </div>
      <div className={cn(BANDE, 'relative lg:grid lg:grid-cols-12 lg:gap-x-14')}>
        <div ref={head} className="lg:sticky lg:top-28 lg:col-span-4 lg:self-start">
          <p className="entree inline-flex items-center rounded-full bg-creme-100/[0.08] px-3.5 py-1.5 text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-or ring-1 ring-creme-100/15 rtl:tracking-normal">
            {t('landing.faq.eyebrow')}
          </p>
          <h2 id="s8-titre" className={cn(H2, 'mt-5')}>
            <Mots text={t('course.faq.title')} />
          </h2>
          <p className={cn(TEXTE, 'entree mt-5 text-creme-100/75')} style={vars({ '--d': '300ms' })}>
            {t('landing.faq.subtitle')}
          </p>
          {reachable && (
            <div className="entree mt-10 border-t border-creme-100/15 pt-6" style={vars({ '--d': '420ms' })}>
              <p className="mb-3 font-semibold">{t('landing.faq.stillHaveQuestions')}</p>
              <ContactOptions tone="onPrimary" prefill={t('contact.prefillGeneral')} />
            </div>
          )}
        </div>

        {/* The card of glass. */}
        <div className="mt-12 rounded-[1.75rem] bg-[linear-gradient(160deg,rgb(255_255_255/0.1),rgb(255_255_255/0.035))] p-2 shadow-[0_1px_0_rgb(255_255_255/0.12)_inset,0_30px_80px_-24px_rgb(0_0_0/0.65),0_60px_120px_-60px_rgb(59_111_216/0.35)] ring-1 ring-creme-100/[0.12] backdrop-blur-xl lg:col-span-8 lg:mt-0 lg:p-3">
          <ol>
            {items.map((item, index) => {
              const shown = open === item.id
              return (
                <Apparait key={item.id} as="li" className={cn(index > 0 && 'border-t border-creme-100/10')}>
                  <h3>
                    <button
                      type="button"
                      aria-expanded={shown}
                      aria-controls={`faq-${item.id}`}
                      onClick={() => setOpen(shown ? null : item.id)}
                      className="group flex w-full items-center justify-between gap-6 rounded-[1.25rem] px-4 py-5 text-start transition-colors duration-200 hover:bg-creme-100/[0.04] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-creme-100 lg:px-6"
                    >
                      <span className={cn('text-[1.125rem] font-semibold leading-snug tracking-[-0.01em] transition-colors duration-300 lg:text-[1.25rem] rtl:tracking-normal', shown ? 'text-or' : 'text-creme-100')}>
                        {item.question}
                      </span>
                      {/* A round "+" that turns into an amber "×". */}
                      <span
                        aria-hidden
                        className={cn(
                          'relative grid h-9 w-9 shrink-0 place-items-center rounded-full ring-1 transition-[background-color,transform,box-shadow] duration-300 ease-out-strong',
                          shown ? 'rotate-45 bg-or text-sombre-950 ring-or' : 'bg-creme-100/[0.06] text-creme-100 ring-creme-100/20 group-hover:bg-creme-100/[0.12]',
                        )}
                      >
                        <span className="absolute h-[1.5px] w-3.5 rounded-full bg-current" />
                        <span className="absolute h-3.5 w-[1.5px] rounded-full bg-current" />
                      </span>
                    </button>
                  </h3>
                  <div
                    id={`faq-${item.id}`}
                    role="region"
                    {...(shown ? {} : { inert: '' })}
                    className={cn('grid transition-[grid-template-rows,opacity] duration-500 ease-out-strong', shown ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0')}
                  >
                    <div className="overflow-hidden">
                      <p className="max-w-[40rem] px-4 pb-6 text-base leading-relaxed text-creme-100/80 lg:px-6 lg:text-[1.0625rem] rtl:leading-loose">{item.answer}</p>
                    </div>
                  </div>
                </Apparait>
              )
            })}
          </ol>
        </div>
      </div>
    </section>
  )
}
