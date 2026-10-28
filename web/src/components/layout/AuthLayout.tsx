import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BlueprintBackdrop } from '@/components/BlueprintBackdrop'
import { ConstruyeLogo } from '@/components/brand/ConstruyeMark'

/**
 * Split screen used by the account and checkout pages: a brand panel on one
 * side, the form on the other. On mobile the panel is dropped entirely so the
 * form is the whole screen.
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
        <BlueprintBackdrop />

        <div className="relative flex h-full flex-col justify-between p-10 text-primary-foreground">
          <Link to="/">
            <span className="sr-only">{t('app.name')}</span>
            <ConstruyeLogo variant="inherit" className="text-primary-foreground" />
          </Link>

          <div className="space-y-3">
            <p className="text-2xl font-extrabold leading-tight">{t('landing.hero.title')}</p>
            {/* What the course is, never who made it: the panel makes no claim
                the instructor has not supplied (constitution I). */}
            <p className="max-w-md text-sm leading-relaxed text-primary-foreground/75">{t('authPanel.body')}</p>
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
