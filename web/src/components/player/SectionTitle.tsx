import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * The title of each part of a lesson's page: the lesson's templates, its
 * websites, its companies, the module's introduction, checklist, reference
 * and quiz. All in one form, the form of the module summary's heading (the
 * owner's call), under a rule, so it is plain where each part starts. The
 * lesson text's own parts («Ejemplo», «Resumen», «Tu checklist») are «### »
 * titles drawn the same way (.md-content.leccion h3).
 */
export function SectionTitle({ id, lead, children, className }: { id?: string; lead?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <div className={cn('seccion', className)}>
      <h2 id={id} className="seccion-titulo bidi-auto">
        {children}
      </h2>
      {lead && <p className="mt-1.5 max-w-2xl text-sm text-muted-foreground">{lead}</p>}
    </div>
  )
}
