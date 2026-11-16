import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  AlertTriangle,
  ArrowRight,
  ArrowUpRight,
  BookOpen,
  CalendarClock,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  FileText,
  Handshake,
  Landmark,
  MapPin,
  Phone,
  PlayCircle,
  Users,
} from 'lucide-react'
import { useUser } from '@/api/auth'
import { useLessonCompanies } from '@/api/directory'
import type { WorldStep } from '@/api/world'
import { MarkdownContent } from '@/components/MarkdownContent'
import { cn, intlLocale } from '@/lib/utils'
import { plainText } from './useNarration'

function percent(rate: number, locale: string) {
  return new Intl.NumberFormat(intlLocale(locale), {
    style: 'percent',
    maximumFractionDigits: 2,
  }).format(rate)
}

/** «https://www.sedecatastro.gob.es/» → «sedecatastro.gob.es». */
function host(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

/** A section of the step: a bold title with its icon, then what it holds. */
function Section({ icon: Icon, title, children, className }: { icon: typeof FileText; title: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={cn('border-t border-foreground/[0.08] pt-4 first:border-t-0 first:pt-0', className)}>
      <h3 className="mb-2.5 flex items-center gap-2 text-[0.95rem] font-bold leading-tight text-plume">
        <span aria-hidden className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#006FFE]/10 text-[#006FFE]">
          <Icon className="h-4 w-4" />
        </span>
        {title}
      </h3>
      {children}
    </section>
  )
}

function Chips({ icon: Icon, items, tone, lang }: { icon: typeof FileText; items: string[]; tone: 'paper' | 'people'; lang: string }) {
  return (
    <ul className="flex flex-wrap gap-1.5">
      {items.map((item) => (
        <li
          key={item}
          lang={lang}
          className={cn(
            'w3d-pop inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium',
            tone === 'paper' ? 'bg-[#006FFE]/[0.08] text-[#0055C4]' : 'bg-foreground/[0.06] text-foreground/80',
          )}
        >
          <Icon className="h-3.5 w-3.5 shrink-0" aria-hidden />
          <span className="bidi-auto">{item}</span>
        </li>
      ))}
    </ul>
  )
}

/** A line of a «Dónde ir» row: the column's name, then the course's cell. */
function WhereLine({ label, html, strong, lang }: { label: string; html: string | null; strong?: boolean; lang: string }) {
  if (!html) return null
  return (
    <div className="grid grid-cols-[6.25rem_1fr] gap-2">
      <dt className="pt-px text-xs font-semibold text-muted-foreground">{label}</dt>
      <dd lang={lang} className={cn('md-inline text-sm leading-snug', strong && 'font-semibold text-foreground')} dangerouslySetInnerHTML={{ __html: html }} />
    </div>
  )
}

export interface StepPanelProps {
  slug: string
  moduleId: number
  step: WorldStep
  index: number
  total: number
  /** The language the course's words are in (the 3D script is Spanish). */
  locale: string
  placeName: string
  province: string | null
  checked: Record<number, boolean>
  checklistDone: number
  checklistTotal: number
  onToggle(itemId: number, value: boolean): void
  /** Opened where they had left it: say so, and offer the beginning. */
  resumed: boolean
  onRestart(): void
  next: WorldStep | null
  onNext(): void
  onPrev(): void
  onVideo(): void
  /** The 3D page's own address, so the lesson can offer the way back. */
  from3d: string
  /** A phone: the dock below carries ‹ ▶ ›, so the panel's own arrows go. */
  compact?: boolean
}

/**
 * One step, as the 3D world tells it. On top, where we are: the lesson, the
 * step, its title, the place. Then the course's words, section by section,
 * each under a clear title: what the lesson says; at an institution, what you
 * ask for and what they give you (the module's «Dónde ir»), the office of the
 * student's province and the website to do it online; the papers; the taxes
 * at the rates in force; who takes part; the lesson's «Errores típicos»; the
 * checklist; the partners of the lesson. At the foot, the video, the lesson
 * and the next step, always in view.
 */
export function StepPanel(props: StepPanelProps) {
  const { slug, moduleId, step, index, total, locale, placeName, province, checked, checklistDone, checklistTotal, onToggle, resumed, onRestart, next, onNext, onPrev, onVideo, from3d, compact } =
    props
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const partners = useLessonCompanies(slug, step.lesson?.id ?? null, {
    province: user?.province_id ?? null,
  })
  const companies = partners.data?.data.slice(0, 3) ?? []

  // A figure the quote already says is not repeated as a chip.
  const said = `${plainText(step.quote_html)} ${(step.quote_rows ?? []).map((r) => plainText(r.value_html)).join(' ')}`
  const figures = step.figures.filter((figure) => !said.includes(figure))
  const hasWhere = step.where.length > 0 || step.offices === null || step.offices.length > 0 || step.links.length > 0
  const lessonUrl = step.lesson ? `/learn/${slug}/lessons/${step.lesson.id}` : null

  return (
    <>
      {/* ── Where we are ── */}
      <header className={cn('shrink-0 border-b border-foreground/[0.08] px-4 sm:px-5', compact ? 'pb-3 pt-3' : 'pb-3.5 pt-4')}>
        {resumed && (
          <p className="mb-2.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-lg bg-or/15 px-3 py-1.5 text-xs font-semibold text-or-fonce">
            {t('world3d.resumed')}
            <button type="button" onClick={onRestart} className="min-h-7 underline underline-offset-2 hover:no-underline">
              {t('world3d.restart')}
            </button>
          </p>
        )}
        {step.lesson && <p className={cn('bidi-auto text-[0.8rem] font-semibold leading-snug text-[#006FFE]', compact ? 'line-clamp-1' : 'line-clamp-2')}>{step.lesson.title}</p>}
        <div className="mt-2 flex items-center gap-2.5">
          <span className="shrink-0 text-xs font-semibold tabular-nums text-muted-foreground">{t('world3d.stepOf', { index: index + 1, total })}</span>
          <span aria-hidden className="h-1 flex-1 overflow-hidden rounded-full bg-foreground/10">
            <span className="block h-full rounded-full bg-[#006FFE] transition-[width] duration-500" style={{ width: `${((index + 1) / total) * 100}%` }} />
          </span>
        </div>
        <h2 id="w3d-step-title" tabIndex={-1} className={cn('bidi-auto mt-2 font-bold leading-snug text-plume outline-none', compact ? 'text-lg' : 'text-xl')}>
          {step.title}
        </h2>
        <p className="mt-2.5 flex flex-wrap items-center gap-1.5 text-sm">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-[#006FFE]/10 px-3 py-1 font-semibold text-[#006FFE]">
            <MapPin className="h-3.5 w-3.5" aria-hidden />
            {t('world3d.where', { place: placeName })}
          </span>
          {step.plazo && step.where.length === 0 && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-or/15 px-3 py-1 font-semibold text-or-fonce">
              <CalendarClock className="h-3.5 w-3.5" aria-hidden />
              {t('world3d.deadline', { plazo: step.plazo })}
            </span>
          )}
        </p>
      </header>

      {/* ── The course's words, section by section ── */}
      <div key={step.key} className="w3d-enter min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
        <Section icon={BookOpen} title={t('world3d.lessonSays')}>
          {step.quote_rows ? (
            <dl className="space-y-2.5 border-s-[3px] border-or ps-3.5">
              {step.quote_rows.map((row) => (
                <div key={row.label}>
                  <dt lang={locale} className="text-xs font-semibold text-muted-foreground">
                    {row.label}
                  </dt>
                  <dd lang={locale} className="md-inline text-[0.95rem] leading-relaxed" dangerouslySetInnerHTML={{ __html: row.value_html ?? '' }} />
                </div>
              ))}
            </dl>
          ) : (
            step.quote_html && (
              <blockquote className="border-s-[3px] border-or ps-3.5">
                <MarkdownContent html={step.quote_html} lang={locale} className="text-[0.95rem] leading-relaxed [&_p]:my-0" />
              </blockquote>
            )
          )}
          {figures.length > 0 && (
            <ul className="mt-3 flex flex-wrap gap-1.5">
              {figures.map((figure) => (
                <li key={figure} className="rounded-md border px-2 py-1 text-sm font-semibold tabular-nums text-foreground/80">
                  <bdi>{figure}</bdi>
                </li>
              ))}
            </ul>
          )}
        </Section>

        {hasWhere && (
          <Section icon={MapPin} title={t('world3d.whereToGo')}>
            <div className="space-y-2.5">
              {step.where.map((row, i) => (
                <div key={i} className="w3d-pop rounded-xl border border-foreground/10 bg-white p-3.5">
                  <p
                    lang={locale}
                    className="md-inline text-[0.95rem] font-bold leading-snug"
                    dangerouslySetInnerHTML={{
                      __html: row.institution_html ?? '',
                    }}
                  />
                  <dl className="mt-2.5 grid gap-2">
                    <WhereLine label={t('world3d.ask')} html={row.ask_html} lang={locale} />
                    <WhereLine label={t('world3d.get')} html={row.get_html} lang={locale} />
                    <WhereLine label={t('world3d.when')} html={row.plazo_html} lang={locale} strong />
                  </dl>
                </div>
              ))}

              {step.offices === null ? (
                <p className="rounded-xl bg-foreground/[0.04] px-3.5 py-3 text-sm text-foreground/75">
                  {t('world3d.officesNoProvince')}{' '}
                  <Link to="/account/profile" className="font-semibold text-[#006FFE] hover:underline">
                    {t('world3d.chooseProvince')}
                  </Link>
                </p>
              ) : (
                step.offices.length > 0 && (
                  <div className="rounded-xl bg-[#006FFE]/[0.05] px-3.5 py-3">
                    <p className="text-xs font-bold uppercase tracking-wide text-[#006FFE]">{t('world3d.officesIn', { province })}</p>
                    <ul className="mt-2 space-y-2">
                      {step.offices.map((office) => (
                        <li key={office.name} lang={locale}>
                          <span className="block text-sm font-semibold leading-snug">{office.name}</span>
                          {office.purpose && <span className="block text-xs leading-snug text-muted-foreground">{office.purpose}</span>}
                        </li>
                      ))}
                    </ul>
                  </div>
                )
              )}

              {step.links.length > 0 && (
                <div>
                  <p className="mb-1.5 text-xs font-bold uppercase tracking-wide text-muted-foreground">{t('world3d.online')}</p>
                  <ul className="space-y-1.5">
                    {step.links.map((link) => (
                      <li key={link.url}>
                        <a href={link.url} target="_blank" rel="noopener noreferrer" className="group flex min-h-11 items-center gap-3 rounded-xl border bg-white px-3 py-2 hover:border-[#006FFE]/40">
                          <span className="min-w-0 flex-1">
                            <span lang={locale} className="block text-sm font-semibold leading-snug">
                              {link.name}
                            </span>
                            <span dir="ltr" className="block truncate text-xs text-muted-foreground">
                              {host(link.url)}
                            </span>
                          </span>
                          <ArrowUpRight aria-hidden className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 rtl:-scale-x-100" />
                          <span className="sr-only">{t('player.opensInNewTab')}</span>
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </Section>
        )}

        {step.documents.length > 0 && (
          <Section icon={FileText} title={t('world3d.papers')}>
            <Chips icon={FileText} items={step.documents} tone="paper" lang={locale} />
          </Section>
        )}

        {step.taxes.length > 0 && (
          <Section icon={Landmark} title={t('world3d.taxes')}>
            <ul className="space-y-2">
              {step.taxes.map((tax) => (
                <li key={`${tax.code}-${tax.name}`} className="w3d-pop rounded-xl bg-or/10 px-3.5 py-2.5">
                  <div className="flex items-baseline justify-between gap-3">
                    <span lang={locale} className="bidi-auto text-[0.95rem] font-semibold">
                      {tax.name}
                    </span>
                    {tax.rate !== null && <span className="shrink-0 text-lg font-bold tabular-nums text-plume">{percent(tax.rate, i18n.language)}</span>}
                  </div>
                  {tax.region && <p className="mt-0.5 text-xs text-foreground/70">{t('world3d.taxRegion', { region: tax.region })}</p>}
                  {tax.rate === null && (tax.code === 'itp' || tax.code === 'ajd') && (
                    <p className="mt-0.5 text-xs text-foreground/70">
                      {t('world3d.taxNoProvince')}{' '}
                      <Link to="/account/profile" className="font-semibold text-[#006FFE] hover:underline">
                        {t('world3d.chooseProvince')}
                      </Link>
                    </p>
                  )}
                  {tax.note && (
                    <p lang={locale} className="mt-0.5 text-sm text-foreground/80 first-letter:uppercase">
                      {tax.note}
                    </p>
                  )}
                </li>
              ))}
            </ul>
            {step.taxes.some((tax) => tax.code === 'itp' || tax.code === 'ajd') && (
              <Link to={`/learn/${slug}/docs/anexo-b`} className="mt-2 inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-[#006FFE] hover:underline">
                {t('world3d.taxesByRegion')}
                <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
              </Link>
            )}
          </Section>
        )}

        {step.people.length > 0 && (
          <Section icon={Users} title={t('world3d.people')}>
            <Chips icon={Users} items={step.people} tone="people" lang={locale} />
          </Section>
        )}

        {step.warning_html && (
          <div className="flex gap-3 rounded-xl border border-or/40 bg-or/10 p-3.5">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-or-fonce" aria-hidden />
            <MarkdownContent html={step.warning_html} lang={locale} className="text-[0.92rem] leading-relaxed [&_p]:my-0" />
          </div>
        )}

        {step.checklist.length > 0 && (
          <Section
            icon={ClipboardCheck}
            title={t('world3d.checklistTitle', {
              done: checklistDone,
              total: checklistTotal,
            })}
          >
            <ul className="space-y-1">
              {step.checklist.map((item) => {
                const isChecked = checked[item.id] ?? item.checked
                return (
                  <li key={item.id}>
                    <label className="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg px-2 py-2 text-sm hover:bg-muted">
                      <input type="checkbox" className="mt-0.5 h-5 w-5 shrink-0 accent-[#006FFE]" checked={isChecked} onChange={(event) => onToggle(item.id, event.target.checked)} />
                      <span
                        lang={locale}
                        className={cn('md-inline leading-snug', isChecked && 'text-muted-foreground line-through')}
                        dangerouslySetInnerHTML={{
                          __html: item.text_html ?? '',
                        }}
                      />
                    </label>
                  </li>
                )
              })}
            </ul>
            <Link to={`/learn/${slug}/modules/${moduleId}/tareas`} className="mt-1 inline-flex min-h-9 items-center gap-1.5 px-2 text-sm font-semibold text-[#006FFE] hover:underline">
              {t('world3d.checklistAll')}
              <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
            </Link>
          </Section>
        )}

        {companies.length > 0 && step.lesson && (
          <section className="rounded-2xl bg-[#006FFE]/[0.06] p-4">
            <h3 className="flex items-center gap-2 text-base font-bold leading-tight text-plume">
              <Handshake className="h-5 w-5 shrink-0 text-[#006FFE]" aria-hidden />
              {province
                ? t('world3d.partnersIn', {
                    number: step.lesson.number,
                    province,
                  })
                : t('world3d.partnersOf', { number: step.lesson.number })}
            </h3>
            <p className="mt-1 text-sm text-foreground/70">{t('world3d.partnersLead')}</p>
            <ul className="mt-3 space-y-2">
              {companies.map((company, i) => (
                <li key={company.id} className="w3d-pop rounded-xl border bg-white p-3" style={{ animationDelay: `${i * 90}ms` }}>
                  <div className="flex items-start gap-3">
                    <span aria-hidden className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#006FFE]/10 text-xs font-bold text-[#006FFE]">
                      {company.name.slice(0, 2).toUpperCase()}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="line-clamp-2 block text-[0.95rem] font-bold leading-snug">{company.name}</span>
                      <span className="mt-0.5 block text-xs">
                        <span className="font-semibold text-[#006FFE]">{t(`directory.category.${company.category}`)}</span>
                        {company.city && <span className="text-muted-foreground"> · {company.city}</span>}
                      </span>
                    </span>
                  </div>
                  {company.phone && (
                    <a
                      href={`tel:${company.phone.replace(/[^\d+]/g, '')}`}
                      className="mt-2.5 flex h-10 items-center justify-center gap-2 rounded-lg border text-sm font-semibold hover:bg-muted"
                      aria-label={t('world3d.callCompany', { name: company.name })}
                    >
                      <Phone className="h-4 w-4 text-[#006FFE]" aria-hidden />
                      {t('world3d.call')}
                      <span dir="ltr" className="font-medium tabular-nums text-muted-foreground">
                        {company.phone}
                      </span>
                    </a>
                  )}
                </li>
              ))}
            </ul>
            <Link to={`/learn/${slug}/modules/${moduleId}/partners`} className="mt-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-[#006FFE] hover:underline">
              {t('world3d.allPartners')}
              <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
            </Link>
          </section>
        )}
      </div>

      {/* ── Always in view: the video, the lesson, the next step ── */}
      <footer className="shrink-0 space-y-2 border-t border-foreground/[0.08] px-4 py-3 sm:px-5">
        {step.lesson && lessonUrl && (
          <div className="flex gap-2">
            {step.lesson.has_video && (
              <button type="button" onClick={onVideo} className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold hover:bg-muted">
                <PlayCircle className="h-4 w-4 text-[#006FFE]" aria-hidden />
                {t('world3d.watchVideo')}
              </button>
            )}
            <Link to={lessonUrl} state={{ from3d }} className="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border bg-white px-3 text-sm font-semibold hover:bg-muted">
              <BookOpen className="h-4 w-4 text-[#006FFE]" aria-hidden />
              {t('world3d.openLesson')}
            </Link>
          </div>
        )}
        {!compact && (
          <div className="flex gap-2">
            <button
              type="button"
              onClick={onPrev}
              aria-label={t('world3d.previous')}
              title={t('world3d.previous')}
              className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border bg-white hover:bg-muted"
            >
              <ChevronLeft className="h-5 w-5 rtl:-scale-x-100" aria-hidden />
            </button>
            <button
              type="button"
              onClick={onNext}
              className="flex min-h-12 min-w-0 flex-1 items-center justify-between gap-3 rounded-xl bg-[#006FFE] px-4 py-1.5 text-start text-white transition-colors hover:bg-[#005FE0] active:scale-[0.99]"
            >
              <span className="min-w-0">
                <span className="block text-sm font-bold">{next ? t('world3d.nextStep') : t('world3d.finishModule')}</span>
                {next && <span className="block truncate text-xs text-white/80">{next.title}</span>}
              </span>
              <ChevronRight className="h-5 w-5 shrink-0 rtl:-scale-x-100" aria-hidden />
            </button>
          </div>
        )}
      </footer>
    </>
  )
}
