import { useTranslation } from 'react-i18next'
import { CompanyDirectory } from '@/components/directory/CompanyDirectory'
import { PageHead } from '@/components/inside/PageHead'
import { usePageTitle } from '@/hooks/usePageTitle'
import { MAIN_COURSE_SLUG } from '@/lib/course'

/**
 * Partners: the whole directory of companies, for every module the student
 * owns, in one place. The same companies appear inside each module, under
 * Partners, narrowed to what that module teaches.
 */
export function PartnersPage() {
  const { t } = useTranslation()
  usePageTitle(t('partnersPage.title'))

  return (
    <div className="container py-12 sm:py-16 lg:py-20">
      <PageHead eyebrow={t('partnersPage.eyebrow')} title={t('partnersPage.title')} lead={t('partnersPage.lead')} />
      <CompanyDirectory slug={MAIN_COURSE_SLUG} />
    </div>
  )
}
