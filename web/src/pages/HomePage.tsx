import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/api/client'
import type { Course } from '@/api/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { formatPrice } from '@/lib/utils'

export function HomePage() {
  const { t, i18n } = useTranslation()
  usePageTitle(t('seo.catalog.title'), t('seo.catalog.description'))
  const language = (LOCALES.find((locale) => locale === i18n.language) ?? 'fr') as Locale

  const { data: courses } = useQuery<Course[]>({
    queryKey: ['courses'],
    queryFn: async () => {
      const { data } = await api.get('/courses')
      return data.data
    },
  })

  return (
    <div>
      <section className="bg-primary py-16 text-primary-foreground sm:py-24">
        <div className="container max-w-3xl text-center">
          <h1 className="text-3xl font-extrabold leading-tight sm:text-5xl">{t('app.tagline')}</h1>
        </div>
      </section>

      <section className="container py-12">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {courses?.map((course) => (
            <Card key={course.id} className="overflow-hidden">
              {course.cover_image && (
                <img src={course.cover_image} alt={course.title} className="aspect-video w-full object-cover" />
              )}
              <CardContent className="space-y-4 p-5">
                <h2 className="bidi-auto text-lg font-semibold leading-snug">{course.title}</h2>
                <p className="bidi-auto line-clamp-3 text-sm text-muted-foreground">{course.description}</p>
                <div className="flex items-center justify-between">
                  {/* A running promotion must show here too, or the catalog
                      price contradicts the landing page and the checkout. */}
                  <span className="flex items-baseline gap-2 text-xl font-bold text-primary">
                    {course.campaign && (
                      <span className="text-sm font-medium text-muted-foreground line-through">
                        {formatPrice(course.price_dzd, 'DZD', i18n.language)}
                      </span>
                    )}
                    {formatPrice(
                      course.campaign
                        ? Math.round((course.price_dzd * (100 - course.campaign.percent_off)) / 100)
                        : course.price_dzd,
                      'DZD',
                      i18n.language,
                    )}
                  </span>
                  <Button asChild>
                    <Link to={localizedPath(`/cursos/${course.slug}`, language)}>{t('dashboard.start')}</Link>
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>
    </div>
  )
}
