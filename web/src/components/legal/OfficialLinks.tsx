import { useTranslation } from 'react-i18next'
import { ExternalLink, Landmark, Receipt, Scale } from 'lucide-react'
import type { OfficialLink } from '@/api/learn'
import { cn } from '@/lib/utils'

const ICONS = { planning: Scale, taxes: Receipt, housing: Landmark } as const

/**
 * A community's official pages, each opening the official site in a new tab:
 * the planning law, the tax agency's ITP and AJD page, the protected-housing
 * maximum prices. The label says what it is; the official title says where.
 */
export function OfficialLinks({ links, className }: { links: OfficialLink[]; className?: string }) {
  const { t } = useTranslation()
  if (links.length === 0) return null

  return (
    <ul className={cn('space-y-2', className)}>
      {links.map((link) => {
        const Icon = ICONS[link.kind]
        return (
          <li key={link.url}>
            <a
              href={link.url}
              target="_blank"
              rel="noopener noreferrer"
              className="group flex items-start gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-secondary"
            >
              <Icon aria-hidden className="mt-0.5 h-4 w-4 shrink-0 text-or-fonce" />
              <span className="min-w-0">
                <span className="flex items-center gap-1.5 text-sm font-medium text-foreground group-hover:underline">
                  {t(`legal.kind.${link.kind}`)}
                  <ExternalLink aria-hidden className="h-3 w-3 shrink-0 text-muted-foreground" />
                  <span className="sr-only">{t('legal.newTab')}</span>
                </span>
                {/* The official title stays in its own language and reads left to right on an Arabic page. */}
                <span className="block text-xs leading-snug text-muted-foreground">
                  <bdi lang={link.lang} dir="ltr">
                    {link.title}
                  </bdi>
                </span>
              </span>
            </a>
          </li>
        )
      })}
    </ul>
  )
}
