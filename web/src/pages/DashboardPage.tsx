import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useUser } from '@/api/auth'
import { PartnerProvinceFilter } from '@/components/player/PartnerProvinceFilter'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useQuery } from '@tanstack/react-query'
import { Boxes } from 'lucide-react'
import { api } from '@/api/client'
import type { Enrollment } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { LoadingAnnouncement, Skeleton } from '@/components/ui/skeleton'

export function DashboardPage() {
  const { t } = useTranslation()
  usePageTitle(t('dashboard.title'))
  const { data: user } = useUser()
  const [provinceOverride, setProvinceOverride] = useState<{ id: number | null } | null>(null)
  const provinceId = provinceOverride ? provinceOverride.id : (user?.province_id ?? null)

  const { data: enrollments, isLoading, isError, refetch } = useQuery<Enrollment[]>({
    queryKey: ['enrollments'],
    queryFn: async () => {
      const { data } = await api.get('/enrollments')
      return data.data
    },
  })

  return (
    <div className="container py-8 sm:py-12">
      <h1 className="mb-6 text-3xl font-bold">{t('dashboard.title')}</h1>

      {/* Set once here, and every partner list across the course filters to
          it — a learner should not have to discover this inside a lesson. */}
      <div className="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-secondary/40 p-4">
        <div>
          <p className="text-sm font-medium">{t('dashboard.provinceTitle')}</p>
          <p className="text-xs text-muted-foreground">{t('dashboard.provinceHelp')}</p>
        </div>
        <PartnerProvinceFilter
          value={provinceId}
          onChange={(next) => setProvinceOverride({ id: next })}
          showLabel={false}
        />
      </div>

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <LoadingAnnouncement label={t('common.loading')} />
          {Array.from({ length: 2 }).map((_, card) => (
            <div key={card} className="space-y-3 rounded-lg border p-4">
              <Skeleton className="aspect-video w-full rounded-md" />
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-2 w-full" />
              <Skeleton className="h-8 w-32" />
            </div>
          ))}
        </div>
      ) : isError ? (
        // Telling a paying student they own nothing because a request failed
        // is the worst possible reading of a network error.
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
            <p className="text-muted-foreground">{t('common.loadFailed')}</p>
            <Button onClick={() => refetch()}>{t('common.retry')}</Button>
          </CardContent>
        </Card>
      ) : !enrollments?.length ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
            <p className="text-muted-foreground">{t('dashboard.empty')}</p>
            <Button asChild>
              <Link to="/">{t('dashboard.browseCourses')}</Link>
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {enrollments.map((enrollment) => (
            <Card key={enrollment.id} className="overflow-hidden">
              {enrollment.course.cover_image && (
                <img
                  src={enrollment.course.cover_image}
                  alt={enrollment.course.title}
                  className="aspect-video w-full object-cover"
                />
              )}
              <CardContent className="space-y-4 p-5">
                <h2 className="bidi-auto font-semibold leading-snug">{enrollment.course.title}</h2>
                {typeof enrollment.course.progress_percent === 'number' && (
                  <div className="space-y-1">
                    <Progress value={enrollment.course.progress_percent} aria-label={enrollment.course.title} />
                    <p className="text-xs text-muted-foreground">
                      {t('dashboard.progress', { percent: enrollment.course.progress_percent })}
                    </p>
                  </div>
                )}
                <Button asChild className="w-full">
                  <Link to={`/learn/${enrollment.course.slug}`}>
                    {enrollment.course.progress_percent ? t('dashboard.continue') : t('dashboard.start')}
                  </Link>
                </Button>

                {/* Partial access: nudge toward buying the remaining modules
                    (checkout auto-upgrades to full when all are selected). */}
                {!enrollment.full_access &&
                  enrollment.owned_modules_count < enrollment.total_modules_count && (
                    <div className="space-y-2 border-t pt-3">
                      <p className="text-xs text-muted-foreground">
                        {t('dashboard.moduleAccess', {
                          owned: enrollment.owned_modules_count,
                          total: enrollment.total_modules_count,
                        })}
                      </p>
                      <Button asChild variant="outline" size="sm" className="w-full">
                        <Link to={`/checkout/${enrollment.course.slug}?scope=modules`}>
                          <Boxes className="me-2 h-4 w-4" />
                          {t('dashboard.unlockMore')}
                        </Link>
                      </Button>
                    </div>
                  )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
