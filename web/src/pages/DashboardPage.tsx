import { Link } from 'react-router-dom'
import { useState } from 'react'
import { useUser } from '@/api/auth'
import { PartnerProvinceFilter } from '@/components/player/PartnerProvinceFilter'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, Boxes } from 'lucide-react'
import { PageHead, SectionLabel } from '@/components/inside/PageHead'
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

  const firstName = user?.name?.trim().split(/\s+/)[0]

  return (
    <div className="container py-12 sm:py-16 lg:py-20">
      <PageHead
        eyebrow={firstName ? t('dashboard.hello', { name: firstName }) : undefined}
        title={t('dashboard.title')}
        lead={t('dashboard.lead')}
      />

      {isLoading ? (
        <div className="grid gap-6">
          <LoadingAnnouncement label={t('common.loading')} />
          <Skeleton className="h-[22rem] w-full rounded-3xl" />
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
          <CardContent className="flex flex-col items-center gap-4 py-16 text-center">
            <p className="titre text-2xl">{t('dashboard.empty')}</p>
            <Button asChild variant="accent" size="lg">
              <Link to="/">{t('dashboard.browseCourses')}</Link>
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-8">
          {enrollments.map((enrollment) => {
            const percent = enrollment.course.progress_percent ?? 0
            const partial = !enrollment.full_access && enrollment.owned_modules_count < enrollment.total_modules_count
            return (
              <article key={enrollment.id} className="grid overflow-hidden rounded-3xl border border-foreground/[0.08] bg-card shadow-[0_40px_80px_-50px_rgb(20_26_34/0.45)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                {/* Night: the building the course puts up, and how far up it is. */}
                <div className="theme-night relative flex min-h-[18rem] items-end overflow-hidden bg-[radial-gradient(110%_90%_at_70%_20%,#12253F,#0B1A2E_55%,#070F1C)] p-8 sm:p-10">
                  <div aria-hidden className="absolute inset-0 bg-[linear-gradient(to_right,rgb(246_242_234/0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgb(246_242_234/0.04)_1px,transparent_1px)] bg-[size:48px_48px]" />
                  <img src="/immeuble-termine.webp" alt="" decoding="async" className="pointer-events-none absolute bottom-0 end-4 h-[88%] max-h-[22rem] object-contain object-bottom opacity-95 drop-shadow-[0_30px_40px_rgb(0_0_0/0.45)] sm:end-10" />
                  <div className="relative">
                    <p className="etiquette text-foreground/60">{t('dashboard.progressLabel')}</p>
                    <p className="titre mt-2 text-[clamp(3.5rem,2.5rem+4vw,6rem)] !leading-none tabular-nums text-or">
                      <bdi>{percent}</bdi>
                      <span className="ms-1 text-[0.45em]">%</span>
                    </p>
                  </div>
                </div>
                <div className="flex flex-col justify-between gap-8 p-8 sm:p-10">
                  <div>
                    <SectionLabel>{t('dashboard.courseLabel')}</SectionLabel>
                    <h2 className="bidi-auto titre mt-4 text-[clamp(1.5rem,1.2rem+1.2vw,2.25rem)]">{enrollment.course.title}</h2>
                    <Progress value={percent} aria-label={enrollment.course.title} className="mt-6 h-1.5" />
                    <p className="mt-3 text-sm text-muted-foreground">{t('dashboard.progress', { percent })}</p>
                  </div>
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button asChild variant="accent" size="lg" className="sm:min-w-[14rem]">
                      <Link to={`/learn/${enrollment.course.slug}`}>
                        {percent ? t('dashboard.continue') : t('dashboard.start')}
                        <ArrowRight className="h-4 w-4 rtl:-scale-x-100" />
                      </Link>
                    </Button>
                    {/* Partial access: nudge toward buying the remaining modules
                        (checkout auto-upgrades to full when all are selected). */}
                    {partial && (
                      <Button asChild variant="outline" size="lg">
                        <Link to={`/checkout/${enrollment.course.slug}?scope=modules`}>
                          <Boxes className="h-4 w-4" />
                          {t('dashboard.unlockMore')}
                        </Link>
                      </Button>
                    )}
                  </div>
                  {partial && (
                    <p className="-mt-4 text-xs text-muted-foreground">
                      {t('dashboard.moduleAccess', { owned: enrollment.owned_modules_count, total: enrollment.total_modules_count })}
                    </p>
                  )}
                </div>
              </article>
            )
          })}
        </div>
      )}

      {/* Set once here, and every partner list across the course filters to
          it — a learner should not have to discover this inside a lesson. */}
      <Card className="mt-10">
        <CardContent className="flex flex-wrap items-center justify-between gap-6 p-6 sm:p-8">
          <div className="max-w-md">
            <SectionLabel>{t('dashboard.provinceTitle')}</SectionLabel>
            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">{t('dashboard.provinceHelp')}</p>
          </div>
          <PartnerProvinceFilter value={provinceId} onChange={(next) => setProvinceOverride({ id: next })} showLabel={false} />
        </CardContent>
      </Card>
    </div>
  )
}
