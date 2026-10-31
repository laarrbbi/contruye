import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Lock, MapPin } from 'lucide-react'
import { useUpdateProfile, useUser } from '@/api/auth'
import { useProvinceDirectory } from '@/api/learn'
import { provinceLabel, useProvinces } from '@/api/provinces'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'

/**
 * "Partners en tu provincia": the rows of Anexo A for this module's phase, in
 * the student's province, with the province's planning law and taxes. The
 * province is the one saved on the profile; changing it here saves it too,
 * so every module follows.
 */
export function ProvinceGuidePanel({ slug, phase, showAnnexLink }: { slug: string; phase: number; showAnnexLink: boolean }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const { data: provinces } = useProvinces()
  const updateProfile = useUpdateProfile()
  const [override, setOverride] = useState<number | null>(null)

  const provinceId = override ?? user?.province_id ?? null
  const directory = useProvinceDirectory(slug, provinceId)
  const rows = directory.data?.phases.find((entry) => entry.phase === phase)

  const choose = (value: string) => {
    const id = Number(value)
    setOverride(id)
    if (user && user.province_id !== id) updateProfile.mutate({ province_id: id })
  }

  return (
    <section className="rounded-lg border">
      <div className="flex flex-wrap items-center gap-3 border-b px-4 py-3">
        <MapPin className="h-4 w-4 shrink-0 text-primary" />
        <h2 className="flex-1 text-sm font-semibold">{t('directory.panelTitle')}</h2>
        <Select value={provinceId === null ? undefined : String(provinceId)} onValueChange={choose}>
          <SelectTrigger className="h-8 w-[200px] text-sm" aria-label={t('directory.chooseProvince')}>
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
      </div>

      <div className="space-y-3 px-4 py-3 text-sm">
        {provinceId === null ? (
          <p className="text-muted-foreground">{t('directory.chooseHint')}</p>
        ) : directory.isPending ? (
          <Skeleton className="h-24 w-full" />
        ) : directory.isError || !directory.data ? (
          <p className="text-muted-foreground">{t('common.error')}</p>
        ) : (
          <>
            <dl className="grid gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-[auto_1fr]">
              <dt className="font-medium text-foreground">{t('directory.urbanLaw')}</dt>
              <dd>{directory.data.urban_law}</dd>
              <dt className="font-medium text-foreground">{t('directory.firstOccupation')}</dt>
              <dd>
                {directory.data.first_occupation}
                {directory.data.habitability_certificate && ` · ${directory.data.habitability_certificate}`}
              </dd>
              <dt className="font-medium text-foreground">{t('directory.taxes')}</dt>
              <dd>
                ITP {directory.data.itp} · AJD {directory.data.ajd} · {directory.data.sale_tax}
              </dd>
              {directory.data.foral && (
                <>
                  <dt className="font-medium text-foreground">{t('directory.foral')}</dt>
                  <dd>{directory.data.foral}</dd>
                </>
              )}
            </dl>

            {rows && rows.rows.length > 0 ? (
              <div className="md-content">
                <table>
                  <thead>
                    <tr>
                      <th>{t('directory.partner')}</th>
                      <th>{t('directory.purpose')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.rows.map((row, index) => (
                      <tr key={index}>
                        <td className="font-medium">{row.name}</td>
                        <td>{row.purpose}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : rows?.is_locked ? (
              <p className="flex items-center gap-2 text-muted-foreground">
                <Lock className="h-3.5 w-3.5" />
                {t('directory.lockedPhase', { count: rows.rows_count })}
              </p>
            ) : null}

            {showAnnexLink && (
              <Link to={`/learn/${slug}/docs/anexo-a`} className="inline-block text-xs font-medium text-primary underline underline-offset-2">
                {t('directory.openAnnex')}
              </Link>
            )}
          </>
        )}
      </div>
    </section>
  )
}
