import { useTranslation } from 'react-i18next'
import { ChevronDown, Scale } from 'lucide-react'
import type { LearnModule } from '@/api/learn'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { moduleLabel } from '@/lib/modules'

/**
 * What opens each module in the instructor's text: its objective, duration
 * and result, then "Leyes que aplican" and "Dónde ir". Open on the module's
 * first lesson, folded on the others, so it is one click away all along.
 */
export function ModuleOverview({ module, defaultOpen }: { module: LearnModule; defaultOpen: boolean }) {
  const { t } = useTranslation()
  const sections = module.blocks.filter((block) => block.type === 'overview')

  if (!module.description_html && sections.length === 0) return null

  return (
    <details className="group rounded-lg border" open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center gap-2 px-4 py-3 [&::-webkit-details-marker]:hidden">
        <Scale className="h-4 w-4 shrink-0 text-primary" />
        <span className="flex-1 text-sm font-semibold">
          {moduleLabel(module, t)} · {t('player.moduleOverview')}
        </span>
        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
      </summary>
      <div className="space-y-5 border-t px-4 py-4">
        <MarkdownContent
          html={module.description_html}
          lang={module.description_locale}
          className="rounded-lg bg-primary/5 px-4 py-3"
        />
        {sections.length > 0 && <ContentLanguageNote locale={sections[0].body_locale} />}
        {sections.map((block) => (
          <MarkdownContent key={block.id} html={block.body_html} lang={block.body_locale} />
        ))}
      </div>
    </details>
  )
}
