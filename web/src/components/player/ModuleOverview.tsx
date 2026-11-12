import { useTranslation } from 'react-i18next'
import { BookOpen, ChevronDown } from 'lucide-react'
import { useModuleContent, type LearnModule } from '@/api/learn'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { moduleLabel } from '@/lib/modules'

/**
 * What opens each module in the instructor's text: its objective, duration
 * and result, then "Leyes que aplican" and "Dónde ir". Titled as the module's
 * introduction, so it is never read as the lesson. Open on the module's first
 * lesson, folded on the others, so it is one click away all along.
 */
export function ModuleOverview({ slug, module, defaultOpen }: { slug: string; module: LearnModule; defaultOpen: boolean }) {
  const { t } = useTranslation()
  const { data: content } = useModuleContent(slug, module.id)
  const sections = (content?.blocks ?? []).filter((block) => block.type === 'overview')

  if (!content || (!content.description_html && sections.length === 0)) return null

  return (
    <details className="group rounded-lg border" open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center gap-2 px-4 py-3 [&::-webkit-details-marker]:hidden">
        <BookOpen className="h-4 w-4 shrink-0 text-primary" />
        <span className="flex-1">
          <span className="block text-sm font-semibold">{t('player.moduleIntroTitle', { module: moduleLabel(module, t) })}</span>
          <span className="block text-xs text-muted-foreground">{t('player.moduleOverview')}</span>
        </span>
        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
      </summary>
      <div className="space-y-5 border-t px-4 py-4">
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
