import { useTranslation } from 'react-i18next'
import { LoadingAnnouncement, Skeleton } from '@/components/ui/skeleton'

/**
 * Shown while a lazily loaded section (admin, player) arrives. It stays
 * invisible for its first 150 ms: on a fast connection the page lands before
 * the skeleton would, and a skeleton that flashes for one frame reads as a
 * glitch, not as loading.
 */
export function RouteFallback() {
  const { t } = useTranslation()
  return (
    <div className="container space-y-4 py-10 animate-in fade-in-0 fill-mode-backwards delay-150 duration-200">
      <LoadingAnnouncement label={t('common.loading')} />
      <Skeleton className="h-8 w-1/3" />
      <Skeleton className="h-4 w-2/3" />
      <Skeleton className="h-40 w-full" />
    </div>
  )
}
