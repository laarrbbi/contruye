import { useTranslation } from 'react-i18next'
import { Landmark } from 'lucide-react'
import type { LegalParametersView } from '@/api/templates'
import { cn, formatPercent, intlLocale } from '@/lib/utils'

/**
 * The legal parameters the template calculates with, read only: the same for
 * every user, kept up to date by CONSTRUYE, with the date of the last review.
 * The community of the site, when the template has one, shows its ITP and AJD.
 */
export function LegalParametersPanel({
  legal,
  community,
  className,
}: {
  legal: LegalParametersView
  community?: string | null
  className?: string
}) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const region = community ? legal.regions.find((r) => r.name === community) : undefined
  const reviewed = legal.reviewed_at
    ? new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'long' }).format(new Date(legal.reviewed_at))
    : null

  return (
    <details className={cn('group rounded-2xl border border-foreground/[0.08] bg-card', className)}>
      <summary className="flex cursor-pointer list-none items-center gap-3 px-5 py-4 text-sm [&::-webkit-details-marker]:hidden">
        <Landmark aria-hidden className="h-4 w-4 shrink-0 text-or-fonce" />
        <span className="flex-1 font-medium">{t('legalParams.title')}</span>
        {reviewed && <span className="text-xs text-muted-foreground">{t('legalParams.reviewed', { date: reviewed })}</span>}
      </summary>
      <div className="space-y-4 border-t border-foreground/[0.08] px-5 py-4 text-sm">
        <p className="text-muted-foreground">{t('legalParams.readOnly')}</p>
        {region && (
          <p className="rounded-lg bg-or/10 px-3 py-2">
            <bdi lang="es">{region.name}</bdi>: ITP {formatPercent(region.itp, locale)} · AJD {formatPercent(region.ajd, locale)}
            {region.note && (
              <span className="mt-1 block text-xs text-muted-foreground" lang="es">
                {region.note}
              </span>
            )}
          </p>
        )}
        <dl className="grid gap-x-6 gap-y-2 sm:grid-cols-2">
          {legal.parameters.map((p) => (
            <div key={p.code} className="flex items-baseline justify-between gap-3 border-b border-foreground/[0.06] pb-2">
              <dt className="min-w-0" lang="es">
                {p.label}
                <span className="block text-xs text-muted-foreground">{p.legal_basis}</span>
              </dt>
              <dd className="shrink-0 font-semibold tabular-nums">{formatPercent(p.value, locale)}</dd>
            </div>
          ))}
        </dl>
      </div>
    </details>
  )
}
