import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * The head of a page inside the platform, in the sales page's voice: a small
 * label after an amber rule, a title in the site's face, an optional lead. One per page,
 * so the h1 is here.
 */
export function PageHead({
  eyebrow,
  title,
  lead,
  children,
  className,
}: {
  eyebrow?: ReactNode
  title: ReactNode
  lead?: ReactNode
  /** Actions or figures that sit with the title. */
  children?: ReactNode
  className?: string
}) {
  return (
    <header className={cn('mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between lg:mb-14', className)}>
      <div className="min-w-0">
        {eyebrow && (
          <p className="etiquette flex items-center gap-3 text-foreground/60">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {eyebrow}
          </p>
        )}
        <h1 className={cn('titre text-balance text-[clamp(2rem,1.45rem+2vw,3.25rem)]', eyebrow && 'mt-4')}>{title}</h1>
        {lead && <p className="mt-4 max-w-2xl text-pretty text-lg leading-relaxed text-foreground/70">{lead}</p>}
      </div>
      {children && <div className="shrink-0">{children}</div>}
    </header>
  )
}

/** A section's label inside a page: smaller than the page head, the same rule. */
export function SectionLabel({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <p className={cn('etiquette flex items-center gap-3 text-foreground/60', className)}>
      <span aria-hidden className="h-px w-6 shrink-0 bg-or" />
      {children}
    </p>
  )
}
