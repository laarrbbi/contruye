import { useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { hasContactChannel, useBusinessProfile } from '@/api/business'
import { useUser } from '@/api/auth'
import { ContactOptions } from '@/components/ContactOptions'
import { IsolatedValue } from '@/components/IsolatedValue'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { LOCALES, type Locale } from '@/i18n'
import { localizedPath } from '@/i18n/paths'
import { vars } from './tools'

const WORD = 'titre block select-none whitespace-nowrap text-center text-[15.5vw] !leading-[0.8] tracking-[-0.045em]'

/**
 * The sales page's own footer (the layout's stands down where this one is:
 * see Layout). Very deep night, and the word CONSTRUYE across the whole width,
 * all but invisible until a soft round light passes over it: the light
 * follows the pointer, a little behind it. On a touch screen it wanders
 * slowly by itself and goes to the finger. Without motion it rests in the
 * middle. Under it: the page's main links, large; the contacts when there
 * are any; the company's legal lines, small.
 *
 * CONSTRUYE is the brand and stays in Latin letters in every language.
 */
export function Pied() {
  const { t, i18n } = useTranslation()
  const still = usePrefersReducedMotion()
  const { data: user } = useUser()
  const { data: profile } = useBusinessProfile()
  const board = useRef<HTMLDivElement>(null)
  const locale = (LOCALES.find((l) => l === i18n.language) ?? LOCALES[0]) as Locale

  useEffect(() => {
    const node = board.current
    if (!node || still) return
    // Where the light is, and where it is going: both in shares of the board.
    const at = { x: 0.5, y: 0.5 }
    const to = { x: 0.5, y: 0.5 }
    let guided = 0
    let frame = 0
    let onScreen = false
    const presence = new IntersectionObserver(([entry]) => (onScreen = entry.isIntersecting))
    presence.observe(node)
    const aim = (event: PointerEvent) => {
      const box = node.getBoundingClientRect()
      to.x = (event.clientX - box.left) / box.width
      to.y = (event.clientY - box.top) / box.height
      guided = performance.now()
    }
    const tick = (time: number) => {
      if (onScreen) {
        // Left alone for a while, the light wanders on its own.
        if (time - guided > 2600) {
          to.x = 0.5 + Math.sin(time / 2300) * 0.36
          to.y = 0.5 + Math.sin(time / 1700 + 1) * 0.22
        }
        at.x += (to.x - at.x) * 0.06
        at.y += (to.y - at.y) * 0.06
        node.style.setProperty('--x', `${(at.x * 100).toFixed(2)}%`)
        node.style.setProperty('--y', `${(at.y * 100).toFixed(2)}%`)
      }
      frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    node.addEventListener('pointermove', aim)
    node.addEventListener('pointerdown', aim)
    return () => {
      cancelAnimationFrame(frame)
      presence.disconnect()
      node.removeEventListener('pointermove', aim)
      node.removeEventListener('pointerdown', aim)
    }
  }, [still])

  const links = [
    { key: 'home', label: t('nav.home'), to: localizedPath('/', locale) },
    { key: 'programme', label: t('landing.pied.programme'), href: '#programme' },
    { key: 'tarifs', label: t('landing.pied.tarifs'), href: '#tarifs' },
    { key: 'questions', label: t('landing.pied.questions'), href: '#questions' },
    user ? { key: 'courses', label: t('nav.myCourses'), to: '/dashboard' } : { key: 'login', label: t('nav.login'), to: '/login' },
  ]
  const link = 'lien-edito titre text-[clamp(1.5rem,1.1rem+1.6vw,2.5rem)] !leading-tight'
  const identity = [
    profile?.business_name && <bdi key="name">{profile.business_name}</bdi>,
    profile?.legal_form && <bdi key="form">{profile.legal_form}</bdi>,
    profile?.capital && <IsolatedValue key="capital" i18nKey="footer.legal.capital" name="capital" value={profile.capital} dir="ltr" />,
    profile?.nif && <IsolatedValue key="nif" i18nKey="footer.legal.nif" name="nif" value={profile.nif} dir="ltr" />,
    // The Registro Mercantil entry, as the owner wrote it (LSSI-CE, art. 10).
    profile?.registry && <bdi key="registry">{profile.registry}</bdi>,
  ].filter(Boolean)

  return (
    <footer data-pied data-tone="night" className="relative overflow-hidden bg-[#04090F] text-creme-100" style={vars({ '--focus': '#F6F2EA' })}>
      {/* CONSTRUYE, and the light that finds it. */}
      <div ref={board} aria-hidden dir="ltr" className="relative touch-pan-y py-[3vw]" style={vars({ '--x': '50%', '--y': '50%' })}>
        <span className={`${WORD} text-creme-100/[0.035]`}>CONSTRUYE</span>
        <span
          className={`${WORD} absolute inset-x-0 top-[3vw] bg-[radial-gradient(circle_at_var(--x)_var(--y),#FFE2A8,#E8A33D_38%,#F6F2EA_70%)] bg-clip-text text-transparent [mask-image:radial-gradient(circle_22vw_at_var(--x)_var(--y),#000_0%,rgb(0_0_0/0.55)_45%,transparent_100%)]`}
        >
          CONSTRUYE
        </span>
        <span className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_26vw_at_var(--x)_var(--y),rgb(232_163_61/0.12),transparent_70%)]" />
      </div>

      <div className="container relative pb-10 pt-6 lg:pt-10">
        <nav aria-label={t('landing.pied.nav')}>
          <ul className="flex flex-col gap-x-10 gap-y-4 sm:flex-row sm:flex-wrap">
            {links.map((item) => (
              <li key={item.key}>
                {'to' in item && item.to ? (
                  <Link to={item.to} className={link}>
                    {item.label}
                  </Link>
                ) : (
                  <a href={item.href} className={link}>
                    {item.label}
                  </a>
                )}
              </li>
            ))}
          </ul>
        </nav>

        {hasContactChannel(profile) && (
          <div className="mt-12 border-t border-creme-100/15 pt-8">
            <p className="etiquette mb-4 text-creme-100/70">{t('footer.contact')}</p>
            <ContactOptions tone="onPrimary" />
          </div>
        )}

        <div className="mt-12 space-y-1 border-t border-creme-100/15 pt-6 text-xs leading-relaxed text-creme-100/65">
          {identity.length > 0 && <p className="flex flex-wrap gap-x-3 gap-y-1">{identity}</p>}
          {profile?.address && (
            <p>
              <bdi>{profile.address}</bdi>
            </p>
          )}
          <p>{t('footer.copyright', { year: new Date().getFullYear() })}</p>
          {/* Who made what the page borrows, and under which licence (docs/credits-photos.md). */}
          <p className="text-creme-100/45">{t('landing.pied.credits')}</p>
        </div>
      </div>
    </footer>
  )
}
