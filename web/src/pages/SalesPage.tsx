import { lazy, Suspense, useCallback, useEffect, useInsertionEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { phaseModules } from '@/lib/modules'
import { isAxiosError } from 'axios'
import { useUser } from '@/api/auth'
import { useCourse } from '@/api/courses'
import { CampaignBanner } from '@/components/CampaignBanner'
import type { HeroCta } from '@/components/landing/kit/cta'
import { Intro } from '@/components/landing/intro/Intro'
import { rememberIntro, shouldPlayIntro } from '@/components/landing/intro/introGate'
import { BarreAchat } from '@/components/landing/kit/BarreAchat'
import { Sommaire } from '@/components/landing/kit/Sommaire'
import { Presentation } from '@/components/landing/s1/Presentation'
import { Parcours } from '@/components/landing/s2/Parcours'
import { Methode } from '@/components/landing/s3/Methode'
import { Aube } from '@/components/landing/kit/Passages'
import { Circuit } from '@/components/landing/s4/Circuit'
import { Journal } from '@/components/landing/s5/Journal'
import { Outils } from '@/components/landing/s6/Outils'
import { Button } from '@/components/ui/button'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useSmoothWheel } from '@/hooks/useSmoothWheel'
import { MAIN_COURSE_SLUG } from '@/lib/course'
import { withoutDemo } from '@/lib/demo'
import { setHeaderTone, type HeaderTone } from '@/lib/headerTone'

// Everything under the first two screens arrives in its own chunk, while the
// visitor reads the hero: a phone on a slow connection gets the pitch, the
// price and the button first.
const BelowTheFold = lazy(() => import('@/pages/SalesPageSections').then((m) => ({ default: m.SalesPageSections })))

/** The page opens on paper: the phone's status bar takes its colour. */
const PAPER = '#F6F2EA'

/** Sets a <meta> for as long as the page is mounted, then puts back what was there. */
function useMeta(name: string, content: (previous: string) => string) {
  useEffect(() => {
    const meta = document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)
    if (!meta) return
    const previous = meta.content
    meta.content = content(previous)
    return () => {
      meta.content = previous
    }
    // `content` is a constant of the call site.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [name])
}

/**
 * The sales page, redesigned (docs/redesign-plan.md): eight sections in three
 * acts, from the night of the drawing to the paper of the building site and
 * back to night for the handover.
 *
 * Every figure, price and review on it comes from the course or from what the
 * instructor supplied (constitution, I). It is complete with no review, no
 * promotion and no student count: that is its state on launch day.
 */
export function SalesPage() {
  const params = useParams<{ slug: string }>()
  const slug = params.slug ?? MAIN_COURSE_SLUG
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: loaded, isError, error } = useCourse(slug)
  // No demo promotion on this page: the real price, no countdown (lib/demo).
  const course = useMemo(() => withoutDemo(loaded), [loaded])
  const isMain = slug === MAIN_COURSE_SLUG
  // The words the server puts in this page's head (api SeoPageController).
  usePageTitle(
    isMain ? t('seo.home.title') : course?.title,
    isMain ? (course ? t('seo.home.description', { modules: phaseModules(course.modules).length }) : undefined) : course?.description,
  )

  const checkoutPath = `/checkout/${slug}`
  const purchaseLink = useMemo(
    () =>
      (target: string): { to: string; state?: { from: string } } =>
        user ? { to: target } : { to: `/register?redirect=${encodeURIComponent(target)}`, state: { from: target } },
    [user],
  )
  const cta = useMemo<HeroCta>(
    () => (course?.is_enrolled && course.full_access ? { to: `/learn/${slug}`, label: 'course.accessCta' } : { ...purchaseLink(checkoutPath), label: 'landing.heroV2.cta' }),
    [course?.is_enrolled, course?.full_access, slug, purchaseLink, checkoutPath],
  )

  // The page's selection colour and tap highlight (index.css, landing-type).
  useInsertionEffect(() => {
    document.documentElement.classList.add('landing-type')
    return () => document.documentElement.classList.remove('landing-type')
  }, [])
  // The phone's status bar matches the top of the page, and the buy bar may
  // use the safe area under the home indicator.
  useMeta('theme-color', () => PAPER)
  useMeta('viewport', (previous) => (previous.includes('viewport-fit') ? previous : `${previous}, viewport-fit=cover`))

  // S0, the cinematic entrance: only on a first visit to the front door
  // (introGate). The page is in the document under it from the start.
  const [intro, setIntro] = useState(() => !params.slug && shouldPlayIntro())
  useEffect(() => {
    if (intro) rememberIntro()
  }, [intro])
  const endIntro = useCallback(() => {
    window.scrollTo(0, 0)
    setIntro(false)
  }, [])
  useSmoothWheel(!intro)

  // The header turns to night over the night sections (data-night or
  // data-tone="night"), clear over cream paper (data-tone="paper"), and back. Sections arrive late (lazy): the list is re-read
  // whenever the page's content changes.
  useEffect(() => {
    const under = new Set<Element>()
    const decide = () => {
      let tone: HeaderTone = 'default'
      under.forEach((node) => {
        const data = (node as HTMLElement).dataset
        if (data.tone === 'night' || data.night !== undefined) tone = 'night'
        else if (data.tone === 'paper' && tone === 'default') tone = 'paper'
      })
      setHeaderTone(tone)
    }
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? under.add(entry.target) : under.delete(entry.target)))
        decide()
      },
      // Only the strip the header covers counts.
      { rootMargin: '0px 0px -92% 0px' },
    )
    const watch = () => document.querySelectorAll('[data-night], [data-tone]').forEach((node) => observer.observe(node))
    watch()
    const root = document.getElementById('sales-page')
    const mutations = new MutationObserver(watch)
    if (root) mutations.observe(root, { childList: true, subtree: true })
    // A section whose ground changes under the header (S1 turns to night) says so.
    root?.addEventListener('tonechange', decide)
    return () => {
      root?.removeEventListener('tonechange', decide)
      mutations.disconnect()
      observer.disconnect()
      setHeaderTone('default')
    }
  }, [])

  if (isError) {
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

  // Someone who owns everything is shown no promotion: there is nothing left to buy.
  const campaign = course && !course.full_access ? course.campaign : null

  return (
    <div id="sales-page">
      {intro && <Intro onDone={endIntro} />}
      <div aria-hidden className="grain" />
      <BarreAchat course={course} cta={cta} held={intro} />
      <Sommaire held={intro} />
      {/* A real, server-set deadline: hides itself and refetches when it ends. */}
      <CampaignBanner campaign={campaign} slug={slug} className="relative z-20" />
      <Presentation course={course} cta={cta} awake={!intro} flush={!campaign} />
      {/* Below: the sections of the earlier version, rebuilt one by one after S1. */}
      <Parcours course={course} />
      <Methode />
      <Aube />
      <Circuit />
      <Journal />
      <Outils />
      {course && (
        <Suspense fallback={<div aria-busy="true" className="min-h-svh bg-papier-100" />}>
          <BelowTheFold course={course} slug={slug} cta={cta} purchaseLink={purchaseLink} checkoutPath={checkoutPath} />
        </Suspense>
      )}
    </div>
  )
}
