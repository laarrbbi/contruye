import { cn } from '@/lib/utils'
import { MARK_FOUNDATIONS, MARK_ROOF } from './markPaths'


/**
 * The Academy mark: a chevron above a pair of wings, together reading as a
 * diamond — the roof and the foundations. It is the Ebni Academy mark, kept
 * for CONSTRUYE at the owner's request.
 *
 * The outlines are traced from the supplied logo, not redrawn by hand, so the
 * silhouette is the real one. `currentColor` lets it sit on any surface —
 * brand blue in the header, white on navy, a faint watermark.
 */
export function ConstruyeMark({ className, title }: { className?: string; title?: string }) {
  return (
    <svg
      viewBox="0 0 411 415"
      className={cn('h-6 w-6', className)}
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : true}
    >
      {title && <title>{title}</title>}
      <g transform="translate(0,415) scale(0.1,-0.1)" fill="currentColor" stroke="none">
        {/* roof */}
        <path d={MARK_ROOF} />
        {/* foundations */}
        <path d={MARK_FOUNDATIONS} />
      </g>
    </svg>
  )
}

/** Brand blue. */
export const CONSTRUYE_BLUE = '#006FFE'

/**
 * Mark + wordmark.
 *
 * `brand` uses the brand colours (blue mark, navy CONSTRUYE, blue ACADEMY) for
 * light surfaces; `inherit` paints everything in currentColor so it stays
 * legible on the navy panels.
 */
export function ConstruyeLogo({
  className,
  compact = false,
  variant = 'brand',
  wordmarkClassName,
}: {
  className?: string
  compact?: boolean
  variant?: 'brand' | 'inherit'
  /** e.g. hide the wordmark on the narrowest phones, keeping the mark. */
  wordmarkClassName?: string
}) {
  const brand = variant === 'brand'

  return (
    <span className={cn('flex items-center gap-2.5 font-bold', className)}>
      <ConstruyeMark className={cn('h-8 w-8 shrink-0', brand && 'text-[#006FFE]')} />
      {!compact && (
        // Decorative: the link around the logo carries the accessible name,
        // and a screen reader would otherwise spell "CONSTRUYE ACADEMY" letter-spaced.
        <span className={cn('flex flex-col leading-none', wordmarkClassName)} aria-hidden>
          <span className={cn('text-lg tracking-[0.22em]', brand && 'text-[#001B4C]')}>CONSTRUYE</span>
          <span
            className={cn(
              'mt-0.5 text-[0.58rem] tracking-[0.32em]',
              brand ? 'text-[#006FFE]' : 'opacity-80',
            )}
          >
            ACADEMY
          </span>
        </span>
      )}
    </span>
  )
}
