import { useTranslation } from 'react-i18next'
import { Building2, Lightbulb, Scale } from 'lucide-react'
import type { LearnContentBlock } from '@/api/learn'
import { MarkdownContent } from '@/components/MarkdownContent'
import { PartnerList } from './PartnerList'

/**
 * Renders the tagged blocks of the programme:
 * - `legal`        → the statutory references behind the lesson
 * - `expert_tip`   → a practitioner's "conseil d'or"
 * - `partner_slot` → real attached partners, or an honest placeholder. A slot
 *                    with no partner NEVER shows an invented contact.
 * - `overview`, `section`, `text` → the instructor's Markdown, rendered.
 */
export function ContentBlocks({ blocks }: { blocks: LearnContentBlock[] }) {
  const { t } = useTranslation()

  if (blocks.length === 0) return null

  return (
    <div className="space-y-3">
      {blocks.map((block) => {
        if (block.type === 'legal') {
          return (
            <section
              key={block.id}
              className="rounded-lg border border-primary/25 bg-primary/5 p-4"
              aria-label={t('content.legalBasis')}
            >
              <p className="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-primary">
                <Scale className="h-3.5 w-3.5 shrink-0" />
                {t('content.legalBasis')}
              </p>
              <p className="bidi-auto text-sm italic leading-relaxed text-muted-foreground">{block.body}</p>
            </section>
          )
        }

        if (block.type === 'expert_tip') {
          return (
            <section key={block.id} className="rounded-lg border border-warning/40 bg-warning/10 p-4">
              <p className="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-warning-foreground">
                <Lightbulb className="h-3.5 w-3.5 shrink-0" />
                {t('content.expertTip')}
              </p>
              <p className="bidi-auto text-sm font-medium leading-relaxed">{block.body}</p>
            </section>
          )
        }

        if (block.type === 'partner_slot') {
          return <PartnerSlot key={block.id} block={block} />
        }

        return <MarkdownContent key={block.id} html={block.body_html} />
      })}
    </div>
  )
}

/**
 * A partner slot: the partners attached to the module, or what stands in for
 * them — the count when the module is not owned, "coming soon" when none is
 * recruited yet. Two different truths, never conflated.
 */
function PartnerSlot({ block }: { block: LearnContentBlock }) {
  const { t } = useTranslation()

  const partners = block.partners ?? []
  // The API sends the count even when it withholds the contacts, so a locked
  // module can say what it holds instead of pretending it holds nothing.
  const lockedCount = partners.length === 0 ? (block.partners_count ?? 0) : 0

  return (
    <section className="rounded-lg border p-4">
      <p className="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        <Building2 className="h-3.5 w-3.5 shrink-0" />
        {t('content.partnerNeeded')}
      </p>
      <p className="bidi-auto text-sm leading-relaxed text-muted-foreground">{block.body}</p>
      <p className="mt-1 text-xs text-muted-foreground">{t('content.noCommission')}</p>

      {partners.length === 0 ? (
        <p className="mt-3 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
          {lockedCount > 0 ? t('partners.lockedTease', { count: lockedCount }) : t('content.partnerSoon')}
        </p>
      ) : (
        <PartnerList partners={partners} />
      )}
    </section>
  )
}
