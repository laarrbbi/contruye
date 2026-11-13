import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Calculator, Check } from 'lucide-react'
import { useExtra } from '@/api/templates'
import { Button } from '@/components/ui/button'
import { cn, formatPrice } from '@/lib/utils'

/** What the extra includes, as the offer of the package's home page text lists it (section 9). */
const INCLUDES = ['excel', 'simplified', 'guide', 'lessons', 'taxes'] as const

function Includes({ className }: { className?: string }) {
  const { t } = useTranslation()
  return (
    <ul className={cn('space-y-2.5 text-sm', className)}>
      {INCLUDES.map((key) => (
        <li key={key} className="flex items-start gap-2.5">
          <Check aria-hidden className="mt-0.5 h-4 w-4 shrink-0 text-or" />
          <span>{t(`extra.includes.${key}`)}</span>
        </li>
      ))}
    </ul>
  )
}

/**
 * The extra offered at the end of the course: the professional financial
 * template, for a student of any plan. On sale only once the admin has
 * switched it on with a price; until then it says so and offers nothing to
 * buy. Nothing renders for a student who already has it, unless `ownedLink`
 * asks for the way in.
 */
export function ExtraOffer({ slug, className, ownedLink = false }: { slug: string; className?: string; ownedLink?: boolean }) {
  const { t, i18n } = useTranslation()
  const { data: extra } = useExtra(slug)

  if (!extra) return null

  if (extra.owned) {
    if (!ownedLink) return null
    return (
      <Button asChild variant="outline" className={className}>
        <Link to={`/learn/${slug}/financials`}>
          <Calculator className="h-4 w-4" />
          {t('extra.open')}
        </Link>
      </Button>
    )
  }

  const price = extra.on_sale && extra.price_eur !== null ? formatPrice(extra.price_eur, 'EUR', i18n.language) : null

  return (
    <section
      aria-label={t('extra.eyebrow')}
      className={cn('theme-night relative overflow-hidden rounded-3xl bg-[radial-gradient(110%_90%_at_80%_10%,#12253F,#0B1A2E_55%,#070F1C)] p-8 text-foreground sm:p-10', className)}
    >
      <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:48px_48px]" />
      <div className={cn('relative grid gap-8', extra.on_sale && 'lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end')}>
        <div className="min-w-0">
          <p className="etiquette text-or">{t('extra.eyebrow')}</p>
          <h2 className="titre mt-4 text-[clamp(1.6rem,1.2rem+1.4vw,2.5rem)]">{t('extra.title')}</h2>
          <p className="mt-4 max-w-2xl text-foreground/75">{t('extra.body')}</p>
          <Includes className="mt-6 max-w-2xl text-foreground/85" />
        </div>
        <div className={cn('flex flex-col items-start gap-3', extra.on_sale && 'lg:items-end')}>
          {price && (
            <p className="titre text-[clamp(2rem,1.6rem+1.2vw,2.75rem)] !leading-none tabular-nums text-or">
              <bdi>{price}</bdi>
            </p>
          )}
          {extra.on_sale ? (
            <Button asChild variant="accent" size="lg">
              <Link to={`/checkout/${slug}?extra=1`}>
                {t('extra.cta')}
                <ArrowRight className="h-4 w-4 rtl:-scale-x-100" />
              </Link>
            </Button>
          ) : (
            <p className="text-sm text-foreground/60">{t('extra.notOnSale')}</p>
          )}
        </div>
      </div>
    </section>
  )
}

/** The extra on the checkout page: what is being bought, at its own price. */
export function ExtraCheckoutCard({ price }: { price: string | null }) {
  const { t } = useTranslation()
  return (
    <section className="space-y-5 rounded-2xl border border-or/35 bg-card p-6">
      <p className="etiquette text-or-fonce">{t('extra.eyebrow')}</p>
      <h2 className="text-lg font-bold">{t('extra.title')}</h2>
      {price && <p className="text-4xl font-extrabold text-primary tabular-nums"><bdi>{price}</bdi></p>}
      <p className="text-sm text-muted-foreground">{t('extra.body')}</p>
      <Includes />
    </section>
  )
}
