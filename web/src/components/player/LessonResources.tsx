import { useTranslation } from 'react-i18next'
import { ArrowUpRight, Calculator, Globe } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { LearnModule, LessonResourcesView } from '@/api/learn'
import { CompanyDirectory } from '@/components/directory/CompanyDirectory'
import { PartnerList } from './PartnerList'
import { SectionTitle } from './SectionTitle'

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
 * a free lesson read without the module shows the links only. First of all,
 * when the lesson names a template of its module, the templates themselves
 * (the owner's call: the template where the text talks about it).
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

  const templateTitles = [...(module.template ? [module.template.title] : []), ...(module.professional_templates ?? []).map((tpl) => tpl.title)]
  const templates = !!resources.templates && !module.is_locked && templateTitles.length > 0

  if (!templates && resources.links.length === 0 && partners.length === 0 && !companies) return null

  return (
    <div className="space-y-10">
      {templates && (
        <section aria-labelledby={`plantillas-${lessonId}`} className="space-y-4">
          <SectionTitle id={`plantillas-${lessonId}`} lead={t('player.lessonTemplatesLead')}>
            {t('player.lessonTemplates')}
          </SectionTitle>
          <div className="flex flex-col gap-4 rounded-2xl border border-primary/25 bg-primary/[0.04] p-5 sm:flex-row sm:items-center">
            <span aria-hidden className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Calculator className="h-5 w-5" />
            </span>
            <ul className="min-w-0 flex-1 space-y-0.5 text-sm font-medium">
              {templateTitles.map((title) => (
                <li key={title} className="bidi-auto">
                  {title}
                </li>
              ))}
            </ul>
            <Link
              to={`/learn/${slug}/modules/${module.id}/plantilla`}
              className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
            >
              {t('player.openTemplates')}
            </Link>
          </div>
        </section>
      )}
      {resources.links.length > 0 && (
        <section aria-labelledby={`webs-${lessonId}`} className="space-y-4">
          <SectionTitle id={`webs-${lessonId}`} lead={t('player.lessonLinksLead')}>
            {t('player.lessonLinks')}
          </SectionTitle>
          <ul className="divide-y rounded-2xl border border-foreground/[0.08] bg-card">
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
        <section aria-labelledby={`empresas-${lessonId}`} className="space-y-5">
          <SectionTitle id={`empresas-${lessonId}`} lead={t('player.lessonPartnersLead')}>
            {t('player.lessonPartners')}
          </SectionTitle>
          {partners.length > 0 && <PartnerList partners={partners} />}
          {companies && <CompanyDirectory slug={slug} lessonId={lessonId} />}
        </section>
      )}
    </div>
  )
}
