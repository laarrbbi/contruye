import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { PageTitleContext, type PageHead } from '@/hooks/usePageTitle'

/**
 * Writes document.title and the meta description from what the page declared
 * and the current language, so switching language retitles the page too.
 */
export function PageTitleProvider({ children }: { children: ReactNode }) {
  const { t, i18n } = useTranslation()
  const [head, setHead] = useState<PageHead>({ title: null, description: null })

  useEffect(() => {
    document.title = head.title ? `${head.title} — ${t('app.name')}` : t('app.title')
    document
      .querySelector('meta[name="description"]')
      ?.setAttribute('content', head.description ?? t('app.metaDescription'))
  }, [head, t, i18n.language])

  return <PageTitleContext.Provider value={setHead}>{children}</PageTitleContext.Provider>
}
