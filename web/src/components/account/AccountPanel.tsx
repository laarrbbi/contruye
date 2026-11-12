import type { ReactNode } from 'react'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

/**
 * One block of the account page: a title, an optional line under it, and its
 * content on a quiet panel. The same shape for every section, so the page
 * reads as one document.
 */
export function AccountPanel({
  title,
  lead,
  children,
  actions,
  bare = false,
  className,
}: {
  title: ReactNode
  lead?: ReactNode
  children: ReactNode
  actions?: ReactNode
  /** Without the panel: for content that brings its own cards. */
  bare?: boolean
  className?: string
}) {
  return (
    <section className={cn(!bare && 'rounded-2xl border border-foreground/10 bg-white p-5 sm:p-7', className)}>
      <header className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0">
          <h2 className="text-lg font-semibold leading-snug">{title}</h2>
          {lead && <p className="mt-1.5 max-w-xl text-pretty text-sm leading-relaxed text-foreground/65">{lead}</p>}
        </div>
        {actions && <div className="shrink-0">{actions}</div>}
      </header>
      {children}
    </section>
  )
}

/** A labelled field with its hint and its error under it. */
export function Field({
  id,
  label,
  hint,
  error,
  children,
  className,
}: {
  id: string
  label: ReactNode
  hint?: ReactNode
  error?: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={id}>{label}</Label>
      {children}
      {error ? (
        <p id={`${id}-error`} className="text-sm text-destructive">
          {error}
        </p>
      ) : (
        hint && (
          <p id={`${id}-hint`} className="text-xs leading-relaxed text-foreground/55">
            {hint}
          </p>
        )
      )}
    </div>
  )
}

/** "Guardado." after a save, read out by screen readers, gone on the next edit. */
export function SavedNote({ show, children }: { show: boolean; children: ReactNode }) {
  return (
    <p role="status" className={cn('text-sm text-foreground/60 transition-opacity duration-200', show ? 'opacity-100' : 'opacity-0')}>
      {show ? children : null}
    </p>
  )
}
