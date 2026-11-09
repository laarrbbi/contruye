import { useTranslation } from 'react-i18next'
import type { LearnPartner } from '@/api/learn'
import { useLearn } from '@/api/learn'
import { CompanyDirectory } from '@/components/directory/CompanyDirectory'
import { PageHead, SectionLabel } from '@/components/inside/PageHead'
import { PartnerList } from '@/components/player/PartnerList'
import { usePageTitle } from '@/hooks/usePageTitle'
import { MAIN_COURSE_SLUG } from '@/lib/course'

/**
 * Partners: everything for every module the student owns, in one place — the
 * partners the academy recommends (each once, whatever the number of modules
 * it serves), then the whole directory of companies. The same appear inside
 * each module, under Partners, narrowed to what that module teaches.
 */
export function PartnersPage() {
  const { t } = useTranslation()
  usePageTitle(t('partnersPage.title'))
  const { data } = useLearn(MAIN_COURSE_SLUG)

  const recommended = [
    ...new Map(
      (data?.modules ?? [])
        .filter((module) => !module.is_locked)
        .flatMap((module) => module.partners)
        .map((partner): [number, LearnPartner] => [partner.id, { ...partner, note: null }]),
    ).values(),
  ]

  return (
    <div className="container py-12 sm:py-16 lg:py-20">
      <PageHead eyebrow={t('partnersPage.eyebrow')} title={t('partnersPage.title')} lead={t('partnersPage.lead')} />
      {recommended.length > 0 && (
        <section className="mb-12 space-y-3">
          <SectionLabel>{t('partners.title')}</SectionLabel>
          <p className="max-w-2xl text-sm text-foreground/70">{t('content.noCommission')}</p>
          <PartnerList partners={recommended} />
        </section>
      )}
      <CompanyDirectory slug={MAIN_COURSE_SLUG} />
    </div>
  )
}
