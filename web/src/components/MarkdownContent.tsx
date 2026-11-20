import { useMemo, type MouseEvent } from 'react'
import { isRtl } from '@/i18n'
import { cn } from '@/lib/utils'
import { useCourseReferences } from '@/components/course/references'

/**
 * Course text rendered from the instructor's Markdown: tables, bullets, bold,
 * task lists. The HTML comes from the API (App\Support\Markdown), which strips
 * any raw HTML and unsafe link from the source, so it is set as is.
 *
 * `lang` is the language the text is actually in, which can differ from the
 * page's while a translation is pending: Spanish inside an Arabic page keeps
 * its own direction and is read aloud in Spanish.
 *
 * A reference to another lesson or module («Lección 4.2») comes as a link
 * that carries its number; inside the course (CourseReferencesProvider) it
 * opens that lesson, elsewhere it reads as plain text.
 */
export function MarkdownContent({ html, className, lang }: { html: string | null; className?: string; lang?: string }) {
  // Each table in a box of its own that scrolls sideways (.md-table), so a
  // table of many columns can be laid out wider than a phone instead of
  // squeezing every column to a word a line. Only the markup around the table
  // changes; not a character of the text.
  const framed = useMemo(() => html?.replace(/<table\b/g, '<div class="md-table"><table').replace(/<\/table>/g, '</table></div>') ?? null, [html])
  const openReference = useCourseReferences()
  if (!framed) return null

  const onClick = (event: MouseEvent<HTMLDivElement>) => {
    const link = (event.target as Element).closest?.('a.ref-curso') as HTMLAnchorElement | null
    if (!link) return
    event.preventDefault()
    openReference?.({ lesson: link.dataset.lesson, module: link.dataset.module ? Number(link.dataset.module) : undefined })
  }

  return (
    // The links inside keep their own keyboard behaviour (Enter clicks a link).
    <div
      onClick={onClick}
      className={cn('md-content', openReference && 'refs-on', className)}
      lang={lang}
      dir={lang ? (isRtl(lang) ? 'rtl' : 'ltr') : undefined}
      dangerouslySetInnerHTML={{ __html: framed }}
    />
  )
}
