import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ChevronLeft, ChevronRight, Info, Trash2, X } from 'lucide-react'
import {
  useAdminReviews,
  useDeleteReview,
  useModerateReview,
  type AdminReview,
  type ReviewStatus,
} from '@/api/admin'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { StarRating } from '@/components/reviews/StarRating'
import { formatDate } from './lib'

const ALL = 'all'
const STATUSES: ReviewStatus[] = ['pending', 'approved', 'rejected']

const STATUS_VARIANT: Record<ReviewStatus, 'default' | 'success' | 'secondary'> = {
  pending: 'default',
  approved: 'success',
  rejected: 'secondary',
}

function RejectDialog({
  review,
  onOpenChange,
}: {
  review: AdminReview | null
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation()
  const moderate = useModerateReview()
  const [note, setNote] = useState('')

  return (
    <Dialog
      open={review !== null}
      onOpenChange={(next) => {
        if (moderate.isPending) return
        if (!next) setNote('')
        onOpenChange(next)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('admin.reviews.rejectTitle')}</DialogTitle>
          <DialogDescription>{t('admin.reviews.rejectBody')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="review-note">{t('admin.reviews.noteLabel')}</Label>
          <Textarea
            id="review-note"
            rows={3}
            maxLength={1000}
            value={note}
            onChange={(event) => setNote(event.target.value)}
          />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={moderate.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            disabled={moderate.isPending}
            onClick={() => {
              if (!review) return
              moderate.mutate(
                { id: review.id, status: 'rejected', admin_note: note.trim() || null },
                {
                  onSuccess: () => {
                    setNote('')
                    onOpenChange(false)
                  },
                },
              )
            }}
          >
            {moderate.isPending ? t('common.loading') : t('admin.reviews.actions.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function AdminReviewsPage() {
  const { t, i18n } = useTranslation()
  const [status, setStatus] = useState<ReviewStatus | typeof ALL>('pending')
  const [page, setPage] = useState(1)
  const [rejectTarget, setRejectTarget] = useState<AdminReview | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminReview | null>(null)

  const { data, isLoading, isError, isFetching } = useAdminReviews({
    status: status === ALL ? '' : status,
    page,
  })
  const moderate = useModerateReview()
  const remove = useDeleteReview()

  const reviews = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{t('admin.reviews.title')}</h1>
          {meta && meta.pending_count > 0 && (
            <Badge>{t('admin.reviews.pendingBadge', { count: meta.pending_count })}</Badge>
          )}
        </div>
        <Select
          value={status}
          onValueChange={(value) => {
            setStatus(value as ReviewStatus | typeof ALL)
            setPage(1)
          }}
        >
          <SelectTrigger className="w-[200px]" aria-label={t('admin.orders.table.status')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>{t('admin.reviews.allStatuses')}</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`reviews.status.${s}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <p className="flex items-start gap-2 rounded-lg border bg-secondary/40 p-3 text-sm text-muted-foreground">
        <Info className="mt-0.5 h-4 w-4 shrink-0" />
        {t('admin.reviews.authorNote')}
      </p>

      {isLoading ? (
        <p className="py-12 text-center text-muted-foreground">{t('common.loading')}</p>
      ) : isError ? (
        <p className="py-12 text-center text-destructive">{t('common.error')}</p>
      ) : reviews.length === 0 ? (
        <div className="rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          {t('admin.reviews.empty')}
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('admin.reviews.table.author')}</TableHead>
                  <TableHead>{t('admin.reviews.table.rating')}</TableHead>
                  <TableHead>{t('admin.reviews.table.review')}</TableHead>
                  <TableHead>{t('admin.reviews.table.status')}</TableHead>
                  <TableHead>{t('admin.reviews.table.date')}</TableHead>
                  <TableHead className="text-end">
                    <span className="sr-only">{t('admin.common.actions')}</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {reviews.map((review) => (
                  <TableRow key={review.id}>
                    <TableCell>
                      <div className="font-medium leading-tight">{review.user.name}</div>
                      <div className="text-xs text-muted-foreground" dir="ltr">
                        {review.user.email}
                      </div>
                    </TableCell>
                    <TableCell>
                      <StarRating value={review.rating} size="sm" />
                    </TableCell>
                    <TableCell className="max-w-sm">
                      <p className="whitespace-pre-line text-sm leading-snug">{review.body ?? '—'}</p>
                    </TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[review.status]}>
                        {t(`reviews.status.${review.status}`)}
                      </Badge>
                    </TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">
                      {formatDate(review.created_at, i18n.language)}
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        {review.status !== 'approved' && (
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('admin.reviews.actions.approve')}
                            disabled={moderate.isPending}
                            onClick={() => moderate.mutate({ id: review.id, status: 'approved' })}
                          >
                            <Check className="h-4 w-4 text-success" />
                          </Button>
                        )}
                        {review.status !== 'rejected' && (
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('admin.reviews.actions.reject')}
                            onClick={() => setRejectTarget(review)}
                          >
                            <X className="h-4 w-4 text-destructive" />
                          </Button>
                        )}
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={t('admin.reviews.actions.delete')}
                          onClick={() => setDeleteTarget(review)}
                        >
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {meta && meta.last_page > 1 && (
            <div className="flex items-center justify-between gap-3">
              <p className="text-sm text-muted-foreground">
                {t('admin.common.pageOf', { page: meta.current_page, total: meta.last_page })}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page <= 1 || isFetching}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
                  {t('admin.common.previous')}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={meta.current_page >= meta.last_page || isFetching}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('admin.common.next')}
                  <ChevronRight className="h-4 w-4 rtl:rotate-180" />
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      <RejectDialog review={rejectTarget} onOpenChange={(open) => !open && setRejectTarget(null)} />

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin.common.confirmDeleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('admin.reviews.deleteConfirm')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={remove.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              disabled={remove.isPending}
              onClick={(event) => {
                event.preventDefault()
                if (!deleteTarget) return
                remove.mutate(deleteTarget.id, { onSettled: () => setDeleteTarget(null) })
              }}
            >
              {remove.isPending ? t('common.loading') : t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
