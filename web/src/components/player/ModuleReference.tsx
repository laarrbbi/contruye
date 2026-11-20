import { useTranslation } from 'react-i18next'
import { Library } from 'lucide-react'
import type { LearnContentBlock, LearnModule } from '@/api/learn'
import { MarkdownContent } from '@/components/MarkdownContent'
import { ContentLanguageNote } from '@/components/player/ContentLanguageNote'
import { ProvinceGuidePanel } from '@/components/player/ProvinceGuidePanel'
import { moduleLabel } from '@/lib/modules'

/**
 * The end of a module, after its checklist and before its quiz (the owner's
 * call): the laws that apply and the institutions and companies of the
 * student's province for this phase, in one section, said plainly to be the
 * complete list, there to look things up, not to study.
 */
export function ModuleReference({
  slug,
  module,
  laws,
  partners,
  showAnnexLink,
}: {
  slug: string
  module: LearnModule
  laws: LearnContentBlock[]
  partners: LearnContentBlock[]
  showAnnexLink: boolean
}) {
  const { t } = useTranslation()
  const blocks = [...laws, ...partners]

  if (blocks.length === 0 && module.kind !== 'phase') return null

  return (
    <section aria-labelledby={`referencia-${module.id}`} className="space-y-5 rounded-2xl border border-foreground/[0.08] bg-card p-5 sm:p-6">
      <header className="flex items-start gap-3">
        <span aria-hidden className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Library className="h-5 w-5" />
        </span>
        <div className="min-w-0">
          <h2 id={`referencia-${module.id}`} className="text-base font-bold leading-snug">
            {t('player.referenceTitle', { module: moduleLabel(module, t) })}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">{t('player.referenceLead')}</p>
        </div>
      </header>
      {blocks.length > 0 && <ContentLanguageNote locale={blocks[0].body_locale} />}
      {blocks.map((block) => (
        <MarkdownContent key={block.id} html={block.body_html} lang={block.body_locale} />
      ))}
      {module.kind === 'phase' && <ProvinceGuidePanel slug={slug} phase={module.order} showAnnexLink={showAnnexLink} />}
    </section>
  )
}
