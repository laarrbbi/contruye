import { useTranslation } from 'react-i18next'
import { BusinessSettingsCard } from './BusinessSettingsCard'

/** Entreprise & contact — who the buyer pays, and how they reach a human. */
export function AdminBusinessPage() {
  const { t } = useTranslation()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">{t('admin.business.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('admin.business.subtitle')}</p>
      </div>
      <BusinessSettingsCard />
    </div>
  )
}
