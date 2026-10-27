import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { isAxiosError } from 'axios'
import { ArrowLeft, ClipboardList, Lock } from 'lucide-react'
import { useChecklists } from '@/api/checklists'
import { ModuleChecklist } from '@/components/player/ModuleChecklist'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { LoadingAnnouncement, Skeleton } from '@/components/ui/skeleton'

/**
 * "Ma feuille de route" — every module's action checklist in one place.
 * Owned modules expand into their full checklist (curated steps + the
 * student's own tasks); locked modules tease the step count and deep-link
 * into checkout with the module preselected.
 */
export function ChecklistsPage() {
  const { slug } = useParams<{ slug: string }>()
  const { t } = useTranslation()
  usePageTitle(t('checklist.pageTitle'))
  const { data, isLoading, isError, error } = useChecklists(slug)

  if (isLoading) {
    return (
      <div className="container max-w-3xl space-y-6 py-8 sm:py-12">
        <LoadingAnnouncement label={t('common.loading')} />
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-16 w-full rounded-lg" />
        {Array.from({ length: 3 }).map((_, card) => (
          <div key={card} className="space-y-3 rounded-lg border p-4">
            <Skeleton className="h-4 w-1/2" />
            {Array.from({ length: 4 }).map((__, row) => (
              <Skeleton key={row} className="h-4 w-full" />
            ))}
          </div>
        ))}
      </div>
    )
  }

  if (isError || !data) {
    const status = isAxiosError(error) ? error.response?.status : undefined
    const notEnrolled = status === 403 || status === 404
    return (
      <div className="container flex min-h-[50vh] flex-col items-center justify-center gap-4 text-center">
        <Lock className="h-10 w-10 text-muted-foreground" />
        <p className="text-muted-foreground">{notEnrolled ? t('player.notEnrolled') : t('common.error')}</p>
        <Button asChild variant="outline">
          <Link to={notEnrolled ? `/courses/${slug}` : '/dashboard'}>
            {notEnrolled ? t('player.goToCourse') : t('common.back')}
          </Link>
        </Button>
      </div>
    )
  }

  const unlocked = data.modules.filter((m) => !m.is_locked)
  const total = unlocked.reduce((sum, m) => sum + m.items.length + m.custom_tasks.length, 0)
  const done = unlocked.reduce(
    (sum, m) =>
      sum + m.items.filter((i) => i.checked).length + m.custom_tasks.filter((task) => task.is_done).length,
    0,
  )
  const percent = total > 0 ? Math.round((done / total) * 100) : 0

  return (
    <div className="container max-w-3xl space-y-8 py-8 sm:py-12">
      <div className="space-y-4">
        <Button asChild variant="ghost" size="sm" className="-ms-2 text-muted-foreground">
          <Link to={`/learn/${slug}`}>
            <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
            {t('checklist.backToCourse')}
          </Link>
        </Button>

        <div className="space-y-2">
          <h1 className="flex items-center gap-2.5 text-3xl font-extrabold text-primary">
            <ClipboardList className="h-7 w-7 shrink-0" />
            {t('checklist.pageTitle')}
          </h1>
          <p className="text-muted-foreground">{t('checklist.pageSubtitle')}</p>
        </div>

        <div className="space-y-1.5 rounded-lg border bg-secondary/40 p-4">
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="font-semibold">{t('checklist.overallProgress')}</span>
            <span className="tabular-nums text-muted-foreground">
              {done}/{total} — {percent}%
            </span>
          </div>
          <Progress value={percent} aria-label={t('checklist.overallProgress')} />
        </div>
      </div>

      <div className="space-y-6">
        {data.modules.map((module) => (
          <article key={module.id} className="space-y-2">
            <h2 className="text-sm font-bold uppercase tracking-wide text-muted-foreground">
              {t('player.moduleLabel', { number: module.order })} — <bdi>{module.title}</bdi>
            </h2>

            {module.is_locked ? (
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed bg-secondary/30 px-4 py-3">
                <span className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Lock className="h-4 w-4 shrink-0" />
                  {t('checklist.lockedTease', { count: module.items_count })}
                </span>
                <Button asChild size="sm" variant="outline">
                  <Link to={`/checkout/${slug}?modules=${module.id}`}>{t('player.unlockModule')}</Link>
                </Button>
              </div>
            ) : (
              <ModuleChecklist slug={slug} module={module} />
            )}
          </article>
        ))}
      </div>
    </div>
  )
}
