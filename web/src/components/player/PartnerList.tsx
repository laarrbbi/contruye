import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Globe, Mail, MapPin, Phone } from 'lucide-react'
import type { LearnPartner } from '@/api/learn'
import { useUser } from '@/api/auth'
import { joinProvinceNames } from '@/api/provinces'
import { Button } from '@/components/ui/button'
import { PartnerProvinceFilter } from './PartnerProvinceFilter'
import { partnerCoversProvince } from './partnerFilter'

/**
 * Recommended partners with their province filter. The filter starts on the
 * student's saved province, so the list lands on where they build; it is never
 * a dead end: when it hides everyone, it says so and offers the whole list.
 */
export function PartnerList({ partners }: { partners: LearnPartner[] }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const [override, setOverride] = useState<{ provinceId: number | null } | null>(null)

  const provinceId = override ? override.provinceId : (user?.province_id ?? null)
  const visible = partners.filter((partner) => partnerCoversProvince(partner, provinceId))
  const hidden = partners.length - visible.length

  return (
    <>
      {/* Show the picker when it can change something — and always when the
          current filter is hiding partners. */}
      {(partners.length > 1 || hidden > 0 || override !== null) && (
        <div className="mt-3">
          <PartnerProvinceFilter value={provinceId} onChange={(next) => setOverride({ provinceId: next })} />
        </div>
      )}

      {visible.length === 0 ? (
        <div className="mt-3 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
          <p>{t('partners.noneInProvince')}</p>
          <Button variant="link" size="sm" className="h-auto p-0" onClick={() => setOverride({ provinceId: null })}>
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
        <Button variant="link" size="sm" className="mt-1 h-auto p-0 text-xs" onClick={() => setOverride({ provinceId: null })}>
          {t('partners.hiddenByFilter', { count: hidden })}
        </Button>
      )}
    </>
  )
}

function PartnerRow({ partner }: { partner: LearnPartner }) {
  const { t } = useTranslation()

  return (
    <li className="rounded-md bg-secondary/50 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className="bidi-auto font-medium">{partner.name}</span>
        {partner.category && (
          <span className="rounded-full bg-background px-2 py-0.5 text-xs text-muted-foreground">
            {t(`partners.category.${partner.category}`)}
          </span>
        )}
        <span className="flex items-center gap-1 text-xs text-muted-foreground">
          <MapPin className="h-3 w-3 shrink-0" />
          {partner.is_national ? t('partners.national') : joinProvinceNames(partner.provinces) || t('partners.noProvince')}
        </span>
      </div>
      {partner.note && <p className="bidi-auto mt-1 text-sm">{partner.note}</p>}
      {partner.description && <p className="bidi-auto mt-1 text-sm text-muted-foreground">{partner.description}</p>}
      <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        {partner.phone && (
          <a href={`tel:${partner.phone}`} dir="ltr" className="allow-select flex items-center gap-1.5 text-primary hover:underline">
            <Phone className="h-3.5 w-3.5 shrink-0" />
            {partner.phone}
          </a>
        )}
        {partner.email && (
          <a href={`mailto:${partner.email}`} dir="ltr" className="allow-select flex items-center gap-1.5 text-primary hover:underline">
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
