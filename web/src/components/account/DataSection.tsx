import { useTranslation } from 'react-i18next'
import { Download } from 'lucide-react'
import { ACCOUNT_EXPORT_URL } from '@/api/account'
import { ContactOptions } from '@/components/ContactOptions'
import { Button } from '@/components/ui/button'
import { AccountPanel } from './AccountPanel'

/** A copy of the student's own records, and how to close the account. */
export function DataSection() {
  const { t } = useTranslation()

  return (
    <div className="space-y-6">
      <AccountPanel title={t('account.data.exportTitle')} lead={t('account.data.exportLead')}>
        <Button variant="outline" asChild>
          <a href={ACCOUNT_EXPORT_URL} download>
            <Download className="h-4 w-4" aria-hidden />
            {t('account.data.export')}
          </a>
        </Button>
      </AccountPanel>

      <AccountPanel title={t('account.data.closeTitle')} lead={t('account.data.closeLead')}>
        <ContactOptions showHours={false} />
      </AccountPanel>
    </div>
  )
}
