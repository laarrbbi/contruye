import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ChevronDown, Lock, PlayCircle } from 'lucide-react'
import type { CourseDetail, PublicLesson } from '@/api/courses'
import { useQuote, type QuotePayload } from '@/api/orders'
import { IsolatedValue } from '@/components/IsolatedValue'
import { LessonPreviewDialog } from '@/components/course/LessonPreviewDialog'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import { isDemo } from '@/lib/demo'
import { paymentMethodList } from '@/lib/payments'
import { phaseModules } from '@/lib/modules'
import { cn, formatPrice } from '@/lib/utils'
import type { HeroCta } from '@/components/landing/kit/cta'
import { BoutonOr, Mots } from '@/components/landing/kit/pieces'
import { useVu, vars } from '@/components/landing/kit/tools'
import { BANDE, H2, TEXTE } from '@/components/landing/kit/type'


/** What the full course includes, each line with what it is for: counted from the course where it can be. */
const INCLUDED: { key: string; values?: (course: CourseDetail) => Record<string, string | number> }[] = [
  { key: 'i1', values: (course) => ({ modules: phaseModules(course.modules).length, lessons: phaseModules(course.modules).reduce((sum, module) => sum + module.lessons.length, 0) }) },
  { key: 'i2', values: (course) => ({ lessons: course.modules.find((module) => module.kind === 'financials')?.lessons.length ?? 0 }) },
  { key: 'i3' },
  { key: 'i4' },
  { key: 'i5' },
  { key: 'i6' },
]
const OBJECTIONS = ['o1', 'o2', 'o3', 'o4'] as const
/** Where a buyer is stuck, and the module that answers it. */
const CASES = ['c1', 'c2', 'c3', 'c4'] as const

interface ProgrammeProps {
  course: CourseDetail
  /** The button for the whole course: sign-up, checkout or the course itself. */
  cta: HeroCta
  /** Where a visitor goes to pay for `target`: through sign-up when not signed in. */
  purchaseLink(target: string): { to: string; state?: { from: string } }
  checkoutPath: string
}

/**
 * The syllabus and the price in one place: tick the modules you need and the
 * total follows, or take the whole course.
 *
 * Titles and lessons are the course's own, shown as they are. Prices and
 * discount steps come from the course; the figure shown is computed at once
 * from them, then confirmed by the server's quote, which is the price the
 * checkout will charge and has the last word (it also knows about a running
 * promotion and says when the whole course has become the cheaper buy).
 * Nothing is ticked in advance.
 */
export function Programme({ course, cta, purchaseLink, checkoutPath }: ProgrammeProps) {
  const { t, i18n } = useTranslation()
  const [selected, setSelected] = useState<number[]>([])
  const [preview, setPreview] = useState<PublicLesson | null>(null)
  // The module whose lessons are unfolded: one line per module until then.
  const [unfolded, setUnfolded] = useState<number | null>(null)
  const price = (amount: number) => formatPrice(amount, 'EUR', i18n.language)
  const head = useVu<HTMLDivElement>()
  // On a phone the summary is far below the list: while modules are ticked
  // and the section is on screen, the total stays at the bottom of the screen.
  const section = useRef<HTMLElement>(null)
  const [onScreen, setOnScreen] = useState(false)
  useEffect(() => {
    const node = section.current
    if (!node) return
    const observer = new IntersectionObserver(([entry]) => setOnScreen(entry.isIntersecting), { rootMargin: '-20% 0px -20% 0px' })
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  const canBuy = !course.full_access
  const tiers = useMemo(() => [...course.module_discount_tiers].sort((a, b) => a.min_modules - b.min_modules), [course.module_discount_tiers])
  const count = selected.length
  const subtotal = course.modules.reduce((sum, module) => (selected.includes(module.id) ? sum + (module.price_eur ?? 0) : sum), 0)
  const percent = tiers.reduce((best, tier) => (count >= tier.min_modules ? Math.max(best, tier.percent_off) : best), 0)
  const nextTier = tiers.find((tier) => tier.min_modules > count) ?? null

  const payload = useMemo<QuotePayload | null>(
    () => (count > 0 ? { course_id: course.id, scope: 'modules', module_ids: [...selected].sort((a, b) => a - b) } : null),
    [count, course.id, selected],
  )
  const asked = useDebouncedValue(payload, 250)
  const quoted = useQuote(asked)
  // The last quote received may be for the previous selection: use it only
  // when it answers exactly what is ticked now. (Its lines cannot tell: when
  // the whole course is the better buy, the server quotes the whole course.)
  // A quote discounted by a demo promotion is not shown either: the figures
  // then come from the course's own prices and steps.
  const confirmed =
    quoted.data && !quoted.isPlaceholderData && asked === payload && !isDemo(quoted.data.campaign?.label) ? quoted.data : null
  // The whole course as it is priced today, a running promotion included.
  const fullPrice = course.campaign ? Math.round((course.price_eur * (100 - course.campaign.percent_off)) / 100) : course.price_eur
  const upgraded = confirmed?.upgraded_to_full ?? false
  const total = confirmed ? confirmed.total : Math.round((subtotal * (100 - percent)) / 100)
  const discount = confirmed ? confirmed.tier_discount : subtotal - Math.round((subtotal * (100 - percent)) / 100)

  const toggle = (id: number) => setSelected((current) => (current.includes(id) ? current.filter((other) => other !== id) : [...current, id]))
  const modulesLink = purchaseLink(`${checkoutPath}?scope=modules&modules=${[...selected].sort((a, b) => a - b).join(',')}`)

  const previews = course.modules.reduce((sum, module) => sum + module.lessons.filter((lesson) => lesson.is_free_preview).length, 0)

  return (
    <section ref={section} id="programme" data-tone="paper" aria-labelledby="s7-titre" className="relative scroll-mt-16 bg-creme-100 text-plume">
      <div className={cn(BANDE, '!pt-6 lg:!pt-10')}>
        <div ref={head}>
          <h2 id="s7-titre" className={cn(H2, 'max-w-[18ch]')}>
            <Mots text={t('landing.modular.title')} />
          </h2>
          <p className={cn(TEXTE, 'entree mt-6 max-w-[36rem] text-plume/85')} style={vars({ '--d': '300ms' })}>
            {t('landing.modular.p1')}
          </p>
          {/* Where one stands, and the module that answers it: four lines, no box. */}
          <ul className="entree mt-6 grid max-w-[52rem] gap-x-10 gap-y-2 lg:grid-cols-2" style={vars({ '--d': '420ms' })}>
            {CASES.map((key) => (
              <li key={key} className="flex items-start gap-2.5 text-[0.9375rem] leading-relaxed text-plume/75 rtl:leading-loose">
                <span aria-hidden className="mt-[0.6rem] h-px w-3 shrink-0 bg-plume/40" />
                {t(`landing.modular.${key}`)}
              </li>
            ))}
          </ul>
          <p className="entree mt-6 max-w-[36rem] text-[0.9375rem] leading-relaxed text-plume/70" style={vars({ '--d': '520ms' })}>
            {previews > 0 ? t('landing.curriculumIntro.leadPreview', { count: previews }) : t('landing.curriculumIntro.lead')}
          </p>
        </div>

        <div className="mt-14 grid gap-12 lg:mt-20 lg:grid-cols-12 lg:gap-16">
          <div className="min-w-0 lg:col-span-7">
            {canBuy && tiers.length > 0 && (
              // The discount steps as a graduated rule: it fills as modules are ticked.
              <div className="sticky top-16 z-10 -mx-4 border-b border-plume/15 bg-creme-100/95 px-4 pb-4 pt-3 backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0 lg:backdrop-blur-none">
                <p className="flex flex-wrap items-baseline justify-between gap-x-4 text-base">
                  <span className="font-semibold">{t('checkout.modules.tierTitle')}</span>
                  <span aria-live="polite" className="text-plume/85">
                    {nextTier
                      ? t('checkout.modules.tierHint', { count: nextTier.min_modules - count, percent: nextTier.percent_off })
                      : t('checkout.modules.selectedCount', { count })}
                  </span>
                </p>
                {/* One segment per step: it fills as the selection approaches the step. */}
                <ol className="mt-3 grid gap-2" style={{ gridTemplateColumns: `repeat(${tiers.length}, minmax(0, 1fr))` }}>
                  {tiers.map((tier, index) => {
                    const from = index === 0 ? 0 : tiers[index - 1].min_modules
                    const filled = Math.min(1, Math.max(0, (count - from) / (tier.min_modules - from)))
                    return (
                      <li key={tier.min_modules} className={cn('text-[0.8125rem] leading-tight', count >= tier.min_modules ? 'text-plume' : 'text-plume/85')}>
                        <span className="relative block h-1 overflow-hidden rounded-full bg-plume/10">
                          <span
                            aria-hidden
                            className="absolute inset-0 origin-left bg-or transition-transform duration-300 ease-out-strong rtl:origin-right"
                            style={{ transform: `scaleX(${filled})` }}
                          />
                        </span>
                        <span dir="ltr" className="mt-2 block font-medium rtl:text-end">
                          {t('checkout.modules.tierPercent', { percent: tier.percent_off })}
                        </span>
                        <span className="block">{t('checkout.modules.tierMin', { min: tier.min_modules })}</span>
                      </li>
                    )
                  })}
                </ol>
              </div>
            )}

            {course.modules.length === 0 ? (
              <p className="py-8 text-plume/85">{t('course.emptyProgram')}</p>
            ) : (
              <ol>
                {course.modules.map((module) => {
                  const sellable = canBuy && module.price_eur != null && !module.is_owned
                  const ticked = selected.includes(module.id)
                  const shown = unfolded === module.id
                  return (
                    <li key={module.id} className={cn('border-b border-plume/15 transition-colors duration-200', ticked && 'bg-or/[0.1]')}>
                      {/* One line: the box, the module, its price, and the fold that holds its lessons. */}
                      <div className="flex min-h-14 items-center gap-3 py-2.5">
                        <label className={cn('flex min-w-0 flex-1 items-center gap-4', sellable && 'cursor-pointer')}>
                          {canBuy && (
                            <input
                              type="checkbox"
                              checked={ticked || !!module.is_owned}
                              disabled={!sellable}
                              onChange={() => toggle(module.id)}
                              className="h-5 w-5 shrink-0 accent-[#1F3A5F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-plume focus-visible:ring-offset-2"
                            />
                          )}
                          <span className="min-w-0 flex-1 text-base font-medium leading-snug lg:truncate">
                            {/* Financials is sold like a module but is not a phase: its own name, no number. */}
                            {module.kind === 'financials' ? (
                              <bdi>{module.title}</bdi>
                            ) : (
                              <IsolatedValue i18nKey="course.moduleHeading" name="title" value={module.title} values={{ number: module.order }} />
                            )}
                          </span>
                          {canBuy && (
                            <span className="shrink-0 text-[0.9375rem] tabular-nums text-plume/80">
                              {module.is_owned
                                ? t('checkout.modules.owned')
                                : module.price_eur != null
                                  ? price(module.price_eur)
                                  : t('checkout.modules.fullOnly')}
                            </span>
                          )}
                        </label>
                        <button
                          type="button"
                          aria-expanded={shown}
                          aria-controls={`lecons-${module.id}`}
                          aria-label={t('course.lessonsCount', { count: module.lessons.length })}
                          onClick={() => setUnfolded(shown ? null : module.id)}
                          className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-plume/60 transition-colors duration-200 hover:bg-plume/[0.06] hover:text-plume focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-plume"
                        >
                          <ChevronDown aria-hidden strokeWidth={1.75} className={cn('h-4 w-4 transition-transform duration-300 ease-out-strong', shown && 'rotate-180')} />
                        </button>
                      </div>
                      <div
                        id={`lecons-${module.id}`}
                        {...(shown ? {} : { inert: '' })}
                        className={cn('grid transition-[grid-template-rows,opacity] duration-300 ease-out-strong', shown ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0')}
                      >
                        <div className="overflow-hidden">
                          <p className="ps-9 text-[0.875rem] text-plume/60">{t('course.lessonsCount', { count: module.lessons.length })}</p>
                          <ul className="space-y-0.5 pb-4 ps-9 pt-1.5">
                            {module.lessons.map((lesson) => (
                              <li key={lesson.id} className="flex items-center gap-3 py-1 text-[0.9375rem]">
                                {lesson.is_free_preview ? (
                                  <PlayCircle aria-hidden strokeWidth={1.5} className="h-4 w-4 shrink-0 text-or-fonce" />
                                ) : (
                                  <Lock aria-hidden strokeWidth={1.5} className="h-4 w-4 shrink-0 text-plume/45" />
                                )}
                                <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                                {lesson.is_free_preview && (
                                  <button
                                    type="button"
                                    onClick={() => setPreview(lesson)}
                                    className="min-h-9 rounded px-2 text-[0.9375rem] font-semibold text-or-fonce underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-plume"
                                  >
                                    {t('course.watchPreview')}
                                  </button>
                                )}
                              </li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    </li>
                  )
                })}
              </ol>
            )}
          </div>

          <aside id="tarifs" className="scroll-mt-24 lg:col-span-5">
            <div className="rounded-[1.5rem] bg-[#FCFAF6] p-6 shadow-[0_1px_2px_rgb(31_58_95/0.08),0_14px_30px_-10px_rgb(31_58_95/0.18),0_50px_90px_-36px_rgb(31_58_95/0.34)] ring-1 ring-plume/[0.06] lg:sticky lg:top-24 lg:p-8">
              {!canBuy ? (
                <BoutonOr cta={cta} className="w-full">
                  {t(cta.label)}
                </BoutonOr>
              ) : (
                <>
                  {/* The course as a building: one storey per module, lit when it is ticked. */}
                  <div aria-hidden className="mb-7 flex items-end gap-5 border-b border-plume/10 pb-6">
                    <svg viewBox={`0 0 132 ${course.modules.length * 11 + 12}`} className="h-44 w-auto shrink-0">
                      {course.modules.map((module, index) => {
                        const y = (course.modules.length - 1 - index) * 11 + 8
                        const lit = selected.includes(module.id) || !!module.is_owned
                        return (
                          <g key={module.id} onClick={() => module.price_eur != null && !module.is_owned && toggle(module.id)} className="cursor-pointer">
                            <rect x="6" y={y} width="120" height="9" rx="1" className={cn('transition-[fill] duration-300 ease-out-strong', lit ? 'fill-[#1F3A5F]' : 'fill-[#E9E3D8]')} />
                            {[14, 32, 50, 68, 86, 104].map((x) => (
                              <rect key={x} x={x} y={y + 2.5} width="12" height="4" rx="0.5" className={cn('transition-[fill] duration-300 ease-out-strong', lit ? 'fill-[#F1B860]' : 'fill-[#D3CCBE]')} />
                            ))}
                          </g>
                        )
                      })}
                      <rect x="0" y={course.modules.length * 11 + 8} width="132" height="2" className="fill-[#5B6470]" />
                      <rect x="2" y="4" width="128" height="3" rx="1" className="fill-[#5B6470]" />
                    </svg>
                    <p className="pb-1">
                      <span className="titre block text-[3.25rem] leading-none tabular-nums" dir="ltr">
                        {count}
                        <span className="text-plume/35">/{course.modules.length}</span>
                      </span>
                      <span className="etiquette mt-2 block text-plume/60">{t('landing.s1.modules')}</span>
                    </p>
                  </div>
                  <h3 className="etiquette text-plume/60">{t('checkout.quote.title')}</h3>
                  {count === 0 ? (
                    <p className="mt-4 leading-relaxed text-plume/85">{t('checkout.modules.empty')}</p>
                  ) : (
                    <dl aria-live="polite" className="mt-4 space-y-2 text-[1.0625rem]">
                      <div className="flex justify-between gap-4">
                        <dt>{t('checkout.modules.selectedCount', { count })}</dt>
                        <dd className={cn('tabular-nums', upgraded && 'text-plume/85 line-through')}>{price(subtotal)}</dd>
                      </div>
                      {/* Once the whole course has been applied, its price is the
                          only line that still means something. */}
                      {discount > 0 && !upgraded && (
                        <div className="flex justify-between gap-4 text-or-fonce">
                          <dt>{t('checkout.quote.tierDiscount', { percent: confirmed ? confirmed.tier_percent : percent })}</dt>
                          <dd className="tabular-nums" dir="ltr">
                            −{price(discount)}
                          </dd>
                        </div>
                      )}
                      {confirmed && confirmed.campaign && confirmed.campaign_discount > 0 && !upgraded && (
                        <div className="flex justify-between gap-4 text-or-fonce">
                          <dt>
                            <bdi>{confirmed.campaign.label}</bdi>{' '}
                            <span dir="ltr">(−{confirmed.campaign_percent} %)</span>
                          </dt>
                          <dd className="tabular-nums" dir="ltr">
                            −{price(confirmed.campaign_discount)}
                          </dd>
                        </div>
                      )}
                      <div className="flex items-baseline justify-between gap-4 border-t border-plume/15 pt-3">
                        <dt className="font-semibold">{t('checkout.quote.total')}</dt>
                        <dd className="titre text-[2.25rem] leading-none tabular-nums">{price(total)}</dd>
                      </div>
                    </dl>
                  )}

                  {/* The honest nudge: said only when the server's quote says it. */}
                  {upgraded && <p className="mt-4 border-s-2 border-or ps-4 text-base leading-relaxed">{t('checkout.quote.upgradedNote')}</p>}
                  {confirmed?.upgrade_hint && !upgraded && (
                    <div className="mt-4 border-s-2 border-or ps-4 text-base leading-relaxed">
                      <p className="font-semibold">{t('checkout.quote.upgradeTitle', { delta: price(confirmed.upgrade_hint.price_delta) })}</p>
                      <p className="text-plume/85">{t('checkout.quote.upgradeSubtitle', { count: confirmed.upgrade_hint.modules_missing })}</p>
                    </div>
                  )}

                  {count > 0 && !upgraded && (
                    <Link
                      to={modulesLink.to}
                      state={modulesLink.state}
                      className="mt-5 flex min-h-12 items-center justify-center rounded-[0.875rem] border border-plume px-4 text-center text-[1.0625rem] font-semibold transition-[background-color,color,transform] duration-150 ease-out-strong hover:bg-plume hover:text-creme-100 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-plume focus-visible:ring-offset-2"
                    >
                      {t('landing.config.continue', { count })}
                    </Link>
                  )}

                  <div className="mt-6 border-t border-plume/15 pt-5">
                    <p className="flex items-baseline justify-between gap-4">
                      <span className="font-semibold">{t('landing.pricing.fullTitle')}</span>
                      <span className="titre flex items-baseline gap-2 text-[1.75rem] leading-none tabular-nums">
                        {course.campaign && <s className="text-base font-normal text-plume/85">{price(course.price_eur)}</s>}
                        {price(fullPrice)}
                      </span>
                    </p>
                    <p className="mt-1.5 text-[0.9375rem] text-plume/80">{t('landing.pricing.fullShort', { count: course.modules.length })}</p>
                    <div data-achat>
                      <BoutonOr cta={cta} className="mt-5 w-full">
                        {t(cta.label)}
                      </BoutonOr>
                    </div>
                  </div>

                  {/* The ways to pay, in one short line. */}
                  <p className="mt-5 text-center text-[0.8125rem] text-plume/65">{t('payments.sentence', { methods: paymentMethodList(t, { set: 'short' }) })}</p>
                </>
              )}
            </div>
          </aside>
        </div>

        {/* What the price includes, the doubts that hold a purchase back, and
            the one sentence a buyer must read before paying: training, not a
            promise of profit. */}
        <div className="mt-24 grid gap-12 border-t border-plume/15 pt-12 lg:mt-32 lg:grid-cols-12 lg:gap-x-14 lg:pt-16">
          <div className="lg:col-span-6">
            <h3 className={cn(H2, '!text-[clamp(1.75rem,1.2rem+1.8vw,2.75rem)]')}>{t('landing.included.title')}</h3>
            <dl className="mt-8 divide-y divide-plume/10 border-y border-plume/10">
              {INCLUDED.map(({ key, values }) => (
                <div key={key} className="grid gap-1 py-4 sm:grid-cols-[1.2fr_1fr] sm:gap-6">
                  <dt className="font-semibold leading-snug">{t(`landing.included.${key}`, values?.(course))}</dt>
                  <dd className="text-[0.9375rem] leading-relaxed text-plume/70">{t(`landing.included.${key}Use`)}</dd>
                </div>
              ))}
            </dl>
          </div>
          <div className="lg:col-span-6">
            <h3 className={cn(H2, '!text-[clamp(1.75rem,1.2rem+1.8vw,2.75rem)]')}>{t('landing.objections.title')}</h3>
            <ul className="mt-8 grid gap-4 sm:grid-cols-2">
              {OBJECTIONS.map((key) => (
                <li key={key} className="rounded-2xl bg-[#FCFAF6] p-5 ring-1 ring-plume/[0.07] shadow-[0_1px_2px_rgb(31_58_95/0.06),0_12px_28px_-14px_rgb(31_58_95/0.22)]">
                  <p className="font-semibold leading-snug">{t(`landing.objections.${key}Q`)}</p>
                  <p className="mt-2 text-[0.9375rem] leading-relaxed text-plume/70 rtl:leading-loose">{t(`landing.objections.${key}A`)}</p>
                </li>
              ))}
            </ul>
          </div>
          <p className="text-sm italic leading-relaxed text-plume/65 lg:col-span-12">{t('landing.legalNotice')}</p>
        </div>
      </div>

      {canBuy && count > 0 && onScreen && (
        <div data-achat className="fixed inset-x-0 bottom-0 z-40 border-t border-plume/10 bg-creme-100/95 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-18px_40px_-16px_rgb(31_58_95/0.3)] backdrop-blur-lg lg:hidden">
          <div className="container flex items-center justify-between gap-4">
            <p aria-live="polite" className="min-w-0">
              <span className="block text-[0.8125rem] text-plume/85">{t('checkout.modules.selectedCount', { count })}</span>
              <span className="titre block text-[1.625rem] leading-none tabular-nums">{price(upgraded ? fullPrice : total)}</span>
            </p>
            <Link
              to={upgraded ? cta.to : modulesLink.to}
              state={upgraded ? cta.state : modulesLink.state}
              className="btn-or h-12 shrink-0 !px-5 text-[1.0625rem]"
            >
              {t('landing.config.continue', { count })}
            </Link>
          </div>
        </div>
      )}

      {/* The free preview: the lesson's text, readable without an account. */}
      <LessonPreviewDialog lesson={preview} onClose={() => setPreview(null)} />
    </section>
  )
}
