import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { BadgeCheck, MessageSquarePlus } from 'lucide-react'
import { useReviews, useSubmitReview } from '@/api/reviews'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { formatDate } from '@/pages/admin/lib'
import { StarPicker, StarRating } from './StarRating'
import { cn, intlLocale } from '@/lib/utils'

function initials(name: string | null): string {
  if (!name) return '?'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function ReviewFormDialog({
  slug,
  open,
  onOpenChange,
  initialRating,
  initialBody,
}: {
  slug: string | undefined
  open: boolean
  onOpenChange: (open: boolean) => void
  initialRating: number
  initialBody: string
}) {
  const { t } = useTranslation()
  const submit = useSubmitReview(slug)
  const [rating, setRating] = useState(initialRating)
  const [body, setBody] = useState(initialBody)
  const [error, setError] = useState<string | null>(null)

  // Re-seed from the student's existing review each time the dialog opens.
  useEffect(() => {
    if (open) {
      setRating(initialRating)
      setBody(initialBody)
      setError(null)
    }
  }, [open, initialRating, initialBody])

  const send = () => {
    if (rating < 1) {
      setError(t('reviews.form.ratingRequired'))
      return
    }
    submit.mutate(
      { rating, body: body.trim() || null },
      {
        onSuccess: () => onOpenChange(false),
        onError: () => setError(t('common.error')),
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !submit.isPending && onOpenChange(next)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('reviews.form.title')}</DialogTitle>
          <DialogDescription>{t('reviews.form.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>{t('reviews.form.rating')}</Label>
            <StarPicker
              value={rating}
              onChange={(value) => {
                setRating(value)
                setError(null)
              }}
              label={t('reviews.form.rating')}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="review-body">{t('reviews.form.body')}</Label>
            <Textarea
              id="review-body"
              rows={4}
              maxLength={2000}
              value={body}
              onChange={(event) => setBody(event.target.value)}
              placeholder={t('reviews.form.placeholder')}
            />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submit.isPending}>
            {t('common.cancel')}
          </Button>
          <Button onClick={send} disabled={submit.isPending}>
            {submit.isPending ? t('common.loading') : t('reviews.form.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/**
 * Public reviews for a course: real average, rating histogram and the approved
 * reviews themselves. When nothing is approved yet it says so plainly rather
 * than showing a placeholder score.
 *
 * `embedded`: inside a page section that already has its own title (the
 * sales page), without a second heading, frame or border.
 */
export function ReviewsSection({ slug, embedded = false }: { slug: string | undefined; embedded?: boolean }) {
  const { t, i18n } = useTranslation()
  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useReviews(slug)
  const [formOpen, setFormOpen] = useState(false)

  if (isLoading || !data) return null

  const meta = data.pages[0].meta
  const reviews = data.pages.flatMap((page) => page.data)
  const hasReviews = meta.count > 0

  const Wrapper = embedded ? 'div' : 'section'

  return (
    <Wrapper className={embedded ? undefined : 'border-t'}>
      <div className={embedded ? 'space-y-6' : 'container max-w-4xl space-y-6 py-10 sm:py-14'}>
        {/* Embedded, the row holds only the button: no button, no row (an
            empty row would still take its share of the spacing). */}
        {(!embedded || meta.can_review) && (
          <div className={cn('flex flex-wrap items-center gap-4', embedded ? 'justify-end' : 'justify-between')}>
            {!embedded && <h2 className="text-2xl font-bold">{t('reviews.title')}</h2>}
            {meta.can_review && (
              <Button variant="outline" onClick={() => setFormOpen(true)}>
                <MessageSquarePlus className="h-4 w-4" />
                {meta.my_review ? t('reviews.editMine') : t('reviews.leaveOne')}
              </Button>
            )}
          </div>
        )}

        {/* The viewer's own review, including while it waits for moderation. */}
        {meta.my_review && meta.my_review.status !== 'approved' && (
          <div className="rounded-lg border border-dashed bg-secondary/30 p-4">
            <div className="flex items-center gap-2">
              <StarRating value={meta.my_review.rating} size="sm" />
              <Badge variant="secondary">{t(`reviews.status.${meta.my_review.status}`)}</Badge>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">{t('reviews.pendingHint')}</p>
          </div>
        )}

        {!hasReviews ? (
          <div className="rounded-lg border border-dashed py-12 text-center">
            <p className="text-muted-foreground">{t('reviews.empty')}</p>
            {/* Not to someone whose own review is already waiting: "be the
                first" read as if theirs had been lost. */}
            {meta.can_review && !meta.my_review && (
              <Button variant="outline" className="mt-4" onClick={() => setFormOpen(true)}>
                {t('reviews.beFirst')}
              </Button>
            )}
          </div>
        ) : (
          <>
            {/* Summary: real average + histogram */}
            <div className="grid gap-6 rounded-lg border bg-secondary/30 p-5 sm:grid-cols-[auto_1fr] sm:gap-8">
              <div className="text-center sm:text-start">
                <p className="text-4xl font-extrabold leading-none text-primary">
                  {meta.average?.toLocaleString(intlLocale(i18n.language), {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                  })}
                </p>
                <StarRating value={meta.average ?? 0} className="mt-1.5" />
                <p className="mt-1 text-sm text-muted-foreground">
                  {t('reviews.count', { count: meta.count })}
                </p>
              </div>

              <div className="space-y-1.5 self-center">
                {[5, 4, 3, 2, 1].map((star) => {
                  const n = meta.distribution[String(star)] ?? 0
                  const pct = meta.count > 0 ? (n / meta.count) * 100 : 0
                  return (
                    <div key={star} className="flex items-center gap-2 text-xs">
                      <span className="w-3 shrink-0 tabular-nums text-muted-foreground">{star}</span>
                      <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                        <div className="h-full rounded-full bg-accent" style={{ width: `${pct}%` }} />
                      </div>
                      <span className="w-6 shrink-0 text-end tabular-nums text-muted-foreground">{n}</span>
                    </div>
                  )
                })}
              </div>
            </div>

            {/* Individual reviews */}
            <ul className="space-y-4">
              {reviews.map((review) => (
                <li key={review.id} className="rounded-lg border p-4">
                  <div className="flex items-start gap-3">
                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-bold text-primary">
                      {initials(review.author_name)}
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="font-medium">{review.author_name}</span>
                        {review.verified_purchase && (
                          <span className="inline-flex items-center gap-1 text-xs text-success">
                            <BadgeCheck className="h-3.5 w-3.5" />
                            {t('reviews.verified')}
                          </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                          {formatDate(review.created_at, i18n.language)}
                        </span>
                      </div>
                      <StarRating value={review.rating} size="sm" className="mt-1" />
                      {review.body && (
                        <p className="bidi-auto mt-2 whitespace-pre-line leading-relaxed">{review.body}</p>
                      )}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
            {hasNextPage && (
              <div className="text-center">
                <Button variant="outline" onClick={() => fetchNextPage()} disabled={isFetchingNextPage}>
                  {isFetchingNextPage ? t('common.loading') : t('reviews.more')}
                </Button>
              </div>
            )}
          </>
        )}
      </div>

      <ReviewFormDialog
        slug={slug}
        open={formOpen}
        onOpenChange={setFormOpen}
        initialRating={meta.my_review?.rating ?? 0}
        initialBody={meta.my_review?.body ?? ''}
      />
    </Wrapper>
  )
}
