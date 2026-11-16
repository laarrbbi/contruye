import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BookOpen, Building2, CalendarClock, FileText, Landmark, PlayCircle, Users } from 'lucide-react'
import { useUser } from '@/api/auth'
import { useLessonCompanies } from '@/api/directory'
import type { WorldStep } from '@/api/world'
import { MarkdownContent } from '@/components/MarkdownContent'
import { cn, intlLocale } from '@/lib/utils'

function percent(rate: number, locale: string) {
  return new Intl.NumberFormat(intlLocale(locale), { style: 'percent', maximumFractionDigits: 2 }).format(rate)
}

function Chips({ icon: Icon, items, tone }: { icon: typeof FileText; items: string[]; tone: 'paper' | 'people' }) {
  return (
    <ul className="flex flex-wrap gap-1.5">
      {items.map((item) => (
        <li
          key={item}
          className={cn(
            'w3d-pop inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
            tone === 'paper' ? 'bg-primary/[0.07] text-primary' : 'bg-foreground/[0.06] text-foreground/80',
          )}
        >
          <Icon className="h-3.5 w-3.5 shrink-0" aria-hidden />
          <span className="bidi-auto">{item}</span>
        </li>
      ))}
    </ul>
  )
}

/**
 * One step, as the 3D world tells it: the lesson it belongs to, where it
 * happens, the lesson's own words, the papers that come back, the taxes at
 * the rates in force (the student's region for ITP and AJD), the people, the
 * checklist items it ticks, the partners of the lesson, the video.
 */
export function StepPanel({
  slug,
  step,
  index,
  total,
  locale,
  placeName,
  checked,
  onToggle,
}: {
  slug: string
  step: WorldStep
  index: number
  total: number
  locale: string
  placeName: string
  checked: Record<number, boolean>
  onToggle(itemId: number, value: boolean): void
}) {
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const partners = useLessonCompanies(slug, step.lesson?.id ?? null, { province: user?.province_id ?? null })
  const companies = partners.data?.data.slice(0, 3) ?? []

  return (
    <div key={step.key} className="w3d-enter space-y-4">
      <div>
        <p className="flex items-center justify-between gap-3 text-xs font-medium text-muted-foreground">
          <span className="truncate">
            {step.lesson ? t('world3d.lesson', { number: step.lesson.number }) : null} · {t('world3d.stepOf', { index: index + 1, total })}
          </span>
        </p>
        <h2 className="bidi-auto mt-1 text-lg font-semibold leading-snug">{step.title}</h2>
        <div className="mt-2 flex flex-wrap gap-1.5">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-[#006FFE] px-2.5 py-1 text-xs font-semibold text-white">
            <Building2 className="h-3.5 w-3.5" aria-hidden />
            {placeName}
          </span>
          {step.plazo && (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-or/15 px-2.5 py-1 text-xs font-semibold text-or-fonce">
              <CalendarClock className="h-3.5 w-3.5" aria-hidden />
              {step.plazo}
            </span>
          )}
        </div>
      </div>

      {step.quote_html && (
        <blockquote className="border-s-2 border-or ps-3">
          <MarkdownContent html={step.quote_html} lang={locale} className="text-[0.9rem] leading-relaxed [&_p]:my-0" />
        </blockquote>
      )}

      {step.documents.length > 0 && (
        <section>
          <h3 className="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.papers')}</h3>
          <Chips icon={FileText} items={step.documents} tone="paper" />
        </section>
      )}

      {step.taxes.length > 0 && (
        <section>
          <h3 className="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.taxes')}</h3>
          <ul className="space-y-1.5">
            {step.taxes.map((tax) => (
              <li key={`${tax.code}-${tax.name}`} className="w3d-pop flex items-center justify-between gap-3 rounded-lg bg-or/10 px-3 py-2 text-sm">
                <span className="inline-flex min-w-0 items-center gap-2 font-medium">
                  <Landmark className="h-4 w-4 shrink-0 text-or-fonce" aria-hidden />
                  <span className="bidi-auto truncate">{tax.name}</span>
                </span>
                {tax.rate !== null && (
                  <span className="shrink-0 tabular-nums text-foreground/80">
                    {percent(tax.rate, i18n.language)}
                    {tax.region && <span className="ms-1 text-xs text-muted-foreground">· {tax.region}</span>}
                  </span>
                )}
              </li>
            ))}
          </ul>
        </section>
      )}

      {step.people.length > 0 && (
        <section>
          <h3 className="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.people')}</h3>
          <Chips icon={Users} items={step.people} tone="people" />
        </section>
      )}

      {step.figures.length > 0 && (
        <ul className="flex flex-wrap gap-1.5">
          {step.figures.map((figure) => (
            <li key={figure} className="rounded-md border px-2 py-1 text-xs tabular-nums text-foreground/80">
              <bdi>{figure}</bdi>
            </li>
          ))}
        </ul>
      )}

      {step.checklist.length > 0 && (
        <section>
          <h3 className="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.checklist')}</h3>
          <ul className="space-y-1.5">
            {step.checklist.map((item) => {
              const isChecked = checked[item.id] ?? item.checked
              return (
                <li key={item.id}>
                  <label className="flex min-h-10 cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-muted">
                    <input
                      type="checkbox"
                      className="mt-0.5 h-4 w-4 shrink-0 accent-[#006FFE]"
                      checked={isChecked}
                      onChange={(event) => onToggle(item.id, event.target.checked)}
                    />
                    <span className={cn('md-inline leading-snug', isChecked && 'text-muted-foreground line-through')} dangerouslySetInnerHTML={{ __html: item.text_html ?? '' }} />
                  </label>
                </li>
              )
            })}
          </ul>
        </section>
      )}

      {companies.length > 0 && (
        <section>
          <h3 className="mb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{t('world3d.partners')}</h3>
          <ul className="space-y-1.5">
            {companies.map((company, i) => (
              <li key={company.id} className="w3d-pop flex items-center gap-2.5 rounded-lg border bg-background px-2.5 py-2" style={{ animationDelay: `${i * 90}ms` }}>
                <span aria-hidden className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                  {company.name.slice(0, 2).toUpperCase()}
                </span>
                <span className="min-w-0">
                  <span className="block truncate text-sm font-medium">{company.name}</span>
                  <span className="block truncate text-xs text-muted-foreground">{[company.city, company.phone].filter(Boolean).join(' · ')}</span>
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {step.lesson && (
        <div className="flex flex-wrap gap-2 pt-1">
          {step.lesson.has_video && (
            <Link to={`/learn/${slug}/lessons/${step.lesson.id}`} className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
              <PlayCircle className="h-4 w-4" aria-hidden />
              {t('world3d.watchVideo')}
            </Link>
          )}
          <Link to={`/learn/${slug}/lessons/${step.lesson.id}`} className="inline-flex min-h-10 items-center gap-2 rounded-lg border px-3.5 text-sm font-medium hover:bg-muted">
            <BookOpen className="h-4 w-4" aria-hidden />
            {t('world3d.openLesson')}
          </Link>
        </div>
      )}
    </div>
  )
}
