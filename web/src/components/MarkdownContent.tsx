import { cn } from '@/lib/utils'

/**
 * Course text rendered from the instructor's Markdown: tables, bullets, bold,
 * task lists. The HTML comes from the API (App\Support\Markdown), which strips
 * any raw HTML and unsafe link from the source, so it is set as is.
 */
export function MarkdownContent({ html, className }: { html: string | null; className?: string }) {
  if (!html) return null

  return <div className={cn('md-content', className)} dangerouslySetInnerHTML={{ __html: html }} />
}
