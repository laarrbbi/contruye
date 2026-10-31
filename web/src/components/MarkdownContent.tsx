import { isRtl } from '@/i18n'
import { cn } from '@/lib/utils'

/**
 * Course text rendered from the instructor's Markdown: tables, bullets, bold,
 * task lists. The HTML comes from the API (App\Support\Markdown), which strips
 * any raw HTML and unsafe link from the source, so it is set as is.
 *
 * `lang` is the language the text is actually in, which can differ from the
 * page's while a translation is pending: Spanish inside an Arabic page keeps
 * its own direction and is read aloud in Spanish.
 */
export function MarkdownContent({ html, className, lang }: { html: string | null; className?: string; lang?: string }) {
  if (!html) return null

  return (
    <div
      className={cn('md-content', className)}
      lang={lang}
      dir={lang ? (isRtl(lang) ? 'rtl' : 'ltr') : undefined}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  )
}
