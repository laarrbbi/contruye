import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useUser } from '@/api/auth'
import { hasContactChannel, useBusinessProfile } from '@/api/business'
import { EbniLogo } from '@/components/brand/EbniMark'
import { ContactOptions } from '@/components/ContactOptions'
import { IsolatedValue } from '@/components/IsolatedValue'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'

/** The small heading over each footer column. */
const COLUMN_TITLE = 'text-xs font-semibold uppercase tracking-wider text-foreground/70 rtl:tracking-normal'

/**
 * What the site is in one sentence, where to go from here, and who the buyer
 * is paying and how to reach them.
 *
 * Contact and legal lines are built only from what the owner has filled in
 * the admin (Entreprise & contact). Until then neither shows — never a
 * placeholder RC or a sample number, and no "contact" heading over nothing.
 *
 * Each value sits in its own isolate, so the Arabic site shows it as typed.
 * Registration numbers and the capital read left to right in every language.
 */
export function Footer() {
  const { t, i18n } = useTranslation()
  const { data: user } = useUser()
  const { data: profile } = useBusinessProfile()

  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'fr') as Locale
  const links = [
    { to: localizedPath('/', locale), label: t('nav.home') },
    { to: localizedPath('/formations', locale), label: t('footer.courses') },
    ...(user
      ? [{ to: '/dashboard', label: t('nav.myCourses') }]
      : [
          { to: '/login', label: t('nav.login') },
          { to: '/register', label: t('nav.register') },
        ]),
  ]

  const identity = [
    profile?.business_name && { key: 'name', node: <bdi>{profile.business_name}</bdi> },
    profile?.legal_form && { key: 'form', node: <bdi>{profile.legal_form}</bdi> },
    profile?.capital && {
      key: 'capital',
      node: <IsolatedValue i18nKey="footer.legal.capital" name="capital" value={profile.capital} dir="ltr" />,
    },
  ].filter((part) => !!part)

  const registrations = [
    profile?.rc && { key: 'rc', node: <IsolatedValue i18nKey="footer.legal.rc" name="rc" value={profile.rc} dir="ltr" /> },
    profile?.nif && { key: 'nif', node: <IsolatedValue i18nKey="footer.legal.nif" name="nif" value={profile.nif} dir="ltr" /> },
  ].filter((line) => !!line)

  return (
    <footer className="border-t bg-secondary/30">
      <div className="container py-10 text-sm">
        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-[minmax(0,5fr)_minmax(0,3fr)_minmax(0,4fr)] lg:gap-14">
          <div className="space-y-3 sm:col-span-2 lg:col-span-1">
            <Link to={localizedPath('/', locale)} className="inline-flex">
              <span className="sr-only">{t('app.name')}</span>
              <EbniLogo />
            </Link>
            <p className="max-w-sm leading-relaxed text-muted-foreground">{t('app.metaDescription')}</p>
          </div>

          <nav aria-label={t('footer.explore')} className="space-y-3">
            <p className={COLUMN_TITLE}>{t('footer.explore')}</p>
            <ul className="space-y-2">
              {links.map(({ to, label }) => (
                <li key={to}>
                  <Link to={to} className="text-muted-foreground transition-colors hover:text-foreground">
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          {hasContactChannel(profile) && (
            <div className="space-y-3">
              <p className={COLUMN_TITLE}>{t('footer.contact')}</p>
              <ContactOptions />
            </div>
          )}
        </div>

        <div className="mt-8 space-y-1 border-t pt-6 text-xs leading-relaxed text-muted-foreground">
          {identity.length > 0 && (
            <p className="font-medium text-foreground/80">
              {identity.map(({ key, node }, index) => (
                <span key={key}>
                  {index > 0 && ' — '}
                  {node}
                </span>
              ))}
            </p>
          )}
          {profile?.address && (
            <p>
              <bdi>{profile.address}</bdi>
            </p>
          )}
          {registrations.length > 0 && (
            <p className="flex flex-wrap gap-x-3 gap-y-1">
              {registrations.map(({ key, node }) => (
                <span key={key}>{node}</span>
              ))}
            </p>
          )}
          <p className={identity.length > 0 || profile?.address || registrations.length > 0 ? 'pt-2' : undefined}>
            <span dir="ltr">{t('footer.copyright', { year: new Date().getFullYear() })}</span>
          </p>
        </div>
      </div>
    </footer>
  )
}
