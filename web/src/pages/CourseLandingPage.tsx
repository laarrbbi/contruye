import { useCallback, useMemo, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { isAxiosError } from 'axios'
import {
  Calculator,
  MapPin,
  ArrowDown,
  Boxes,
  MessageCircle,
  CircleCheck,
  Clock,
  HardHat,
  Infinity as InfinityIcon,
  LandPlot,
  ListChecks,
  Lock,
  Percent,
  Plane,
  PlayCircle,
  Scale,
} from 'lucide-react'
import { useCourse, type PublicLesson } from '@/api/courses'
import { useUser } from '@/api/auth'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { VideoPlayer } from '@/components/player/VideoPlayer'
import { CampaignBanner } from '@/components/CampaignBanner'
import { BlueprintBackdrop } from '@/components/BlueprintBackdrop'
import { ReviewsSection } from '@/components/reviews/ReviewsSection'
import { StarRating } from '@/components/reviews/StarRating'
import { ScrollReveal } from '@/components/ScrollReveal'
import { FaqChat } from '@/components/ui/faq-chat'
import faqContent from '@/content/faq.json'
import { ContactOptions } from '@/components/ContactOptions'
import { IsolatedValue } from '@/components/IsolatedValue'
import { hasContactChannel, useBusinessProfile } from '@/api/business'
import { phaseModules } from '@/lib/modules'
import { cn, formatDuration, formatNumber, formatPrice } from '@/lib/utils'
import { TRANSFER_METHODS, paymentMethodList } from '@/lib/payments'

// The sales page makes no claim about the instructor's own track record:
// none has been supplied yet, and nothing on a public surface is invented
// (constitution I). Each band below says what the course and the platform
// actually deliver.

const COST_OF_ERROR = ['e1', 'e2', 'e3', 'e4'] as const
/** The instructor's twelve phases, in order (landing.method.s1 … s12). */
const METHOD_STEPS = ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10', 's11', 's12'] as const
const MODULAR_CASES = ['c1', 'c2', 'c3', 'c4'] as const

/** Who the course is for: four starting points. */
const FOR_WHOM = [
  { key: 'p1', icon: LandPlot },
  { key: 'p2', icon: HardHat },
  { key: 'p3', icon: Plane },
  { key: 'p4', icon: Boxes },
] as const

/** What the platform delivers today — each item is a feature that exists. */
const TOOLKIT = [
  { icon: ListChecks, titleKey: 'landing.toolkit.checklistsTitle', bodyKey: 'landing.toolkit.checklistsBody' },
  { icon: Scale, titleKey: 'landing.toolkit.legalTitle', bodyKey: 'landing.toolkit.legalBody' },
  { icon: MapPin, titleKey: 'landing.toolkit.partnersTitle', bodyKey: 'landing.toolkit.partnersBody' },
  { icon: Calculator, titleKey: 'landing.toolkit.sheetsTitle', bodyKey: 'landing.toolkit.sheetsBody' },
] as const

// Shared with the server, which renders the same questions in the page's
// structured data (api SeoPageController).
const FAQ_KEYS = faqContent.keys

/** Section heading shared by every sales block. */
function SectionTitle({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <h2 className={cn('text-2xl font-extrabold leading-tight text-primary sm:text-3xl', className)}>
      {children}
    </h2>
  )
}

/**
 * The opening of a section: what it is in two or three words, its title and
 * its lead. The short accent rule is the one place the brand's amber shows
 * on every band.
 */
function SectionHeader({
  eyebrow,
  title,
  lead,
  onDark = false,
  className,
}: {
  eyebrow?: string
  title: string
  lead?: string
  onDark?: boolean
  className?: string
}) {
  return (
    <div className={cn('max-w-2xl', className)}>
      {eyebrow && (
        <p
          className={cn(
            'mb-3 flex items-center gap-2.5 text-xs font-semibold uppercase tracking-[0.18em] rtl:tracking-normal',
            onDark ? 'text-primary-foreground/75' : 'text-primary/80',
          )}
        >
          <span aria-hidden className="h-0.5 w-6 shrink-0 rounded-full bg-accent" />
          {eyebrow}
        </p>
      )}
      <SectionTitle className={onDark ? 'text-primary-foreground' : undefined}>{title}</SectionTitle>
      {lead && (
        <p className={cn('mt-4 leading-relaxed', onDark ? 'text-primary-foreground/75' : 'text-muted-foreground')}>
          {lead}
        </p>
      )}
    </div>
  )
}

/**
 * One band of the sales page. Every band has the header's width and left
 * edge, so the eye runs down a single line from the logo to the footer. The
 * light bands alternate white and grey (ALTERNATING) instead of a border
 * between them.
 *
 * `texture`: a faint drafting grid, the trade's own paper, fading out from
 * the top. Decorative only.
 */
function Band({
  id,
  tone = 'plain',
  texture = false,
  className,
  children,
}: {
  id?: string
  tone?: 'plain' | 'dark'
  texture?: boolean
  className?: string
  children: React.ReactNode
}) {
  return (
    <section
      id={id}
      // An anchor lands below the sticky header (h-16), not under it.
      className={cn(
        'scroll-mt-16',
        tone === 'dark' && 'bg-primary text-primary-foreground',
        texture && 'relative isolate overflow-hidden',
      )}
    >
      {texture && (
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(to_right,rgb(255_255_255/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.06)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_90%_80%_at_50%_0%,black,transparent)]"
        />
      )}
      <div className={cn('container py-12 sm:py-16 lg:py-20', className)}>{children}</div>
    </section>
  )
}

/**
 * The light bands alternate white and grey. The rule sits on their wrapper,
 * not on each band: a band that does not render (the modular offer for an
 * owner of everything, the reviews before the first one) would otherwise
 * leave two greys or two whites side by side.
 */
const ALTERNATING = '[&>section:nth-child(odd)]:bg-secondary/40'

/** Desktop split used by most bands: the header beside its content. */
const SPLIT = 'grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-14'

/** The single course this academy sells — used when the landing is served at "/". */
export const MAIN_COURSE_SLUG = 'promocion-inmobiliaria-espana'

/** A full-width CTA whose label may wrap on a narrow phone instead of overflowing. */
const WRAPPING_CTA = 'h-auto min-h-11 w-full whitespace-normal py-2.5 text-center leading-snug'

export function CourseLandingPage() {
  const params = useParams<{ slug: string }>()
  const slug = params.slug ?? MAIN_COURSE_SLUG
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const { data: course, isLoading, isError, error } = useCourse(slug)
  // The words the server puts in this page's head (api SeoPageController):
  // the home page's own, or the course's for any other course.
  const isMain = slug === MAIN_COURSE_SLUG
  usePageTitle(
    isMain ? t('seo.home.title') : course?.title,
    // The description names the module count: set once it is known.
    isMain ? (course ? t('seo.home.description', { modules: phaseModules(course.modules).length }) : undefined) : course?.description,
  )
  const { data: business } = useBusinessProfile()
  const canBeReached = hasContactChannel(business)

  const [previewLesson, setPreviewLesson] = useState<PublicLesson | null>(null)
  const [showStickyBar, setShowStickyBar] = useState(false)

  // The phone's buy bar takes over once the hero's price card has scrolled
  // out above: never two prices and two buttons on screen at once.
  const priceCardObserver = useRef<IntersectionObserver | null>(null)
  const priceCardRef = useCallback((card: HTMLDivElement | null) => {
    priceCardObserver.current?.disconnect()
    if (!card) return
    priceCardObserver.current = new IntersectionObserver((entries) => {
      // A batch can hold several changes of the one card: the last is current.
      const entry = entries[entries.length - 1]
      setShowStickyBar(!entry.isIntersecting && entry.boundingClientRect.top < 0)
    })
    priceCardObserver.current.observe(card)
  }, [])

  const checkoutPath = `/checkout/${slug}`
  const cta = useMemo(() => {
    if (course?.is_enrolled) {
      return { to: `/learn/${slug}`, state: undefined, label: 'course.accessCta' }
    }
    if (user) {
      return { to: checkoutPath, state: undefined, label: 'landing.hero.ctaPrimary' }
    }
    return {
      to: `/register?redirect=${encodeURIComponent(checkoutPath)}`,
      state: { from: checkoutPath },
      label: 'landing.hero.ctaPrimary',
    }
  }, [course?.is_enrolled, user, slug, checkoutPath])

  // Same guest-vs-authenticated routing as `cta`, for arbitrary targets.
  const purchaseLink = (target: string): { to: string; state?: { from: string } } =>
    user
      ? { to: target }
      : { to: `/register?redirect=${encodeURIComponent(target)}`, state: { from: target } }

  if (isLoading) {
    // The hero's colour and shape while the course loads. In production the
    // server-drawn hero comes first, then this, then the real hero: one navy
    // band throughout, instead of a white "loading" line in between. And it
    // fills the screen, so the footer stays below the fold instead of
    // jumping away when the page arrives.
    return (
      <section aria-busy="true" className="min-h-[calc(100svh-4rem)] bg-primary">
        <div className="container py-12 sm:py-16">
          <p className="sr-only">{t('common.loading')}</p>
          <div aria-hidden className="max-w-2xl space-y-6 motion-safe:animate-pulse">
            <div className="h-6 w-56 rounded-full bg-primary-foreground/10" />
            <div className="space-y-3">
              <div className="h-9 rounded-lg bg-primary-foreground/10" />
              <div className="h-9 w-11/12 rounded-lg bg-primary-foreground/10" />
              <div className="h-9 w-2/3 rounded-lg bg-primary-foreground/10" />
            </div>
            <div className="space-y-2">
              <div className="h-4 rounded bg-primary-foreground/10" />
              <div className="h-4 w-5/6 rounded bg-primary-foreground/10" />
            </div>
          </div>
        </div>
      </section>
    )
  }

  if (isError || !course) {
    const notFound = isAxiosError(error) && error.response?.status === 404
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <p className="text-muted-foreground">{notFound ? t('course.notFound') : t('common.error')}</p>
        <Button asChild variant="outline">
          <Link to="/">{t('common.back')}</Link>
        </Button>
      </div>
    )
  }

  const price = formatPrice(course.price_eur, 'EUR', i18n.language)

  // Someone who owns SOME modules can still buy the rest — only full access
  // removes every purchase surface. (is_enrolled is true for partial owners.)
  const canStillBuy = !course.full_access
  const notEnrolled = canStillBuy
  const sellableModules = course.modules.filter((m) => m.price_eur != null)
  const moduleAnchor = sellableModules.reduce((sum, m) => sum + (m.price_eur ?? 0), 0)
  const fullSavingsPct =
    moduleAnchor > course.price_eur ? Math.round((1 - course.price_eur / moduleAnchor) * 100) : 0
  const cheapestModule = sellableModules.length
    ? Math.min(...sellableModules.map((m) => m.price_eur as number))
    : null
  const tiers = [...course.module_discount_tiers].sort((a, b) => a.min_modules - b.min_modules)
  const showAlaCarte = canStillBuy && cheapestModule != null
  const modulesLink = purchaseLink(`${checkoutPath}?scope=modules`)

  // Promotion currently running (hidden once the student owns everything).
  const campaign = canStillBuy ? course.campaign : null
  const campaignPrice = campaign
    ? Math.round((course.price_eur * (100 - campaign.percent_off)) / 100)
    : null

  const effectivePrice = campaignPrice ?? course.price_eur
  const hasSocialProof = course.rating != null || (course.students_count ?? 0) > 0
  // Counted, never typed: the copy follows the catalogue when it changes.
  // The 12 phases are the modules; Financials comes on top of them.
  const phaseCount = phaseModules(course.modules).length
  const modulesLabel = t(
    course.modules.length > phaseCount ? 'landing.counts.modulesWithFinancials' : 'landing.counts.modules',
    { count: phaseCount },
  )
  const lessonsLabel = t('course.lessonsCount', { count: course.lessons_count })
  const previewCount = course.modules.reduce(
    (sum, module) => sum + module.lessons.filter((lesson) => lesson.is_free_preview).length,
    0,
  )
  // The complete offer planned for launch, even while a gateway still waits
  // for its credentials (constitution I, amendment inherited from Ebni). The
  // checkout offers only what can take money.
  const paymentNames = paymentMethodList(t)
  const paymentShort = paymentMethodList(t, { set: 'short' })
  const paymentSentence = t('payments.sentence', { methods: paymentNames })
  const transferNames = paymentMethodList(t, { only: TRANSFER_METHODS })
  // An empty reviews band tells a visitor nobody bought. A buyer still sees
  // it, with the invitation to write the first review.
  const showReviews = (course.reviews_count ?? 0) > 0 || course.is_enrolled
  const bestTier = tiers.length > 0 ? tiers[tiers.length - 1] : null

  const ctaButton = (size: 'default' | 'lg' = 'lg', className?: string) => (
    <Button asChild size={size} className={cn('font-semibold', className)}>
      <Link to={cta.to} state={cta.state}>
        {t(cta.label)}
      </Link>
    </Button>
  )

  const modulesButton = (className?: string) =>
    showAlaCarte ? (
      <Button asChild size="lg" variant="outline" className={cn('font-semibold', className)}>
        <Link to={modulesLink.to} state={modulesLink.state}>
          <Boxes className="me-2 h-4 w-4" />
          {t('course.pricing.pickModulesCta')}
        </Link>
      </Button>
    ) : null

  const curriculumButtons = (className: string) => (
    <div className={cn('flex flex-wrap gap-3', className)}>
      {ctaButton('lg', 'max-sm:w-full')}
      {modulesButton('max-sm:w-full')}
    </div>
  )

  return (
    <div>
      {/* Real, server-set deadline — hides itself and refetches when it ends */}
      <CampaignBanner campaign={campaign} slug={slug} />

      {/* ─── 1. HERO — the whole journey, from the plot to the keys ──── */}
      <section className="relative isolate overflow-hidden bg-primary text-primary-foreground">
        <BlueprintBackdrop />

        {/* Three grid items in reading order: the pitch, the price card, the
            proof. A phone reaches the price right after the pitch; on a wide
            screen the card stands beside both. */}
        <div className="container grid gap-x-10 gap-y-8 py-12 sm:py-16 lg:grid-cols-[1fr_380px] lg:items-start lg:gap-y-6">
          <div className="space-y-6 lg:col-start-1 lg:row-start-1">
            <p className="inline-flex rounded-full border border-primary-foreground/20 bg-primary-foreground/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
              {t('landing.hero.eyebrow')}
            </p>

            <h1 className="text-3xl font-extrabold leading-tight sm:text-4xl lg:text-[2.75rem]">
              {t('landing.hero.title')}
            </h1>

            <p className="max-w-2xl text-base leading-relaxed text-primary-foreground/90">
              {t('landing.hero.body')}
            </p>

            {course.modules.length > 0 && (
              <a
                href="#programme"
                className="group inline-flex items-center gap-2 text-sm font-semibold text-primary-foreground/90 underline-offset-4 transition-colors hover:text-primary-foreground hover:underline"
              >
                <ArrowDown
                  className="h-4 w-4 shrink-0 transition-transform duration-200 ease-out-strong group-hover:translate-y-0.5"
                  aria-hidden
                />
                {previewCount > 0
                  ? t('landing.hero.ctaSecondary', { count: previewCount, modules: modulesLabel })
                  : t('landing.hero.ctaSecondaryNoPreview', { modules: modulesLabel })}
              </a>
            )}
          </div>

          {/* Price card */}
          <Card
            ref={priceCardRef}
            className="overflow-hidden text-foreground shadow-2xl shadow-black/25 lg:col-start-2 lg:row-span-2 lg:row-start-1"
          >
            {course.cover_image && (
              <img src={course.cover_image} alt={course.title} className="aspect-video w-full object-cover" />
            )}
            <CardContent className="space-y-4 p-5">
              <div>
                {notEnrolled &&
                  (campaign ? (
                    <div className="mb-1 flex flex-wrap items-center gap-2">
                      <span className="text-lg font-medium text-muted-foreground line-through">
                        {price}
                      </span>
                      <Badge variant="success">
                        {t('course.pricing.saveBadge', { percent: campaign.percent_off })}
                      </Badge>
                    </div>
                  ) : fullSavingsPct > 0 ? (
                    <div className="mb-1 flex items-center gap-2">
                      <span className="text-lg font-medium text-muted-foreground line-through">
                        {formatPrice(moduleAnchor, 'EUR', i18n.language)}
                      </span>
                      <Badge variant="success">
                        {t('course.pricing.saveBadge', { percent: fullSavingsPct })}
                      </Badge>
                    </div>
                  ) : null)}
                <p className="text-3xl font-extrabold text-primary">
                  {campaignPrice != null ? formatPrice(campaignPrice, 'EUR', i18n.language) : price}
                </p>
                {campaign && (
                  <p className="bidi-auto mt-0.5 text-sm font-semibold text-primary">{campaign.label}</p>
                )}
                {notEnrolled && (
                  <p className="mt-0.5 text-sm text-muted-foreground">
                    {t('course.pricing.fullAccessNote')}
                  </p>
                )}
                {notEnrolled && (
                  <p className="mt-1 text-xs text-muted-foreground">{t('course.pricing.vatIncluded')}</p>
                )}
              </div>

              {ctaButton('lg', 'w-full')}

              {showAlaCarte && (
                <Link
                  to={modulesLink.to}
                  state={modulesLink.state}
                  className="flex items-center justify-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                  <Boxes className="h-4 w-4 shrink-0" />
                  {t('course.pricing.fromPerModule', {
                    price: formatPrice(cheapestModule, 'EUR', i18n.language),
                  })}
                </Link>
              )}

              <ul className="space-y-2 pt-1 text-sm text-muted-foreground">
                <li className="flex items-center gap-2">
                  <CircleCheck className="h-4 w-4 shrink-0 text-success" />
                  {t('course.bullets.lifetime')}
                </li>
                <li className="flex items-center gap-2">
                  <CircleCheck className="h-4 w-4 shrink-0 text-success" />
                  {paymentShort}
                </li>
                <li className="flex items-center gap-2">
                  <CircleCheck className="h-4 w-4 shrink-0 text-success" />
                  {t('course.bullets.devices')}
                </li>
              </ul>
            </CardContent>
          </Card>

          {/* The proof: what the programme holds, counted from the catalogue,
              and the rating once real reviews exist. A phone sees the ways to
              pay in the card above. */}
          <div className="space-y-6 lg:col-start-1 lg:row-start-2">
            <dl className="grid grid-cols-3 gap-x-6 gap-y-4 border-y border-primary-foreground/15 py-5">
              {[
                { key: 'phases', figure: formatNumber(phaseCount, i18n.language), label: t('landing.hero.statPhases') },
                { key: 'lessons', figure: formatNumber(course.lessons_count, i18n.language), label: t('course.stats.lessons') },
                { key: 'access', figure: <InfinityIcon className="h-7 w-7 sm:h-8 sm:w-8" aria-hidden />, label: t('course.lifetimeAccess') },
              ].map(({ key, figure, label }) => (
                <div key={key}>
                  <dt className="flex h-7 items-center text-2xl font-extrabold leading-none sm:h-8 sm:text-3xl">{figure}</dt>
                  <dd className="mt-1 text-xs leading-snug text-primary-foreground/70">{label}</dd>
                </div>
              ))}
            </dl>

            {/* Real social proof — each item renders only when the number is real. */}
            {hasSocialProof && (
              <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                {course.rating != null && (
                  <span className="flex items-center gap-2">
                    <StarRating value={course.rating} size="sm" />
                    <span className="text-sm font-semibold">{course.rating.toFixed(1)}</span>
                    <span className="text-sm text-primary-foreground/70">
                      {t('reviews.count', { count: course.reviews_count ?? 0 })}
                    </span>
                  </span>
                )}
                {(course.students_count ?? 0) > 0 && (
                  <span className="text-sm text-primary-foreground/70">
                    {t('reviews.studentsEnrolled', { count: course.students_count ?? 0 })}
                  </span>
                )}
              </div>
            )}

            <p className="hidden max-w-2xl text-xs leading-relaxed text-primary-foreground/60 sm:block">
              {`${paymentSentence} ${t('landing.hero.paymentNote')}`}
            </p>
          </div>
        </div>
      </section>

      <div className={ALTERNATING}>
        {/* ─── 3b. FOR WHOM — is this for me? ───────────────────────────── */}
        <Band className="space-y-10">
          <SectionHeader
            eyebrow={t('landing.eyebrow.forWhom')}
            title={t('landing.forWhom.title')}
            lead={t('landing.forWhom.lead')}
          />
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {FOR_WHOM.map(({ key, icon: Icon }, index) => (
              <ScrollReveal
                key={key}
                as="li"
                delay={index * 50}
                className="flex h-full flex-col gap-3 rounded-2xl border bg-background p-5"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                  <Icon className="h-5 w-5 text-primary" />
                </span>
                <h3 className="font-semibold leading-snug">{t(`landing.forWhom.${key}Title`)}</h3>
                <p className="text-sm leading-relaxed text-muted-foreground">{t(`landing.forWhom.${key}Body`)}</p>
              </ScrollReveal>
            ))}
          </ul>
        </Band>

        {/* ─── 4. THE MAZE — name the real blocker ──────────────────────── */}
        <Band className={cn(SPLIT, 'lg:items-start')}>
          <ScrollReveal>
            <SectionHeader eyebrow={t('landing.eyebrow.maze')} title={t('landing.maze.title')} lead={t('landing.maze.lead')} />
          </ScrollReveal>

          <ScrollReveal delay={120}>
            <ul className="grid gap-2.5 sm:grid-cols-2">
              {(['b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7'] as const).map((key) => (
                <li
                  key={key}
                  // Seven hurdles: the last one spans the row instead of
                  // sitting alone in it.
                  className="flex items-center gap-3 rounded-xl border bg-background px-3.5 py-3 text-sm font-medium sm:last:col-span-2"
                >
                  <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/[0.07]">
                    <Lock className="h-3.5 w-3.5 text-primary/70" />
                  </span>
                  {t(`landing.maze.${key}`)}
                </li>
              ))}
            </ul>
            <p className="mt-6 leading-relaxed">{t('landing.maze.close')}</p>
          </ScrollReveal>
        </Band>

        {/* ─── 5. COST OF ERROR — reframe the price as insurance ────────── */}
        <Band className="space-y-10">
          <SectionHeader eyebrow={t('landing.eyebrow.costOfError')} title={t('landing.costOfError.title')} />

          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {COST_OF_ERROR.map((key, index) => (
              <ScrollReveal
                key={key}
                as="li"
                delay={index * 50}
                // The icon beside the text on a phone, above it in the grid.
                className="flex h-full items-start gap-4 rounded-2xl border bg-background p-5 shadow-sm sm:flex-col"
              >
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-destructive/10">
                  <Clock className="h-4 w-4 text-destructive" />
                </span>
                <span className="text-sm leading-relaxed">{t(`landing.costOfError.${key}`)}</span>
              </ScrollReveal>
            ))}
          </ul>

          <p className="max-w-3xl text-lg font-semibold leading-relaxed text-primary">{t('landing.costOfError.close')}</p>
        </Band>

        {/* ─── 6. METHOD — the real step by step ────────────────────────── */}
        <Band className="space-y-10">
          <SectionHeader eyebrow={t('landing.eyebrow.method')} title={t('landing.method.title')} lead={t('landing.method.intro')} />

          <ol className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
            {METHOD_STEPS.map((key, index) => (
              <ScrollReveal
                key={key}
                as="li"
                delay={index * 50}
                className="flex h-full items-start gap-4 rounded-2xl border bg-background p-4 sm:p-5"
              >
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold tabular-nums text-accent-foreground">
                  {index + 1}
                </span>
                <span className="pt-1.5 text-sm leading-relaxed">{t(`landing.method.${key}`)}</span>
              </ScrollReveal>
            ))}
          </ol>

          <p className="max-w-3xl font-semibold leading-relaxed">{t('landing.method.close')}</p>
        </Band>

        {/* ─── 8. TOOLKIT — what you actually walk away with ────────────── */}
        <Band className="space-y-10">
          <div className={cn(SPLIT, 'lg:items-start')}>
            <ScrollReveal>
              <SectionHeader
                eyebrow={t('landing.eyebrow.toolkit')}
                title={t('landing.toolkit.title')}
                lead={t('landing.toolkit.footer')}
              />
            </ScrollReveal>

            <div className="grid gap-4 sm:grid-cols-2">
              {TOOLKIT.map((item, index) => {
                const Icon = item.icon
                return (
                  <ScrollReveal
                    key={item.titleKey}
                    delay={index * 50}
                    className="h-full space-y-3 rounded-2xl border bg-background p-5"
                  >
                    <div className="flex items-center gap-3">
                      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                        <Icon className="h-5 w-5 text-primary" />
                      </span>
                      <h3 className="font-semibold leading-snug">{t(item.titleKey)}</h3>
                    </div>
                    <p className="text-sm leading-relaxed text-muted-foreground">{t(item.bodyKey)}</p>
                  </ScrollReveal>
                )
              })}
            </div>
          </div>

        </Band>

        {/* ─── 9. CURRICULUM — depth is the proof ───────────────────────── */}
        {/* On a wide screen the header and its buttons stay in view beside the
            long list. On a phone the buttons come after the list, once the
            modules have been seen. */}
        <Band id="programme" className={cn(SPLIT, 'lg:items-start')}>
          <div className="space-y-8 lg:sticky lg:top-24">
            <SectionHeader
              eyebrow={t('landing.eyebrow.curriculum')}
              title={t('landing.curriculumIntro.title', { modules: modulesLabel, lessons: lessonsLabel })}
              lead={
                previewCount > 0
                  ? t('landing.curriculumIntro.leadPreview', { count: previewCount })
                  : t('landing.curriculumIntro.lead')
              }
            />
            {curriculumButtons('hidden lg:flex')}
          </div>

          <div className="min-w-0">
            {course.modules.length === 0 ? (
              <p className="text-muted-foreground">{t('course.emptyProgram')}</p>
            ) : (
              <Accordion
                type="multiple"
                defaultValue={course.modules.length > 0 ? [`module-${course.modules[0].id}`] : []}
                className="rounded-2xl border bg-background px-4 shadow-sm"
              >
                {course.modules.map((module, index) => (
                  <AccordionItem key={module.id} value={`module-${module.id}`} className="last:border-b-0">
                    <AccordionTrigger>
                      {/* The title on its own line, then the same line under
                          every title: the lesson count, the price at the end. */}
                      <span className="flex min-w-0 flex-1 flex-col gap-1 pe-2">
                        <span className="font-semibold">
                          {module.kind === 'financials' ? (
                            <bdi>{module.title}</bdi>
                          ) : (
                            <IsolatedValue i18nKey="course.moduleHeading" name="title" value={module.title} values={{ number: index + 1 }} />
                          )}
                        </span>
                        <span className="flex items-baseline justify-between gap-3 text-xs font-normal text-muted-foreground">
                          <span>{t('course.lessonsCount', { count: module.lessons.length })}</span>
                          {notEnrolled && (
                            <span className="shrink-0 font-semibold tabular-nums text-primary">
                              {module.price_eur != null
                                ? formatPrice(module.price_eur, 'EUR', i18n.language)
                                : t('checkout.modules.fullOnly')}
                            </span>
                          )}
                        </span>
                      </span>
                    </AccordionTrigger>
                    <AccordionContent className="pb-2">
                      <ul className="divide-y">
                        {module.lessons.map((lesson) => (
                          <li key={lesson.id} className="flex items-center gap-3 py-2.5">
                            {lesson.is_free_preview ? (
                              <PlayCircle className="h-4 w-4 shrink-0 text-primary" />
                            ) : (
                              <Lock className="h-4 w-4 shrink-0 text-muted-foreground/60" />
                            )}
                            <span className="bidi-auto flex-1 leading-snug">{lesson.title}</span>
                            {lesson.is_free_preview && (
                              <Button
                                variant="outline"
                                size="sm"
                                className="h-7 gap-1.5 px-2.5 text-xs text-primary"
                                onClick={() => setPreviewLesson(lesson)}
                              >
                                <PlayCircle className="h-3.5 w-3.5" />
                                {t('course.watchPreview')}
                              </Button>
                            )}
                            <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                              {formatDuration(lesson.duration_seconds, t)}
                            </span>
                          </li>
                        ))}
                      </ul>
                    </AccordionContent>
                  </AccordionItem>
                ))}
              </Accordion>
            )}
            {curriculumButtons('mt-8 lg:hidden')}
          </div>
        </Band>

        {/* ─── 10. MODULAR OFFER — buy the stage you're stuck on ────────── */}
        {showAlaCarte && (
          <Band className={cn(SPLIT, 'lg:items-start')}>
            <ScrollReveal className="space-y-6">
              <SectionHeader eyebrow={t('landing.eyebrow.modular')} title={t('landing.modular.title')} lead={t('landing.modular.p1')} />
              <p className="text-2xl font-extrabold text-primary">
                {t('landing.modular.from', {
                  price: formatPrice(cheapestModule, 'EUR', i18n.language),
                })}
              </p>
              {/* "Discounts apply on their own; past a few modules, full access
                  is cheaper": said only while both are true. */}
              {tiers.length > 0 && fullSavingsPct > 0 && (
                <p className="leading-relaxed">{t('landing.modular.close')}</p>
              )}
              <div className="flex flex-wrap gap-3">{modulesButton('max-sm:w-full')}</div>
            </ScrollReveal>

            <div className="min-w-0 space-y-4">
              <ul className="grid gap-3 sm:grid-cols-2">
                {MODULAR_CASES.map((key) => (
                  <li key={key} className="flex gap-3 rounded-2xl border bg-background p-4">
                    <Boxes className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <span className="text-sm leading-relaxed">{t(`landing.modular.${key}`)}</span>
                  </li>
                ))}
              </ul>

              {/* The real tier ladder, straight from the course configuration */}
              {tiers.length > 0 && (
                <div className="space-y-3 rounded-2xl border bg-background p-4">
                  <p className="flex items-center gap-2 text-sm font-semibold">
                    <Percent className="h-4 w-4 text-primary" />
                    {t('checkout.modules.tierTitle')}
                  </p>
                  <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {tiers.map((tier) => (
                      <div key={tier.min_modules} className="rounded-xl bg-secondary/60 px-2 py-3 text-center">
                        <p className="text-lg font-extrabold leading-tight text-primary">
                          {t('checkout.modules.tierPercent', { percent: tier.percent_off })}
                        </p>
                        <p className="text-[11px] leading-tight text-muted-foreground">
                          {t('checkout.modules.tierMin', { min: tier.min_modules })}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </Band>
        )}

        {/* ─── 11. SOCIAL PROOF — real verified buyers only ─────────────── */}
        {showReviews && (
          <Band className={cn(SPLIT, 'lg:items-start')}>
            <SectionHeader
              eyebrow={t('landing.eyebrow.reviews')}
              title={t('landing.socialProof.title')}
              lead={t('landing.socialProof.note')}
            />
            <div className="min-w-0">
              <ReviewsSection slug={slug} embedded />
            </div>
          </Band>
        )}

        {/* ─── 12. PRICING — the two ways in ────────────────────────────── */}
        <Band className="grid gap-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:items-start lg:gap-14">
          <div className="space-y-4">
            <SectionHeader eyebrow={t('landing.eyebrow.pricing')} title={t('landing.pricing.title')} />
            <p className="text-sm leading-relaxed text-muted-foreground">
              {`${paymentSentence} ${t('landing.pricing.payment')}`}
            </p>
            {campaign && (
              <p className="text-sm leading-relaxed text-muted-foreground">{t('landing.pricing.promoNote')}</p>
            )}
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            {/* Full access — the primary offer */}
            {/* min-w-0 and wrapping CTAs: a grid item defaults to its content's
                width, and a nowrap button label pushed both cards past a
                390px screen. */}
            <div className="flex min-w-0 flex-col rounded-2xl border-2 border-primary bg-background p-6 shadow-lg">
              <h3 className="text-lg font-bold">{t('landing.pricing.fullTitle')}</h3>
              <div className="mt-2 flex flex-wrap items-baseline gap-2">
                {campaignPrice != null && (
                  <span className="text-base font-medium text-muted-foreground line-through">{price}</span>
                )}
                <span className="text-3xl font-extrabold text-primary">
                  {formatPrice(effectivePrice, 'EUR', i18n.language)}
                </span>
              </div>
              <p className="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground">
                {t('landing.pricing.fullBody', { modules: phaseCount, lessons: course.lessons_count })}
              </p>
              <div className="mt-5">{ctaButton('lg', WRAPPING_CTA)}</div>
            </div>

            {/* Per module — the low-friction entry */}
            <div className="flex min-w-0 flex-col rounded-2xl border bg-background p-6">
              <h3 className="text-lg font-bold">{t('landing.pricing.unitTitle')}</h3>
              <div className="mt-2 flex flex-wrap items-baseline gap-2">
                <span className="text-3xl font-extrabold">
                  {cheapestModule != null
                    ? formatPrice(cheapestModule, 'EUR', i18n.language)
                    : formatPrice(course.price_eur, 'EUR', i18n.language)}
                </span>
                {/* The best volume discount, not a discount on this price. */}
                {bestTier && (
                  <Badge variant="secondary">
                    {t('landing.pricing.upTo', { percent: bestTier.percent_off })}
                  </Badge>
                )}
              </div>
              <p className="mt-3 flex-1 text-sm leading-relaxed text-muted-foreground">
                {[
                  t('landing.pricing.unitBody'),
                  tiers.length > 0 && t('landing.pricing.unitTiers', { count: tiers[0].min_modules }),
                ]
                  .filter(Boolean)
                  .join(' ')}
              </p>
              <div className="mt-5">{modulesButton(WRAPPING_CTA)}</div>
            </div>
          </div>
        </Band>

        {/* ─── 12b. HOW IT WORKS — from payment to the first lesson ─────── */}
        {/* Card and transfer, as the offer is planned for launch: by card,
            access opens on the gateway's confirmation; by transfer, once the
            receipt is checked. */}
        {canStillBuy && (
          <Band className="space-y-10">
            <SectionHeader eyebrow={t('landing.eyebrow.howItWorks')} title={t('landing.howItWorks.title')} />
            <ol className="grid gap-4 lg:grid-cols-3">
              {[
                {
                  key: 's1',
                  lines: [
                    t(showAlaCarte ? 'landing.howItWorks.s1BodyModules' : 'landing.howItWorks.s1BodyFull'),
                    showAlaCarte && tiers.length > 0 && t('landing.howItWorks.s1Tiers'),
                  ],
                },
                {
                  key: 's2',
                  lines: [
                    t('landing.howItWorks.s2Card'),
                    t('landing.howItWorks.s2Transfer', { methods: transferNames }),
                    t('landing.howItWorks.s2Mail'),
                  ],
                },
                { key: 's3', lines: [t('landing.howItWorks.s3Body')] },
              ].map(({ key, lines }, index) => (
                <ScrollReveal
                  key={key}
                  as="li"
                  delay={index * 50}
                  className="flex h-full items-start gap-4 rounded-2xl border bg-background p-5"
                >
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold tabular-nums text-accent-foreground">
                    {index + 1}
                  </span>
                  <div className="space-y-1.5 pt-1">
                    <h3 className="font-semibold leading-snug">{t(`landing.howItWorks.${key}Title`)}</h3>
                    {lines.filter(Boolean).map((line) => (
                      <p key={String(line)} className="text-sm leading-relaxed text-muted-foreground">
                        {line}
                      </p>
                    ))}
                  </div>
                </ScrollReveal>
              ))}
            </ol>
          </Band>
        )}

        {/* ─── 13. FAQ ──────────────────────────────────────────────────── */}
        {/* Three grid items in reading order: the header, the questions, then
            "another question?". On a wide screen the last one moves under
            the header, beside the questions. */}
        <Band className="grid gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-x-14 lg:gap-y-8">
          <ScrollReveal className="lg:col-start-1 lg:row-start-1">
            <SectionHeader eyebrow={t('landing.eyebrow.faq')} title={t('course.faq.title')} lead={t('landing.faq.subtitle')} />
          </ScrollReveal>

          <ScrollReveal delay={120} className="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1">
            <FaqChat
              items={FAQ_KEYS
                // "¿Cómo contacto con el soporte?" promises phone and WhatsApp.
                // Until a channel is configured that promise is false, so the
                // question is not asked at all.
                .filter((key) => key !== 'support' || canBeReached)
                .map((key) => ({
                  id: key,
                  question: t(`course.faq.${key}.q`),
                  answer:
                    key === 'support' ? (
                      <div className="space-y-3">
                        <p>{t(`course.faq.${key}.a`)}</p>
                        <ContactOptions prefill={t('contact.prefillGeneral')} />
                      </div>
                    ) : key === 'payment' ? (
                      // Cash is taken by the owner in person: offered only when
                      // there is a way to reach him.
                      [t('course.faq.payment.a', { methods: paymentNames }), canBeReached && t('payments.cash')]
                        .filter(Boolean)
                        .join(' ')
                    ) : (
                      t(`course.faq.${key}.a`)
                    ),
                }))}
              questionClassName="text-base"
              answerClassName="text-sm leading-relaxed"
            />
          </ScrollReveal>

          <ScrollReveal delay={220} className="lg:col-start-1 lg:row-start-2 lg:self-start">
            <div className="overflow-hidden rounded-3xl bg-primary p-7 text-primary-foreground">
              <div className="flex flex-col items-start gap-4">
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-foreground/10">
                  <MessageCircle className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="mb-1 text-xl font-bold">{t('landing.faq.stillHaveQuestions')}</h3>
                  <p className="text-sm text-primary-foreground/70">
                    {previewCount > 0
                      ? t('landing.faq.expertHelpPreview', { count: previewCount })
                      : t('landing.faq.expertHelp')}
                  </p>
                </div>
                {canBeReached ? (
                  <>
                    <ContactOptions
                      tone="onPrimary"
                      prefill={t('contact.prefillGeneral')}
                      className="mt-1 flex flex-col items-start"
                    />
                    <Link
                      to={cta.to}
                      state={cta.state}
                      className="text-sm font-semibold text-primary-foreground/80 underline-offset-4 hover:underline"
                    >
                      {t(cta.label)}
                    </Link>
                  </>
                ) : (
                  <Button asChild variant="secondary" className="mt-2 rounded-full px-6 font-semibold">
                    <Link to={cta.to} state={cta.state}>{t(cta.label)}</Link>
                  </Button>
                )}
              </div>
            </div>
          </ScrollReveal>
        </Band>

      </div>

      {/* ─── 14. FINAL CTA ────────────────────────────────────────────── */}
      <Band tone="dark" texture className="grid gap-10 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-center lg:gap-14">
        <div className="space-y-5">
          <h2 className="text-2xl font-extrabold leading-tight sm:text-3xl">{t('landing.finalCta.title', { modules: modulesLabel })}</h2>
          <p className="leading-relaxed text-primary-foreground/80">{t('landing.finalCta.p1')}</p>
          <p className="leading-relaxed font-medium">{t('landing.finalCta.p2')}</p>

          <div className="flex flex-wrap gap-3 pt-2">
            {/* The page's last word gets its one amber button. */}
            <Button asChild size="lg" variant="accent" className="max-sm:w-full">
              <Link to={cta.to} state={cta.state}>
                {t(cta.label)}
              </Link>
            </Button>
            {showAlaCarte && (
              <Button
                asChild
                size="lg"
                variant="outline"
                className="border-primary-foreground/30 bg-transparent font-semibold text-primary-foreground hover:bg-primary-foreground/10 max-sm:w-full"
              >
                <Link to={modulesLink.to} state={modulesLink.state}>
                  <Boxes className="me-2 h-4 w-4" />
                  {t('course.pricing.pickModulesCta')}
                </Link>
              </Button>
            )}
          </div>

          {campaign && (
            <p className="text-xs text-primary-foreground/60">{t('landing.pricing.promoNote')}</p>
          )}
        </div>

        {/* The figure first, its caption under it. Lifetime access has no
            figure: its sign stands in, at the same height. A list, not a
            <dl>: a definition list may only hold dt/dd. */}
        <ul className="grid grid-cols-3 gap-4 border-t border-primary-foreground/15 pt-6 lg:grid-cols-1 lg:gap-6 lg:border-s lg:border-t-0 lg:ps-10 lg:pt-0">
          {[
            { key: 'lessons', figure: formatNumber(course.lessons_count, i18n.language), label: t('course.stats.lessons') },
            { key: 'phases', figure: formatNumber(phaseCount, i18n.language), label: t('landing.hero.statPhases') },
            { key: 'access', figure: <InfinityIcon className="h-8 w-8" aria-hidden />, label: t('course.lifetimeAccess') },
          ].map(({ key, figure, label }) => (
            <li key={key}>
              <span className="flex h-9 items-center text-3xl font-extrabold leading-none tabular-nums">{figure}</span>
              <span className="mt-1.5 block text-xs leading-snug text-primary-foreground/70">{label}</span>
            </li>
          ))}
        </ul>
      </Band>

      {/* Free-preview dialog */}
      <Dialog open={previewLesson !== null} onOpenChange={(open) => !open && setPreviewLesson(null)}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle className="bidi-auto pe-6">{previewLesson?.title}</DialogTitle>
            <DialogDescription>{t('course.freePreview')}</DialogDescription>
          </DialogHeader>
          {previewLesson && <VideoPlayer lessonId={previewLesson.id} />}
        </DialogContent>
      </Dialog>

      {/* Sticky mobile CTA bar. The layout reserves its height after the
          footer (data-bottom-bar). */}
      <div
        data-bottom-bar
        className={cn(
          'fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur transition-transform ease-out-strong md:hidden',
          // Rises on the strong curve; leaves faster than it came.
          showStickyBar ? 'translate-y-0 [transition-duration:250ms]' : 'translate-y-full duration-150',
        )}
      >
        <div className="container flex items-center justify-between gap-3 py-3">
          {/* The price is what this bar is for: it keeps its width and the
              button wraps instead of cutting the amount short. */}
          <div className="shrink-0">
            <p className="flex items-baseline gap-2 whitespace-nowrap text-lg font-extrabold text-primary">
              {campaignPrice != null && (
                <span className="text-sm font-medium text-muted-foreground line-through">{price}</span>
              )}
              {campaignPrice != null ? formatPrice(campaignPrice, 'EUR', i18n.language) : price}
            </p>
          </div>
          {ctaButton('default', 'h-auto min-h-10 min-w-0 flex-1 whitespace-normal py-2 text-center leading-snug')}
        </div>
      </div>
    </div>
  )
}
