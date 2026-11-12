import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useHeaderTone } from '@/lib/headerTone'
import { cn } from '@/lib/utils'

/** The page's sections, by the id each one carries. */
const ITEMS = [
  ['presentation', 'presentation'],
  ['calculadora', 'calculadora'],
  ['parcours', 'parcours'],
  ['methode', 'methode'],
  ['pour-qui', 'pourQui'],
  ['journal', 'journal'],
  ['outils', 'outils'],
  ['programme', 'programme'],
  ['questions', 'questions'],
  ['boletin', 'boletin'],
] as const

/**
 * A discreet table of contents on the side of wide screens: one short line
 * per section, the current one longer, its name beside it. It takes the
 * colour of the ground it stands on, as the header does.
 */
export function Sommaire({ held }: { held: boolean }) {
  const { t } = useTranslation()
  const tone = useHeaderTone()
  const [current, setCurrent] = useState<string>('presentation')

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => entry.isIntersecting && setCurrent(entry.target.id))
      },
      // The section that crosses the middle of the screen is the current one.
      { rootMargin: '-50% 0px -50% 0px' },
    )
    const watch = () => ITEMS.forEach(([id]) => {
      const node = document.getElementById(id)
      if (node) observer.observe(node)
    })
    watch()
    const root = document.getElementById('sales-page')
    const mutations = new MutationObserver(watch)
    if (root) mutations.observe(root, { childList: true, subtree: true })
    return () => {
      mutations.disconnect()
      observer.disconnect()
    }
  }, [])

  if (held) return null
  return (
    <nav
      aria-label={t('landing.sommaire.label')}
      className={cn('fixed end-4 top-1/2 z-30 hidden -translate-y-1/2 transition-colors duration-500 ease-out-strong xl:block', tone === 'night' ? 'text-creme-100' : 'text-plume')}
    >
      <ol>
        {ITEMS.map(([id, key]) => {
          const active = id === current
          return (
            <li key={id}>
              <a
                href={`#${id}`}
                aria-current={active ? 'location' : undefined}
                className="group flex items-center justify-end gap-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current"
              >
                <span
                  className={cn(
                    'etiquette transition-opacity duration-300 ease-out-strong group-hover:opacity-100 group-focus-visible:opacity-100',
                    active ? 'opacity-0 min-[1560px]:opacity-90' : 'opacity-0',
                  )}
                >
                  {t(`landing.sommaire.${key}`)}
                </span>
                <span
                  aria-hidden
                  className={cn(
                    'h-px w-8 origin-right bg-current transition-[transform,opacity] duration-500 ease-out-strong rtl:origin-left',
                    active ? 'scale-x-100 opacity-100' : 'scale-x-[0.4] opacity-40 group-hover:scale-x-75 group-hover:opacity-80',
                  )}
                />
              </a>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
