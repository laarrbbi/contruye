import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BackgroundVideo } from '@/components/BackgroundVideo'
import { EbniLogo } from '@/components/brand/EbniMark'

/**
 * Split screen used by the account and checkout pages: a media panel on one
 * side, the form on the other. On mobile the media panel is dropped entirely
 * so the form is the whole screen.
 *
 * The background video is decorative and muted, and is not mounted at all for
 * visitors who asked for reduced motion.
 */
export function AuthLayout({
  title,
  subtitle,
  children,
  footer,
  wide = false,
}: {
  title: string
  subtitle?: ReactNode
  children: ReactNode
  footer?: ReactNode
  /** Checkout needs more room than a login form. */
  wide?: boolean
}) {
  const { t } = useTranslation()

  return (
    <div className="grid min-h-[calc(100vh-4rem)] lg:grid-cols-2">
      {/* Media panel */}
      <div className="relative isolate hidden overflow-hidden bg-primary lg:block">
        <BackgroundVideo
          // The panel only shows from lg up; phones must not download it.
          media="(min-width: 1024px)"
          brightness={0.75}
          cropCorner="top-right"
          overlayClassName="bg-gradient-to-t from-primary via-primary/55 to-primary/10"
        />

        <div className="relative flex h-full flex-col justify-between p-10 text-primary-foreground">
          <Link to="/">
            <span className="sr-only">{t('app.name')}</span>
            <EbniLogo variant="inherit" className="text-primary-foreground" />
          </Link>

          <div className="space-y-3">
            <p className="text-2xl font-extrabold leading-tight">{t('landing.hero.title')}</p>
            <dl className="flex flex-wrap gap-x-8 gap-y-3 pt-2">
              <div>
                <dt className="text-2xl font-extrabold leading-none">+2 100</dt>
                <dd className="mt-1 text-xs text-primary-foreground/70">
                  {t('landing.trackRecord.logements')}
                </dd>
              </div>
              <div>
                <dt className="text-2xl font-extrabold leading-none">16</dt>
                <dd className="mt-1 text-xs text-primary-foreground/70">
                  {t('landing.trackRecord.years')}
                </dd>
              </div>
              <div>
                <dt className="text-2xl font-extrabold leading-none">13</dt>
                <dd className="mt-1 text-xs text-primary-foreground/70">{t('nav.modules')}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      {/* Form panel */}
      <div className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className={wide ? 'w-full max-w-2xl' : 'w-full max-w-md'}>
          <div className="mb-8 space-y-2">
            <h1 className="text-3xl font-extrabold leading-tight text-primary">{title}</h1>
            {subtitle && <p className="text-muted-foreground">{subtitle}</p>}
          </div>

          {children}

          {footer && <div className="mt-6 text-center text-sm">{footer}</div>}
        </div>
      </div>
    </div>
  )
}
