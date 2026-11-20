import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { useModuleContent, type LearnModule } from '@/api/learn'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { moduleLabel } from '@/lib/modules'

/**
 * What opens each module in the instructor's text: its objective, duration
 * and result, then "Dónde ir" ("Leyes que aplican" closes the module, before
 * its quiz: ModuleReference). Titled as the module's
 * introduction, so it is never read as the lesson, and shown under the
 * lesson's text (the owner's call: read first, the lesson). Open on the
 * module's first lesson, folded on the others, so it is one click away all along.
 */
export function ModuleOverview({ slug, module, defaultOpen }: { slug: string; module: LearnModule; defaultOpen: boolean }) {
  const { t } = useTranslation()
  const { data: content } = useModuleContent(slug, module.id)
  // The laws close the module, with its institutions and companies (ModuleReference).
  const sections = (content?.blocks ?? []).filter((block) => block.type === 'overview' && block.role !== 'laws')

  if (!content || (!content.description_html && sections.length === 0)) return null

  return (
    // Titled like every other part of the page (SectionTitle), folding under its title.
    <details className="group seccion" open={defaultOpen}>
      <summary className="flex min-h-11 cursor-pointer list-none items-start gap-3 [&::-webkit-details-marker]:hidden">
        <span className="min-w-0 flex-1">
          <span className="seccion-titulo bidi-auto block">{t('player.moduleIntroTitle', { module: moduleLabel(module, t) })}</span>
          <span className="mt-1.5 block text-sm text-muted-foreground">{t('player.moduleOverview')}</span>
        </span>
        <span aria-hidden className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-foreground/10">
          <ChevronDown className="h-4 w-4 text-primary transition-transform group-open:rotate-180" />
        </span>
      </summary>
      <div className="mt-5 space-y-5">
        {/* Said once, plainly: this is the module's opening, not the lesson. */}
        <p className="text-sm text-muted-foreground">{t('player.moduleIntroLead')}</p>
        <MarkdownContent
          html={content.description_html}
          lang={content.description_locale}
          className="rounded-2xl bg-foreground/[0.04] px-5 py-4"
        />
        {sections.length > 0 && <ContentLanguageNote locale={sections[0].body_locale} />}
        {sections.map((block) => (
          <MarkdownContent key={block.id} html={block.body_html} lang={block.body_locale} />
        ))}
      </div>
    </details>
  )
}
