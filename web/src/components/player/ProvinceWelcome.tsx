import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { MapPin } from 'lucide-react'
import { useUpdateProfile, useUser } from '@/api/auth'
import { provinceLabel, useProvinces } from '@/api/provinces'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const LATER_KEY = 'construye.province-welcome.later'

/**
 * The first time a student opens the course without a province on their
 * profile: which province they build in. Saved on the profile, so every
 * module's «Tu provincia en esta fase» and the Partners page open on it; the
 * province list in each module still changes it. «Más tarde» asks again on
 * another day, never twice in the same browser session.
 */
export function ProvinceWelcome() {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: provinces } = useProvinces()
  const updateProfile = useUpdateProfile()
  const [choice, setChoice] = useState<string | undefined>(undefined)
  const content = useRef<HTMLDivElement>(null)
  const [later, setLater] = useState(() => {
    try {
      return sessionStorage.getItem(LATER_KEY) === '1'
    } catch {
      return false
    }
  })

  const open = !!user && user.role !== 'admin' && user.province_id == null && !later

  const postpone = () => {
    try {
      sessionStorage.setItem(LATER_KEY, '1')
    } catch {
      // Private mode: it simply asks again next time.
    }
    setLater(true)
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && postpone()}>
      <DialogContent
        ref={content}
        className="sm:max-w-md"
        // The dialog takes the focus, not the select: opened on its own, the
        // field wore its focus ring before anyone had touched it.
        onOpenAutoFocus={(event) => {
          event.preventDefault()
          content.current?.focus({ preventScroll: true })
        }}
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <MapPin className="h-5 w-5 shrink-0 text-primary" aria-hidden />
            {t('directory.welcomeTitle')}
          </DialogTitle>
          <DialogDescription>{t('directory.welcomeBody')}</DialogDescription>
        </DialogHeader>
        <Select value={choice} onValueChange={setChoice}>
          <SelectTrigger aria-label={t('directory.chooseProvince')}>
            <SelectValue placeholder={t('directory.chooseProvince')} />
          </SelectTrigger>
          <SelectContent>
            {(provinces ?? []).map((province) => (
              <SelectItem key={province.id} value={String(province.id)}>
                {provinceLabel(province)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {updateProfile.isError && <p className="text-sm text-destructive">{t('partners.saveFailed')}</p>}
        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="ghost" onClick={postpone}>
            {t('directory.welcomeLater')}
          </Button>
          <Button disabled={!choice || updateProfile.isPending} onClick={() => choice && updateProfile.mutate({ province_id: Number(choice) })}>
            {t('directory.welcomeSave')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
