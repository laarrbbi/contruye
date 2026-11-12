import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, X } from 'lucide-react'
import { useOffers, useOfferEvent, type StudentOffer } from '@/api/offers'
import { MarkdownContent } from '@/components/MarkdownContent'
import { Button } from '@/components/ui/button'
import { isRtl, isLocale, type Locale } from '@/i18n/locales'
import { localizedPath } from '@/i18n/paths'
import { cn, formatPrice, intlLocale } from '@/lib/utils'

/** Each offer counts as seen once per page load; the server keeps only the first anyway. */
const seenThisLoad = new Set<string>()

/**
 * The offer whose moment has come, if any: the end-of-course one first, then
 * the milestone, then the one after the purchase. Nothing renders while there
 * is none, so the pages that hold it look exactly as before.
 */
export function CurrentOffer({ variant, className }: { variant: 'full' | 'compact'; className?: string }) {
  const { data } = useOffers()
  const event = useOfferEvent()
  const offer = data?.[0]
  const stage = offer?.stage
  const { mutate } = event

  useEffect(() => {
    if (!stage || seenThisLoad.has(stage)) return
    seenThisLoad.add(stage)
    mutate({ stage, action: 'seen' })
  }, [stage, mutate])

  if (!offer) return null

  return (
    <OfferCard
      offer={offer}
      variant={variant}
      className={className}
      onCta={() => event.mutate({ stage: offer.stage, action: 'click' })}
      onDismiss={() => event.mutate({ stage: offer.stage, action: 'dismiss' })}
    />
  )
}

/**
 * The offer itself, in Hani's night palette on the dashboard (`full`) and as
 * a slim card above the page in the player (`compact`). The admin's preview
 * renders it too, with no handlers.
 */
export function OfferCard({
  offer,
  variant,
  className,
  onCta,
  onDismiss,
  stacked = false,
}: {
  offer: StudentOffer
  variant: 'full' | 'compact'
  className?: string
  onCta?: () => void
  onDismiss?: () => void
  /** One column whatever the screen: for a narrow place such as the admin's preview. */
  stacked?: boolean
}) {
  const { t, i18n } = useTranslation()
  const locale: Locale = isLocale(i18n.language) ? i18n.language : 'es'
  // The offer's text keeps its own language and direction when it differs from the page's.
  const textLang = offer.lang !== locale ? offer.lang : undefined
  const textDir = textLang ? (isRtl(textLang) ? 'rtl' : 'ltr') : undefined
  const ctaLabel = offer.cta_label || t('offers.cta')
  const until = offer.closes_at
    ? t('offers.until', {
        date: new Intl.DateTimeFormat(intlLocale(locale), { dateStyle: 'long', timeStyle: 'short' }).format(new Date(offer.closes_at)),
      })
    : null
  const price = offer.price_eur !== null ? formatPrice(offer.price_eur, 'EUR', locale) : null

  const cta = (size: 'sm' | 'lg') => {
    const inner = (
      <>
        {ctaLabel}
        <ArrowRight className="h-4 w-4 rtl:-scale-x-100" />
      </>
    )
    if (offer.course) {
      return (
        <Button asChild variant="accent" size={size}>
          <Link to={localizedPath(`/cursos/${offer.course.slug}`, locale)} onClick={onCta}>
            {inner}
          </Link>
        </Button>
      )
    }
    return (
      <Button asChild variant="accent" size={size}>
        <a href={offer.url ?? '#'} target="_blank" rel="noopener noreferrer" onClick={onCta}>
          {inner}
        </a>
      </Button>
    )
  }

  if (variant === 'compact') {
    return (
      <aside
        aria-label={t(`offers.stage.${offer.stage}`)}
        className={cn('relative flex flex-wrap items-center gap-x-5 gap-y-3 rounded-2xl border border-or/35 bg-card p-4 pe-12 shadow-[0_20px_40px_-30px_rgb(20_26_34/0.5)] sm:p-5 sm:pe-14', className)}
      >
        <div className="min-w-0 flex-1 basis-60">
          <p className="etiquette text-or-fonce">{t(`offers.stage.${offer.stage}`)}</p>
          <p className="mt-1.5 font-semibold leading-snug" lang={textLang} dir={textDir}>
            {offer.title}
          </p>
          {until && <p className="mt-1 text-xs text-muted-foreground">{until}</p>}
        </div>
        {price && (
          <p className="titre text-xl tabular-nums">
            <bdi>{price}</bdi>
          </p>
        )}
        {cta('sm')}
        {onDismiss && (
          <button
            type="button"
            onClick={onDismiss}
            className="absolute end-1.5 top-1.5 rounded-full p-3 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          >
            <X className="h-4 w-4" />
            <span className="sr-only">{t('offers.close')}</span>
          </button>
        )}
      </aside>
    )
  }

  return (
    <section
      aria-label={t(`offers.stage.${offer.stage}`)}
      className={cn('theme-night relative overflow-hidden rounded-3xl bg-[radial-gradient(110%_90%_at_80%_10%,#12253F,#0B1A2E_55%,#070F1C)] p-8 text-foreground sm:p-10', className)}
    >
      <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:48px_48px]" />
      <div className={cn('relative grid gap-8', !stacked && 'lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end')}>
        <div className="min-w-0">
          <p className="etiquette text-or">{t(`offers.stage.${offer.stage}`)}</p>
          {/* One block in the text's own language, so title and text share an edge. */}
          <div lang={textLang} dir={textDir}>
            <h2 className="titre mt-4 text-[clamp(1.6rem,1.2rem+1.4vw,2.5rem)]">{offer.title}</h2>
            <MarkdownContent html={offer.body_html} className="mt-4 max-w-2xl text-foreground/75" />
          </div>
          {offer.course && <p className="mt-4 text-sm text-foreground/55">{offer.course.title}</p>}
        </div>
        <div className={cn('flex flex-col items-start gap-3', !stacked && 'lg:items-end')}>
          {price && (
            <p className="titre text-[clamp(2rem,1.6rem+1.2vw,2.75rem)] !leading-none tabular-nums text-or">
              <bdi>{price}</bdi>
            </p>
          )}
          <div className="flex flex-wrap items-center gap-3">
            {cta('lg')}
            {onDismiss && (
              <Button variant="ghost" size="lg" onClick={onDismiss} className="px-4 text-foreground/70">
                {t('offers.notNow')}
              </Button>
            )}
          </div>
          {until && <p className="text-xs text-foreground/60">{until}</p>}
        </div>
      </div>
    </section>
  )
}
