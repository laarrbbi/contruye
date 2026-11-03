import { Navigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useLearn } from '@/api/learn'
import { useChecklists } from '@/api/checklists'
import { MarkdownContent } from '@/components/MarkdownContent'
import { CompanyDirectory } from '@/components/directory/CompanyDirectory'
import { SectionLabel } from '@/components/inside/PageHead'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { ModuleChecklist } from '@/components/player/ModuleChecklist'
import { PlayerShell } from '@/components/player/PlayerShell'
import { PlayerSkeleton } from '@/components/player/PlayerSkeleton'
import { ProvinceGuidePanel } from '@/components/player/ProvinceGuidePanel'
import { usePageTitle } from '@/hooks/usePageTitle'
import { moduleLabel } from '@/lib/modules'

export const MODULE_SECTIONS = ['tareas', 'leyes', 'partners'] as const
export type ModuleSection = (typeof MODULE_SECTIONS)[number]

/**
 * What a module leaves the promoter with, each on its own page after the
 * quiz: the tasks (the module's checklist and the student's own), the laws
 * that apply, and the partners (where to go, the province's institutions and
 * the companies of the directory for this phase).
 */
export function ModuleSectionPage() {
  const { slug, moduleId, section } = useParams<{ slug: string; moduleId: string; section: string }>()
  const { t } = useTranslation()
  const { data, isLoading } = useLearn(slug)
  const checklists = useChecklists(slug)
  const module = data?.modules.find((m) => m.id === Number(moduleId))
  usePageTitle(module ? `${t(`moduleSection.${section}`)} · ${moduleLabel(module, t)}` : undefined)

  if (isLoading || !data || !slug) return <PlayerSkeleton />
  if (!module || !MODULE_SECTIONS.includes(section as ModuleSection) || module.is_locked) return <Navigate to={`/learn/${slug}`} replace />

  const kind = section as ModuleSection
  const laws = module.blocks.filter((b) => b.type === 'overview' && b.role === 'laws')
  const where = module.blocks.filter((b) => b.type === 'overview' && b.role === 'where')
  const checklist = checklists.data?.modules.find((m) => m.id === module.id && !m.is_locked)

  return (
    <PlayerShell data={data} location={{ moduleId: module.id, section: kind }}>
      <header>
        <p className="etiquette flex items-center gap-3 text-foreground/60">
          <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
          <span>
            {moduleLabel(module, t)} · <bdi>{module.title}</bdi>
          </span>
        </p>
        <h1 className="titre mt-4 text-[clamp(1.75rem,1.3rem+1.6vw,2.75rem)]">{t(`moduleSection.${kind}`)}</h1>
        <p className="mt-3 max-w-2xl text-foreground/70">{t(`moduleSection.${kind}Lead`)}</p>
      </header>

      {kind === 'tareas' &&
        (checklist ? <ModuleChecklist slug={slug} module={checklist} /> : <p className="text-sm text-muted-foreground">{t('moduleSection.noTasks')}</p>)}

      {kind === 'leyes' && (
        <article className="space-y-4 rounded-2xl border border-foreground/[0.08] bg-card p-6 sm:p-8">
          {laws.length > 0 && <ContentLanguageNote locale={laws[0].body_locale} />}
          {laws.length > 0 ? (
            laws.map((block) => <MarkdownContent key={block.id} html={block.body_html} lang={block.body_locale} />)
          ) : (
            <p className="text-sm text-muted-foreground">{t('moduleSection.noLaws')}</p>
          )}
        </article>
      )}

      {kind === 'partners' && (
        <div className="space-y-12">
          {where.length > 0 && (
            <article className="space-y-4 rounded-2xl border border-foreground/[0.08] bg-card p-6 sm:p-8">
              {where.map((block) => (
                <MarkdownContent key={block.id} html={block.body_html} lang={block.body_locale} />
              ))}
            </article>
          )}
          {module.kind === 'phase' && <ProvinceGuidePanel slug={slug} phase={module.order} showAnnexLink={data.course.full_access} />}
          {module.kind === 'phase' && (
            <div className="space-y-5">
              <SectionLabel>{t('moduleSection.companies')}</SectionLabel>
              <p className="max-w-2xl text-sm text-foreground/70">{t('moduleSection.companiesLead')}</p>
              <CompanyDirectory slug={slug} moduleId={module.id} />
            </div>
          )}
        </div>
      )}
    </PlayerShell>
  )
}
