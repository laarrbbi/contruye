import { useTranslation } from 'react-i18next'
import { LoadingAnnouncement, Skeleton } from '@/components/ui/skeleton'

/**
 * The player's own shape while its payload is in flight: sidebar rail, video
 * frame, title, then content blocks — so the real page settles into the same
 * boxes instead of replacing a centred line of text.
 */
export function PlayerSkeleton() {
  const { t } = useTranslation()

  return (
    <div className="flex min-h-[70vh]">
      <LoadingAnnouncement label={t('common.loading')} />

      <aside className="hidden w-80 shrink-0 border-e p-4 lg:block">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="mt-3 h-2 w-full" />
        <div className="mt-6 space-y-4">
          {Array.from({ length: 5 }).map((_, section) => (
            <div key={section} className="space-y-2">
              <Skeleton className="h-3 w-1/3" />
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-11/12" />
            </div>
          ))}
        </div>
      </aside>

      <div className="min-w-0 flex-1 space-y-4 p-4 sm:p-6">
        <Skeleton className="aspect-video w-full rounded-xl" />
        <Skeleton className="h-6 w-2/3" />
        <Skeleton className="h-3 w-24" />
        <div className="space-y-2 pt-4">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-11/12" />
          <Skeleton className="h-4 w-4/5" />
        </div>
        <Skeleton className="h-32 w-full rounded-lg" />
      </div>
    </div>
  )
}
