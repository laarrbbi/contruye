import { useTranslation } from 'react-i18next'
import { Languages } from 'lucide-react'

/**
 * One line above a course text read in another language than Spanish: either
 * the text is still the Spanish original (its translation is pending), or it
 * is a translation, and the instructor's Spanish stays the reference for every
 * law, figure and deadline. Nothing to say to a Spanish reader.
 */
export function ContentLanguageNote({ locale }: { locale: string }) {
  const { t, i18n } = useTranslation()

  if (i18n.language === 'es') return null

  return (
    <p className="flex items-start gap-2 text-xs text-muted-foreground">
      <Languages className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden />
      <span>{locale === 'es' ? t('content.inSpanish') : t('content.draftTranslation')}</span>
    </p>
  )
}
