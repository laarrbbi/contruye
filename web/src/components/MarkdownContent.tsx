import { useMemo } from 'react'
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
  // Each table in a box of its own that scrolls sideways (.md-table), so a
  // table of many columns can be laid out wider than a phone instead of
  // squeezing every column to a word a line. Only the markup around the table
  // changes; not a character of the text.
  const framed = useMemo(
    () => html?.replace(/<table\b/g, '<div class="md-table"><table').replace(/<\/table>/g, '</table></div>') ?? null,
    [html],
  )
  if (!framed) return null

  return (
    <div
      className={cn('md-content', className)}
      lang={lang}
      dir={lang ? (isRtl(lang) ? 'rtl' : 'ltr') : undefined}
      dangerouslySetInnerHTML={{ __html: framed }}
    />
  )
}
