import { cn } from '@/lib/utils'

/**
 * A placeholder shaped like the content that will replace it.
 *
 * A centred "loading…" line is a different layout from the loaded page, so
 * every load ends in a jump. Matching the shape removes the jump, which reads
 * as the page being faster even though nothing about the request changed.
 *
 * The shimmer is opacity-only, so it survives reduced motion as a gentle fade
 * rather than movement.
 */
export function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return <div aria-hidden className={cn('animate-pulse rounded-md bg-muted', className)} {...props} />
}

/** Screen-reader announcement to pair with a visual skeleton. */
export function LoadingAnnouncement({ label }: { label: string }) {
  return (
    <span role="status" aria-live="polite" className="sr-only">
      {label}
    </span>
  )
}
