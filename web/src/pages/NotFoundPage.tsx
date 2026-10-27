import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'
import { MapPinOff } from 'lucide-react'
import { useUser } from '@/api/auth'
import { Button } from '@/components/ui/button'

/**
 * Any URL the app does not know. It used to render nothing at all — no
 * header, no text, no way back — which reads as the site being down.
 */
export function NotFoundPage() {
  const { t, i18n } = useTranslation()
  usePageTitle(t('notFound.title'))
  const language = (LOCALES.find((locale) => locale === i18n.language) ?? 'fr') as Locale
  const { data: user } = useUser()

  return (
    <div className="container flex min-h-[60vh] flex-col items-center justify-center gap-5 text-center">
      <div className="flex h-20 w-20 items-center justify-center rounded-full bg-secondary">
        <MapPinOff className="h-10 w-10 text-primary" aria-hidden />
      </div>
      <div className="max-w-md space-y-2">
        <h1 className="text-3xl font-bold">{t('notFound.title')}</h1>
        <p className="text-muted-foreground">{t('notFound.body')}</p>
      </div>
      <div className="flex flex-wrap items-center justify-center gap-3">
        <Button asChild size="lg">
          <Link to={localizedPath('/', language)}>{t('notFound.home')}</Link>
        </Button>
        {user && (
          <Button asChild variant="outline" size="lg">
            <Link to="/dashboard">{t('nav.myCourses')}</Link>
          </Button>
        )}
      </div>
    </div>
  )
}
