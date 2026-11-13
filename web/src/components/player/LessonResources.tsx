import { useTranslation } from 'react-i18next'
import { ArrowUpRight, Globe } from 'lucide-react'
import type { LearnModule, LessonResourcesView } from '@/api/learn'
import { CompanyDirectory } from '@/components/directory/CompanyDirectory'
import { SectionLabel } from '@/components/inside/PageHead'
import { PartnerList } from './PartnerList'

/** «https://www.idealista.com/venta-terrenos/» → «idealista.com». */
function host(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

/**
 * Under a lesson's text, to make it something to do: the websites the lesson
 * names (the land portals of 1.1, the auction sites, the Catastro…), each at
 * its official address, then the partners for what it asks of the promoter
 * (real-estate agencies where it talks of finding land): the academy's own,
 * and the companies of the directory. The partners follow the module's gate:
 * a free lesson read without the module shows the links only.
 */
export function LessonResources({
  slug,
  lessonId,
  module,
  resources,
}: {
  slug: string
  lessonId: number
  module: LearnModule
  resources: LessonResourcesView
}) {
  const { t } = useTranslation()
  const partners = module.is_locked ? [] : module.partners.filter((partner) => resources.partners.includes(partner.category))
  const companies = !module.is_locked && resources.companies.length > 0 && resources.companies_count > 0

  if (resources.links.length === 0 && partners.length === 0 && !companies) return null

  return (
    <div className="space-y-10">
      {resources.links.length > 0 && (
        <section className="rounded-2xl border border-foreground/[0.08] bg-card">
          <div className="border-b px-5 py-4">
            <h2 className="text-sm font-semibold">{t('player.lessonLinks')}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{t('player.lessonLinksLead')}</p>
          </div>
          <ul className="divide-y">
            {resources.links.map((link) => (
              <li key={link.url}>
                <a
                  href={link.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="group flex min-h-[3.25rem] items-center gap-3 px-5 py-3 text-sm transition-colors hover:bg-secondary"
                >
                  <Globe aria-hidden className="h-4 w-4 shrink-0 text-primary" />
                  <span className="min-w-0 flex-1">
                    <span className="block font-medium" lang="es">
                      {link.name}
                    </span>
                    <span className="block truncate text-xs text-muted-foreground" dir="ltr">
                      {host(link.url)}
                    </span>
                  </span>
                  <ArrowUpRight aria-hidden className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 rtl:-scale-x-100" />
                  <span className="sr-only">{t('player.opensInNewTab')}</span>
                </a>
              </li>
            ))}
          </ul>
        </section>
      )}

      {(partners.length > 0 || companies) && (
        <section className="space-y-5">
          <SectionLabel>{t('player.lessonPartners')}</SectionLabel>
          <p className="max-w-2xl text-sm text-foreground/70">{t('player.lessonPartnersLead')}</p>
          {partners.length > 0 && <PartnerList partners={partners} />}
          {companies && <CompanyDirectory slug={slug} lessonId={lessonId} />}
        </section>
      )}
    </div>
  )
}
