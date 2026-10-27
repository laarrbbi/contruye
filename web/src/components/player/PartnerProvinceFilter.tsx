import { useTranslation } from 'react-i18next'
import { MapPin } from 'lucide-react'
import { useUpdateProfile, useUser } from '@/api/auth'
import { useProvinces, provinceLabel } from '@/api/provinces'
import { ALL_PROVINCES } from './partnerFilter'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * "Ma province" picker shown above a partner list. The choice is saved on the
 * learner's profile, so it holds across modules, sessions and devices — a
 * promoter builds in one province, they should not re-pick it on every lesson.
 */
export function PartnerProvinceFilter({
  value,
  onChange,
  showLabel = true,
}: {
  value: number | null
  onChange: (provinceId: number | null) => void
  /** Off where the surrounding card already names it. */
  showLabel?: boolean
}) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: provinces, isPending: provincesPending } = useProvinces()
  const updateProfile = useUpdateProfile()

  const handleChange = (next: string) => {
    const provinceId = next === ALL_PROVINCES ? null : Number(next)
    onChange(provinceId)

    // Remember it, but never let a failed save block the filtering — the
    // list is already filtered locally either way.
    if (user && user.province_id !== provinceId) {
      updateProfile.mutate({ province_id: provinceId })
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {showLabel && (
        <>
          <MapPin className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
          <span className="text-xs text-muted-foreground">{t('partners.filterLabel')}</span>
        </>
      )}
      <Select value={value === null ? ALL_PROVINCES : String(value)} onValueChange={handleChange}>
        <SelectTrigger className="h-8 w-[190px] text-sm" aria-label={t('partners.filterLabel')}>
          {/* The list may still be loading (or have failed): never render an
              empty trigger next to an already-filtered list. */}
          <SelectValue placeholder={provincesPending ? t('partners.loadingProvinces') : t('partners.allProvinces')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL_PROVINCES}>{t('partners.allProvinces')}</SelectItem>
          {(provinces ?? []).map((province) => (
            <SelectItem key={province.id} value={String(province.id)}>
              {provinceLabel(province)}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {updateProfile.isError && (
        <span className="text-xs text-muted-foreground">{t('partners.saveFailed')}</span>
      )}
    </div>
  )
}
