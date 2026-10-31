import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, Globe, Lightbulb, Mail, MapPin, Phone, Scale } from 'lucide-react'
import type { LearnContentBlock, LearnPartner } from '@/api/learn'
import { useUser } from '@/api/auth'
import { joinProvinceNames } from '@/api/provinces'
import { Button } from '@/components/ui/button'
import { MarkdownContent } from '@/components/MarkdownContent'
import { PartnerProvinceFilter } from './PartnerProvinceFilter'
import { partnerCoversProvince } from './partnerFilter'

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
 * A partner slot with its province filter. Filtering is local to the block but
 * seeded from the learner's saved province, so the whole lesson lands
 * pre-filtered to where they actually build.
 */
function PartnerSlot({ block }: { block: LearnContentBlock }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const [override, setOverride] = useState<{ provinceId: number | null } | null>(null)

  const partners = block.partners ?? []
  const provinceId = override ? override.provinceId : (user?.province_id ?? null)
  const visible = partners.filter((partner) => partnerCoversProvince(partner, provinceId))
  const hidden = partners.length - visible.length
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
          {/* Withheld because the module is not owned, versus genuinely not
              recruited yet — two different truths, never conflated. */}
          {lockedCount > 0 ? t('partners.lockedTease', { count: lockedCount }) : t('content.partnerSoon')}
        </p>
      ) : (
        <>
          {/* Show the picker when it can change something — and always when
              the current filter is hiding partners, so a single-provider slot
              never becomes a dead end. */}
          {(partners.length > 1 || hidden > 0 || override !== null) && (
            <div className="mt-3">
              <PartnerProvinceFilter
                value={provinceId}
                onChange={(next) => setOverride({ provinceId: next })}
              />
            </div>
          )}

          {visible.length === 0 ? (
            // Never silently empty: say why, and offer the way back.
            <div className="mt-3 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
              <p>{t('partners.noneInProvince')}</p>
              <Button
                variant="link"
                size="sm"
                className="h-auto p-0"
                onClick={() => setOverride({ provinceId: null })}
              >
                {t('partners.showAll', { count: partners.length })}
              </Button>
            </div>
          ) : (
            <ul className="mt-3 space-y-2">
              {visible.map((partner) => (
                <PartnerRow key={partner.id} partner={partner} />
              ))}
            </ul>
          )}

          {visible.length > 0 && hidden > 0 && (
            <Button
              variant="link"
              size="sm"
              className="mt-1 h-auto p-0 text-xs"
              onClick={() => setOverride({ provinceId: null })}
            >
              {t('partners.hiddenByFilter', { count: hidden })}
            </Button>
          )}
        </>
      )}
    </section>
  )
}

function PartnerRow({ partner }: { partner: LearnPartner }) {
  const { t } = useTranslation()

  return (
    <li className="rounded-md bg-secondary/50 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className="bidi-auto font-medium">{partner.name}</span>
        <span className="flex items-center gap-1 text-xs text-muted-foreground">
          <MapPin className="h-3 w-3 shrink-0" />
          {partner.is_national
            ? t('partners.national')
            : joinProvinceNames(partner.provinces) || t('partners.noProvince')}
        </span>
      </div>
      <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        {partner.phone && (
          <a href={`tel:${partner.phone}`} dir="ltr" className="flex items-center gap-1.5 text-primary hover:underline">
            <Phone className="h-3.5 w-3.5 shrink-0" />
            {partner.phone}
          </a>
        )}
        {partner.email && (
          <a href={`mailto:${partner.email}`} dir="ltr" className="flex items-center gap-1.5 text-primary hover:underline">
            <Mail className="h-3.5 w-3.5 shrink-0" />
            {partner.email}
          </a>
        )}
        {partner.website && (
          <a href={partner.website} target="_blank" rel="noreferrer noopener" className="flex items-center gap-1.5 text-primary hover:underline">
            <Globe className="h-3.5 w-3.5 shrink-0" />
            {t('partners.website')}
          </a>
        )}
      </div>
    </li>
  )
}
