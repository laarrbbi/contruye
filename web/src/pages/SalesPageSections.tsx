import { useTranslation } from 'react-i18next'
import type { CourseDetail } from '@/api/courses'
import type { HeroCta } from '@/components/landing/kit/cta'
import { Questions } from '@/components/landing/s8/Questions'
import { Cloture } from '@/components/landing/s9/Cloture'
import { Pied } from '@/components/landing/kit/Pied'
import { Aube } from '@/components/landing/kit/Passages'
import { Programme } from '@/components/landing/s7/Programme'
import { BANDE, H2, TEXTE } from '@/components/landing/kit/type'
import { useReviews } from '@/api/reviews'
import { ReviewsSection } from '@/components/reviews/ReviewsSection'
import { isDemo } from '@/lib/demo'
import { cn } from '@/lib/utils'

/**
 * The end of the sales page: the syllabus and its prices, the reviews once
 * there are real ones, the questions, and the handover. Its own chunk, since
 * it needs the course's modules and the configurator: see SalesPage.
 */
export function SalesPageSections({
  course,
  slug,
  cta,
  purchaseLink,
  checkoutPath,
}: {
  course: CourseDetail
  slug: string
  cta: HeroCta
  purchaseLink(target: string): { to: string; state?: { from: string } }
  checkoutPath: string
}) {
  const { t } = useTranslation()
  // An empty reviews band tells a visitor nobody bought, and a band of demo
  // reviews would lie to him: it shows once a real review exists. A buyer
  // still sees it, with the invitation to write the first one.
  const reviews = useReviews(slug)
  const hasRealReview = (reviews.data?.pages ?? []).some((page) => page.data.some((review) => !isDemo(review.body)))
  const showReviews = hasRealReview || course.is_enrolled

  return (
    <>
      <Aube />
      <Programme course={course} cta={cta} purchaseLink={purchaseLink} checkoutPath={checkoutPath} />
      {showReviews && (
        <section data-tone="paper" className="bg-creme-100 text-plume">
          <div className={cn(BANDE, 'grid gap-10 border-t border-plume/15 lg:grid-cols-12 lg:gap-14')}>
            <div className="lg:col-span-5">
              <h2 className={H2}>{t('landing.socialProof.title')}</h2>
              <p className={cn(TEXTE, 'mt-6 text-plume/85')}>{t('landing.socialProof.note')}</p>
            </div>
            <div className="min-w-0 lg:col-span-7">
              <ReviewsSection slug={slug} embedded />
            </div>
          </div>
        </section>
      )}
      <Questions />
      <Cloture course={course} cta={cta} />
      <Pied />
    </>
  )
}
