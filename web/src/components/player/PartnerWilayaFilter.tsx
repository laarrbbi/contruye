import { useTranslation } from 'react-i18next'
import { MapPin } from 'lucide-react'
import { useUpdateProfile, useUser } from '@/api/auth'
import { useWilayas, wilayaLabel } from '@/api/wilayas'
import { ALL_WILAYAS } from './partnerFilter'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * "Ma wilaya" picker shown above a partner list. The choice is saved on the
 * learner's profile, so it holds across modules, sessions and devices — a
 * promoter builds in one wilaya, they should not re-pick it on every lesson.
 */
export function PartnerWilayaFilter({
  value,
  onChange,
  showLabel = true,
}: {
  value: number | null
  onChange: (wilayaId: number | null) => void
  /** Off where the surrounding card already names it. */
  showLabel?: boolean
}) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: wilayas, isPending: wilayasPending } = useWilayas()
  const updateProfile = useUpdateProfile()

  const handleChange = (next: string) => {
    const wilayaId = next === ALL_WILAYAS ? null : Number(next)
    onChange(wilayaId)

    // Remember it, but never let a failed save block the filtering — the
    // list is already filtered locally either way.
    if (user && user.wilaya_id !== wilayaId) {
      updateProfile.mutate({ wilaya_id: wilayaId })
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
      <Select value={value === null ? ALL_WILAYAS : String(value)} onValueChange={handleChange}>
        <SelectTrigger className="h-8 w-[190px] text-sm" aria-label={t('partners.filterLabel')}>
          {/* The list may still be loading (or have failed): never render an
              empty trigger next to an already-filtered list. */}
          <SelectValue placeholder={wilayasPending ? t('partners.loadingWilayas') : t('partners.allWilayas')} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL_WILAYAS}>{t('partners.allWilayas')}</SelectItem>
          {(wilayas ?? []).map((wilaya) => (
            <SelectItem key={wilaya.id} value={String(wilaya.id)}>
              {wilayaLabel(wilaya)}
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
