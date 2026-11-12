import { useEffect, useMemo, type ReactNode, type SyntheticEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useUser } from '@/api/auth'
import { cn } from '@/lib/utils'

/**
 * Fields the student types in (a calculator, a task) stay theirs to select and
 * copy, and so do a company's phone and e-mail (`allow-select`): contacting it
 * is what they are there for.
 */
function isEditable(target: EventTarget | null) {
  const element = target instanceof Element ? target : target instanceof Node ? target.parentElement : null
  return element?.closest('input, textarea, select, [contenteditable="true"], .allow-select') != null
}

/** The reader's e-mail, faint and slanted, repeated as a pattern behind the text. */
function watermark(text: string) {
  const safe = text.replace(/[<>&"']/g, '')
  const svg =
    `<svg xmlns="http://www.w3.org/2000/svg" width="360" height="220">` +
    `<text x="180" y="110" text-anchor="middle" transform="rotate(-24 180 110)" ` +
    `font-family="-apple-system, BlinkMacSystemFont, Inter, sans-serif" font-size="13" ` +
    `fill="#141a22" fill-opacity="0.07">${safe}</text></svg>`
  return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`
}

/**
 * Where the course material is read. What a browser lets a page refuse, it
 * refuses here: selecting the text, copying or cutting it, the context menu
 * ("save as", "print"), dragging it out, and the save, print and view-source
 * shortcuts; printed, the page shows a notice instead of the text. Behind
 * the text, the reader's e-mail in a faint pattern, so a screenshot carries
 * its owner's name; the server also marks the text itself, invisibly
 * (ContentFingerprint).
 *
 * None of this stops someone retyping what is on their screen. It stops the
 * two-second copy, and makes any copy traceable.
 */
export function ProtectedArea({ children, className }: { children: ReactNode; className?: string }) {
  const { t } = useTranslation()
  const { data: user } = useUser()
  const background = useMemo(() => (user?.email ? watermark(user.email) : undefined), [user?.email])

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if (!(event.ctrlKey || event.metaKey) || isEditable(event.target)) return
      if (['s', 'p', 'u'].includes(event.key.toLowerCase())) event.preventDefault()
    }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [])

  const refuse = (event: SyntheticEvent) => {
    if (!isEditable(event.target)) event.preventDefault()
  }

  return (
    <>
      {/* First, so on paper it takes the material's place. */}
      <p className="course-protected-print-note">{t('protection.printNote')}</p>
      <div
        className={cn('course-protected', className)}
        style={background ? { backgroundImage: background } : undefined}
        onCopy={refuse}
        onCut={refuse}
        onContextMenu={refuse}
        onDragStart={refuse}
      >
        {children}
      </div>
    </>
  )
}
