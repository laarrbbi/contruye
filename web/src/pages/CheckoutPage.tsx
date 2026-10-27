import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { isAxiosError } from 'axios'
import {
  BadgePercent,
  Check,
  CircleCheck,
  CreditCard,
  FileText,
  Globe,
  Infinity as InfinityIcon,
  Landmark,
  Layers,
  Smartphone,
  Sparkles,
  X,
} from 'lucide-react'
import { useCourse } from '@/api/courses'
import {
  apiErrorMessage,
  apiFieldError,
  useCheckout,
  useCheckoutMethods,
  useQuote,
  type CheckoutMethod,
  type CheckoutPayload,
  type Currency,
  type OrderScope,
  type PricedCourse,
  type PricedModule,
  type QuotePayload,
} from '@/api/orders'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import { CampaignBanner } from '@/components/CampaignBanner'
import { BackgroundVideo } from '@/components/BackgroundVideo'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { cn, formatPrice } from '@/lib/utils'
import { ContactOptions } from '@/components/ContactOptions'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'


interface MethodOption {
  id: CheckoutMethod
  icon: typeof CreditCard
  labelKey: string
  hintKey: string
}

const METHOD_OPTIONS: MethodOption[] = [
  { id: 'chargily', icon: CreditCard, labelKey: 'checkout.method.chargily', hintKey: 'checkout.method.chargilyHint' },
  { id: 'stripe', icon: Globe, labelKey: 'checkout.method.stripe', hintKey: 'checkout.method.stripeHint' },
  { id: 'manual_ccp', icon: Landmark, labelKey: 'checkout.method.ccp', hintKey: 'checkout.method.ccpHint' },
  { id: 'manual_baridimob', icon: Smartphone, labelKey: 'checkout.method.baridimob', hintKey: 'checkout.method.baridimobHint' },
]

export function CheckoutPage() {
  const { slug } = useParams<{ slug: string }>()
  const { t, i18n } = useTranslation()
  usePageTitle(t('checkout.title'))
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const courseQuery = useCourse(slug)
  const course = courseQuery.data as PricedCourse | undefined

  const [scope, setScope] = useState<OrderScope>('full')
  const [selected, setSelected] = useState<number[]>([])
  const [method, setMethod] = useState<CheckoutMethod>('chargily')
  const [couponInput, setCouponInput] = useState('')
  const [coupon, setCoupon] = useState<string | undefined>(undefined)
  const [upgradedNote, setUpgradedNote] = useState(false)

  // ?modules=1,2,3 deep-link: parsed exactly once on mount.
  const preselectRef = useRef<number[] | null>(null)
  if (preselectRef.current === null) {
    preselectRef.current = (searchParams.get('modules') ?? '')
      .split(',')
      .map((raw) => Number(raw))
      .filter((id) => Number.isInteger(id) && id > 0)
  }

  // Initialise scope/selection once the course arrives: deep-linked modules
  // win, then partially-enrolled students default to the modules tab.
  const initializedRef = useRef(false)
  useEffect(() => {
    if (!course || initializedRef.current) return
    initializedRef.current = true
    const preselect = preselectRef.current ?? []
    const valid = course.modules
      .filter((m) => preselect.includes(m.id) && !m.is_owned)
      .map((m) => m.id)
    if (valid.length > 0) {
      setSelected(valid)
      setScope('modules')
    } else if (searchParams.get('scope') === 'modules') {
      setScope('modules')
    } else if (course.is_enrolled && !course.full_access) {
      setScope('modules')
    }
    // searchParams is read once here, guarded by initializedRef.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [course])

  // Only what can take money right now (GET /checkout/methods), and euros
  // only for a course that has a euro price. The selection follows: if the
  // default is not offered, the first method that is becomes the default.
  const methodsQuery = useCheckoutMethods()
  const methodOptions = useMemo(
    () =>
      METHOD_OPTIONS.filter(
        (o) => (methodsQuery.data ?? []).includes(o.id) && (o.id !== 'stripe' || course?.price_eur != null),
      ),
    [methodsQuery.data, course],
  )
  useEffect(() => {
    if (methodOptions.length > 0 && !methodOptions.some((o) => o.id === method)) {
      setMethod(methodOptions[0].id)
    }
  }, [methodOptions, method])
  const noMethod = methodsQuery.isSuccess && course !== undefined && methodOptions.length === 0

  const currency: Currency = method === 'stripe' ? 'EUR' : 'DZD'
  const priceOf = (module: PricedModule) => (currency === 'EUR' ? module.price_eur : module.price_dzd)

  const modules = useMemo(
    () => (course ? [...course.modules].sort((a, b) => a.order - b.order) : []),
    [course],
  )

  // Selection restricted to modules that are actually purchasable right now
  // (exist, not owned, sellable in the active currency).
  const effectiveSelection = useMemo(
    () =>
      modules
        .filter((m) => selected.includes(m.id) && !m.is_owned && priceOf(m) !== null)
        .map((m) => m.id),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [modules, selected, currency],
  )

  const quotePayload = useMemo<QuotePayload | null>(() => {
    if (!course || course.full_access) return null
    if (scope === 'modules' && effectiveSelection.length === 0) return null
    return {
      course_id: course.id,
      scope,
      ...(scope === 'modules' ? { module_ids: effectiveSelection } : {}),
      method,
      ...(coupon ? { coupon_code: coupon } : {}),
    }
  }, [course, scope, effectiveSelection, method, coupon])

  const debouncedPayload = useDebouncedValue(quotePayload, 400)
  const quoteQuery = useQuote(debouncedPayload)
  const quote = quotePayload === null ? undefined : quoteQuery.data

  const checkout = useCheckout()

  // Selecting every remaining module means the backend upgraded the quote to
  // full access: mirror that in the UI, once per distinct selection.
  const selectionKey = [...effectiveSelection].sort((a, b) => a - b).join(',')
  const upgradedKeyRef = useRef<string | null>(null)
  useEffect(() => {
    if (quote?.upgraded_to_full && scope === 'modules' && upgradedKeyRef.current !== selectionKey) {
      upgradedKeyRef.current = selectionKey
      setScope('full')
      setUpgradedNote(true)
    }
  }, [quote, scope, selectionKey])

  const toggleModule = (id: number) => {
    setUpgradedNote(false)
    checkout.reset()
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  const handleScopeChange = (value: string) => {
    setScope(value as OrderScope)
    setUpgradedNote(false)
    checkout.reset()
  }

  const applyCoupon = () => {
    const code = couponInput.trim()
    if (code) setCoupon(code)
  }

  const removeCoupon = () => {
    setCoupon(undefined)
    setCouponInput('')
  }

  const submit = () => {
    if (!course) return
    const payload: CheckoutPayload = {
      course_id: course.id,
      scope,
      method,
      ...(scope === 'modules' ? { module_ids: effectiveSelection } : {}),
      ...(coupon ? { coupon_code: coupon } : {}),
    }
    checkout.mutate(payload, {
      onSuccess: (res) => {
        if (res.checkout_url) {
          window.location.href = res.checkout_url
        } else {
          navigate(`/orders/${res.order.id}/pay`)
        }
      },
    })
  }

  if (courseQuery.isLoading || methodsQuery.isLoading) {
    return (
      <div className="container flex min-h-[50vh] items-center justify-center text-muted-foreground">
        {t('common.loading')}
      </div>
    )
  }

  if (courseQuery.isError || methodsQuery.isError || !course) {
    const notFound = isAxiosError(courseQuery.error) && courseQuery.error.response?.status === 404
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <p className="text-muted-foreground">{notFound ? t('course.notFound') : t('common.error')}</p>
        <Button asChild variant="outline">
          <Link to="/">{t('common.back')}</Link>
        </Button>
      </div>
    )
  }

  // Already owns everything: nothing left to buy.
  if (course.full_access) {
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <CircleCheck className="h-12 w-12 text-success" aria-hidden />
        <h1 className="text-lg font-semibold">{t('checkout.errors.alreadyEnrolled')}</h1>
        <Button asChild>
          <Link to={`/learn/${course.slug}`}>{t('checkout.errors.goToCourse')}</Link>
        </Button>
      </div>
    )
  }

  const locale = i18n.language
  const fullPrice = currency === 'EUR' ? course.price_eur : course.price_dzd
  const anchorLocal = modules.reduce((sum, m) => sum + (priceOf(m) ?? 0), 0)
  // Only trust the quote's anchor when it is in the currently displayed
  // currency — right after a method switch the (kept-previous) quote still
  // carries amounts in the old currency.
  const anchor =
    quote?.scope === 'full' && quote.currency === currency ? quote.full_price_anchor : anchorLocal
  // A running promotion is part of the price on offer: the card used to say
  // 90 000 DA while the button said "Payer 72 000 DA". The quote's figure once
  // it is in; until then the sales page's arithmetic (Campaign::apply). A
  // coupon still shows only in the summary.
  const campaignOff =
    quote?.scope === 'full' && quote.currency === currency
      ? quote.campaign_discount
      : fullPrice !== null && course.campaign
        ? fullPrice - Math.round((fullPrice * (100 - course.campaign.percent_off)) / 100)
        : 0
  const fullPriceNow = fullPrice !== null ? fullPrice - campaignOff : null
  const fullPercent =
    fullPriceNow !== null && anchor > 0 ? Math.max(0, Math.round((1 - fullPriceNow / anchor) * 100)) : 0

  const tiers = [...course.module_discount_tiers].sort((a, b) => a.min_modules - b.min_modules)
  const count = effectiveSelection.length
  const reachedTiers = tiers.filter((tier) => count >= tier.min_modules)
  const activeTier = reachedTiers.length > 0 ? reachedTiers[reachedTiers.length - 1] : null
  const nextTier = tiers.find((tier) => tier.min_modules > count) ?? null
  const purchasableCount = modules.filter((m) => !m.is_owned && priceOf(m) !== null).length
  const showTierHint = nextTier !== null && nextTier.min_modules <= purchasableCount

  // Coupon feedback: the quote endpoint silently drops invalid coupons
  // (coupon_code comes back null), while 422s carry a field error.
  const couponFieldError = apiFieldError(quoteQuery.error, 'coupon_code') ?? apiFieldError(checkout.error, 'coupon_code')
  const couponRejected =
    coupon !== undefined && quote !== undefined && !quoteQuery.isFetching && quote.coupon_code !== coupon
  const couponError = couponFieldError ?? (couponRejected ? t('checkout.coupon.invalid') : undefined)
  const couponApplied = coupon !== undefined && quote !== undefined && quote.coupon_code === coupon

  const moduleIdsError = apiFieldError(quoteQuery.error, 'module_ids') ?? apiFieldError(checkout.error, 'module_ids')
  const methodError = apiFieldError(quoteQuery.error, 'method')
  const alreadyEnrolledError = apiFieldError(checkout.error, 'course_id')
  const checkoutGenericError =
    checkout.isError && !alreadyEnrolledError && !apiFieldError(checkout.error, 'coupon_code') && !apiFieldError(checkout.error, 'module_ids')
      ? apiErrorMessage(checkout.error) ?? t('common.error')
      : undefined

  const gatewayDown = isAxiosError(checkout.error) && checkout.error.response?.status === 502
  const manualFallback = gatewayDown
    ? methodOptions.find((o) => o.id === 'manual_ccp' || o.id === 'manual_baridimob')
    : undefined

  const quoteBroken = quoteQuery.isError && !couponFieldError
  const noSelection = scope === 'modules' && effectiveSelection.length === 0
  const submitDisabled =
    noMethod || noSelection || quoteBroken || !quote || quoteQuery.isFetching || checkout.isPending
  const isManual = method === 'manual_ccp' || method === 'manual_baridimob'

  const fmt = (amount: number) => formatPrice(amount, currency, locale)
  // Quote amounts are always formatted in the quote's own currency: while a
  // method switch is debouncing/fetching, the previous quote's numbers are
  // still in the old currency.
  const fmtQ = (amount: number) => formatPrice(amount, quote?.currency ?? currency, locale)

  return (
    <div>
      {/* Video header band — same language as the landing hero and the auth
          pages, without stealing the width the checkout columns need. */}
      <div className="relative isolate overflow-hidden bg-primary text-primary-foreground">
        <BackgroundVideo
          // Not on phones: at the moment of paying, every byte on a mobile
          // connection goes to the payment, not to a decorative band.
          media="(min-width: 768px)"
          brightness={0.75}
          cropCorner="top-right"
          overlayClassName="bg-primary/60"
        />
        <div className="container space-y-1 py-10">
          <h1 className="text-3xl font-extrabold">{t('checkout.title')}</h1>
          <p className="bidi-auto text-primary-foreground/75">
            <Link to={`/cursos/${course.slug}`} className="hover:underline">
              {course.title}
            </Link>
          </p>
        </div>
      </div>

      <div className="container py-8 sm:py-12">
      {quote?.campaign && (
        <CampaignBanner campaign={quote.campaign} slug={slug} className="mb-6 rounded-lg" />
      )}

      <div className="grid gap-8 lg:grid-cols-[1fr_400px] lg:items-start">
        {/* ——— Left column: scope + payment method ——— */}
        <div className="space-y-8">
          <Tabs value={scope} onValueChange={handleScopeChange}>
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="full">
                <Sparkles className="h-4 w-4" />
                {t('checkout.scopeFull')}
              </TabsTrigger>
              <TabsTrigger value="modules">
                <Layers className="h-4 w-4" />
                {t('checkout.scopeModules')}
              </TabsTrigger>
            </TabsList>

            {/* Full access */}
            <TabsContent value="full" className="mt-4 space-y-4">
              {upgradedNote && (
                <div className="flex items-start gap-2 rounded-lg border border-primary/40 bg-primary/5 p-3 text-sm">
                  <CircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                  <p>{t('checkout.quote.upgradedNote')}</p>
                </div>
              )}
              <Card>
                <CardContent className="space-y-5 p-6">
                  <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-lg font-bold">{t('checkout.full.title')}</h2>
                    {fullPercent > 0 && (
                      <Badge className="gap-1 text-sm">
                        <BadgePercent className="h-3.5 w-3.5" />
                        {t('checkout.full.badge', { percent: fullPercent })}
                      </Badge>
                    )}
                  </div>

                  <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    {fullPriceNow !== null && (
                      <span className="text-4xl font-extrabold text-primary">{fmt(fullPriceNow)}</span>
                    )}
                    {anchor > 0 && fullPriceNow !== null && anchor > fullPriceNow && (
                      <span className="text-lg text-muted-foreground line-through">{fmt(anchor)}</span>
                    )}
                  </div>

                  <ul className="space-y-2.5 text-sm">
                    <li className="flex items-center gap-2.5">
                      <CircleCheck className="h-4 w-4 shrink-0 text-success" />
                      {t('checkout.full.bullets.modules', { count: modules.length })}
                    </li>
                    <li className="flex items-center gap-2.5">
                      <InfinityIcon className="h-4 w-4 shrink-0 text-success" />
                      {t('checkout.full.bullets.lifetime')}
                    </li>
                    <li className="flex items-center gap-2.5">
                      <FileText className="h-4 w-4 shrink-0 text-success" />
                      {t('checkout.full.bullets.attachments')}
                    </li>
                  </ul>
                </CardContent>
              </Card>
            </TabsContent>

            {/* Per-module */}
            <TabsContent value="modules" className="mt-4 space-y-4">
              <h2 className="text-lg font-bold">{t('checkout.modules.title')}</h2>

              <div className="space-y-2" role="group" aria-label={t('checkout.modules.title')}>
                {modules.map((module, index) => {
                  const owned = module.is_owned === true
                  const price = priceOf(module)
                  const sellable = price !== null
                  const checked = owned || effectiveSelection.includes(module.id)
                  return (
                    <button
                      key={module.id}
                      type="button"
                      role="checkbox"
                      aria-checked={checked}
                      disabled={owned || !sellable}
                      onClick={() => toggleModule(module.id)}
                      className={cn(
                        'flex w-full items-center gap-3 rounded-lg border bg-background p-3.5 text-start transition-colors',
                        checked && !owned && 'border-primary bg-primary/5',
                        !owned && sellable && 'hover:border-primary/50',
                        (owned || !sellable) && 'cursor-default opacity-70',
                      )}
                    >
                      <span
                        className={cn(
                          'flex h-5 w-5 shrink-0 items-center justify-center rounded border-2',
                          checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                        )}
                        aria-hidden
                      >
                        {checked && <Check className="h-3.5 w-3.5" />}
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block text-sm font-medium leading-snug">
                          {index + 1}. <bdi>{module.title}</bdi>
                        </span>
                        {owned && (
                          <Badge variant="secondary" className="mt-1.5">
                            {t('checkout.modules.owned')}
                          </Badge>
                        )}
                      </span>
                      <span className="shrink-0 text-sm font-semibold tabular-nums">
                        {sellable ? fmt(price) : t('checkout.modules.fullOnly')}
                      </span>
                    </button>
                  )
                })}
              </div>

              {moduleIdsError && <p className="text-sm text-destructive">{moduleIdsError}</p>}

              {/* Tier ladder */}
              {tiers.length > 0 && (
                <div className="space-y-3 rounded-lg border bg-secondary/40 p-4">
                  <p className="text-sm font-semibold">{t('checkout.modules.tierTitle')}</p>
                  <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {tiers.map((tier) => {
                      const isActive = activeTier?.min_modules === tier.min_modules
                      const reached = count >= tier.min_modules
                      return (
                        <div
                          key={tier.min_modules}
                          className={cn(
                            'rounded-md border px-2 py-2 text-center transition-colors',
                            isActive
                              ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                              : reached
                                ? 'border-primary/40 bg-primary/10 text-primary'
                                : 'border-border bg-background text-muted-foreground',
                          )}
                        >
                          <p className="text-base font-extrabold leading-tight">
                            {t('checkout.modules.tierPercent', { percent: tier.percent_off })}
                          </p>
                          <p className="text-[11px] leading-tight">
                            {t('checkout.modules.tierMin', { min: tier.min_modules })}
                          </p>
                        </div>
                      )
                    })}
                  </div>
                  {showTierHint && nextTier && (
                    <p className="text-sm text-primary">
                      {t('checkout.modules.tierHint', {
                        count: nextTier.min_modules - count,
                        percent: nextTier.percent_off,
                      })}
                    </p>
                  )}
                </div>
              )}
            </TabsContent>
          </Tabs>

          {/* Payment methods */}
          <section className="space-y-3">
            <h2 className="text-lg font-bold">{t('checkout.method.title')}</h2>
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label={t('checkout.method.title')}>
              {methodOptions.map((option) => {
                const Icon = option.icon
                const active = method === option.id
                return (
                  <button
                    key={option.id}
                    type="button"
                    role="radio"
                    aria-checked={active}
                    onClick={() => {
                      setMethod(option.id)
                      checkout.reset()
                    }}
                    className={cn(
                      'flex items-center gap-3 rounded-lg border bg-background p-4 text-start transition-colors',
                      active ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'hover:border-primary/50',
                    )}
                  >
                    <Icon className={cn('h-6 w-6 shrink-0', active ? 'text-primary' : 'text-muted-foreground')} />
                    <span className="min-w-0">
                      <span className="block text-sm font-semibold">{t(option.labelKey)}</span>
                      <span className="block text-xs text-muted-foreground">{t(option.hintKey)}</span>
                    </span>
                  </button>
                )
              })}
            </div>
            {noMethod && (
              <div className="space-y-3 rounded-lg border bg-muted p-4 text-sm">
                <p>{t('checkout.method.noneAvailable')}</p>
                <ContactOptions prefill={t('contact.prefillGeneral')} showHours={false} />
              </div>
            )}
            {methodError && <p className="text-sm text-destructive">{methodError}</p>}
          </section>
        </div>

        {/* ——— Right column: quote box ——— */}
        <aside className="lg:sticky lg:top-24">
          <Card>
            <CardContent className="space-y-4 p-5">
              <h2 className="text-lg font-bold">{t('checkout.quote.title')}</h2>

              {noSelection ? (
                <p className="rounded-md bg-muted p-4 text-sm text-muted-foreground">
                  {t('checkout.modules.empty')}
                </p>
              ) : quoteBroken ? (
                <p className="rounded-md border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
                  {moduleIdsError ?? methodError ?? apiErrorMessage(quoteQuery.error) ?? t('checkout.quote.quoteError')}
                </p>
              ) : !quote ? (
                <p className="py-4 text-sm text-muted-foreground">{t('common.loading')}</p>
              ) : (
                <div className={cn('space-y-3', quoteQuery.isFetching && 'opacity-60')}>
                  {scope === 'modules' && quote.scope === 'modules' && (
                    <p className="text-sm text-muted-foreground">
                      {t('checkout.modules.selectedCount', { count: quote.items.length })}
                    </p>
                  )}
                  {scope === 'modules' && quote.scope === 'full' && (
                    <p className="rounded-md bg-primary/5 p-2.5 text-xs text-primary">
                      {t('checkout.quote.upgradedNote')}
                    </p>
                  )}

                  <div className="space-y-2 text-sm">
                    <div className="flex items-baseline justify-between gap-2">
                      <span className="text-muted-foreground">{t('checkout.quote.subtotal')}</span>
                      {/* For a full quote the API's `subtotal` already equals the
                          discounted full price; rebuild the pre-discount line so
                          the displayed rows always sum to the total. */}
                      <span className="font-medium tabular-nums">
                        {fmtQ(
                          quote.total +
                            quote.tier_discount +
                            quote.campaign_discount +
                            quote.coupon_discount,
                        )}
                      </span>
                    </div>
                    {quote.tier_discount > 0 && (
                      <div className="flex items-baseline justify-between gap-2 text-success">
                        <span>{t('checkout.quote.tierDiscount', { percent: quote.tier_percent })}</span>
                        <span className="font-medium tabular-nums">&minus;{fmtQ(quote.tier_discount)}</span>
                      </div>
                    )}
                    {quote.campaign_discount > 0 && quote.campaign && (
                      <div className="flex items-baseline justify-between gap-2 text-success">
                        <span>
                          <bdi>{quote.campaign.label}</bdi> (&minus;{quote.campaign_percent}%)
                        </span>
                        <span className="font-medium tabular-nums">
                          &minus;{fmtQ(quote.campaign_discount)}
                        </span>
                      </div>
                    )}
                    {quote.coupon_discount > 0 && quote.coupon_code && (
                      <div className="flex items-baseline justify-between gap-2 text-success">
                        <span>
                          {t('checkout.quote.coupon', { code: quote.coupon_code, percent: quote.coupon_percent })}
                        </span>
                        <span className="font-medium tabular-nums">&minus;{fmtQ(quote.coupon_discount)}</span>
                      </div>
                    )}
                  </div>

                  <Separator />

                  <div className="flex items-baseline justify-between gap-2">
                    <span className="font-bold">{t('checkout.quote.total')}</span>
                    <span className="text-2xl font-extrabold text-primary tabular-nums">{fmtQ(quote.total)}</span>
                  </div>

                  {quote.savings_vs_itemized > 0 && (
                    <p className="text-xs text-muted-foreground">
                      {t('checkout.quote.itemized', {
                        price: fmtQ(quote.scope === 'full' ? quote.full_price_anchor : quote.subtotal),
                      })}{' '}
                      <span className="font-semibold text-success">
                        {t('checkout.quote.savings', { amount: fmtQ(quote.savings_vs_itemized) })}
                      </span>
                    </p>
                  )}

                  {/* Upgrade nudge */}
                  {quote.upgrade_hint && (
                    <div className="space-y-2 rounded-lg border border-primary/40 bg-primary/5 p-3">
                      <p className="text-sm font-semibold text-primary">
                        {t('checkout.quote.upgradeTitle', { delta: fmtQ(quote.upgrade_hint.price_delta) })}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {t('checkout.quote.upgradeSubtitle', { count: quote.upgrade_hint.modules_missing })}
                      </p>
                      <Button
                        variant="outline"
                        size="sm"
                        className="w-full border-primary/50 text-primary"
                        onClick={() => handleScopeChange('full')}
                      >
                        <Sparkles className="h-4 w-4" />
                        {t('checkout.quote.upgradeCta')}
                      </Button>
                    </div>
                  )}
                </div>
              )}

              {/* Coupon */}
              <div className="space-y-2">
                {couponApplied && quote ? (
                  <div className="flex items-center justify-between gap-2 rounded-md border border-success/40 bg-success/5 px-3 py-2">
                    <p className="text-sm text-success">
                      {t('checkout.coupon.applied', { code: quote.coupon_code })}
                    </p>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7 shrink-0"
                      onClick={removeCoupon}
                      aria-label={t('checkout.coupon.remove')}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </div>
                ) : (
                  <div className="flex gap-2">
                    <Input
                      value={couponInput}
                      onChange={(e) => setCouponInput(e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault()
                          applyCoupon()
                        }
                      }}
                      placeholder={t('checkout.coupon.placeholder')}
                      aria-label={t('checkout.coupon.label')}
                      className="h-9"
                    />
                    <Button variant="outline" size="sm" onClick={applyCoupon} disabled={!couponInput.trim()}>
                      {t('checkout.coupon.apply')}
                    </Button>
                  </div>
                )}
                {couponError && <p className="text-sm text-destructive">{couponError}</p>}
              </div>

              {/* Checkout errors */}
              {alreadyEnrolledError && (
                <div className="space-y-2 rounded-md border border-destructive/40 bg-destructive/5 p-3">
                  <p className="text-sm text-destructive">{alreadyEnrolledError}</p>
                  <Button asChild variant="outline" size="sm" className="w-full">
                    <Link to={`/learn/${course.slug}`}>{t('checkout.errors.goToCourse')}</Link>
                  </Button>
                </div>
              )}
              {checkoutGenericError && (
                <div role="alert" className="space-y-2">
                  <p className="text-sm text-destructive">{checkoutGenericError}</p>
                  {/* The gateway is down but the buyer is ready to pay: offer the
                      method that needs no gateway instead of a dead end. */}
                  {manualFallback && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="h-auto min-h-9 w-full whitespace-normal py-2"
                      onClick={() => {
                        setMethod(manualFallback.id)
                        checkout.reset()
                      }}
                    >
                      {t('checkout.fallbackToManual', { method: t(manualFallback.labelKey) })}
                    </Button>
                  )}
                </div>
              )}

              <Button className="w-full font-semibold" size="lg" disabled={submitDisabled} onClick={submit}>
                {checkout.isPending
                  ? t('checkout.submit.processing')
                  : isManual
                    ? t('checkout.submit.order')
                    : quote
                      ? t('checkout.submit.pay', { amount: fmtQ(quote.total) })
                      : t('checkout.submit.order')}
              </Button>
            </CardContent>
          </Card>

          {/* Last chance to ask before paying — renders nothing until a
              channel is configured. Desktop only: on a phone the footer's
              identical buttons come right after it. */}
          <div className="mt-4 hidden px-1 lg:block">
            <ContactOptions
              title={t('contact.beforePaying')}
              prefill={t('contact.prefillGeneral')}
              showHours={false}
              className="text-center [&>div]:justify-center"
            />
          </div>
        </aside>
      </div>
    </div>
    </div>
  )
}
