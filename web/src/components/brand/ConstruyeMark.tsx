import { cn } from '@/lib/utils'

/**
 * The Construye Academy mark: a roof over three floors that are still going
 * up — the building the course follows, from the plot to the keys.
 *
 * Drawn for this site (no artwork has been supplied yet), on a 64-unit grid so
 * it stays crisp at favicon size. `currentColor` lets it sit on any surface —
 * brand blue in the header, white on navy.
 */
export function ConstruyeMark({ className, title }: { className?: string; title?: string }) {
  return (
    <svg
      viewBox="0 0 64 64"
      className={cn('h-6 w-6', className)}
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : true}
    >
      {title && <title>{title}</title>}
      <g fill="currentColor">
        {/* roof */}
        <path d="M32 4 61 26.5 56.6 32.2 32 13.1 7.4 32.2 3 26.5Z" />
        {/* floors, the last one still being built */}
        <rect x="12" y="35" width="40" height="7" rx="1.5" />
        <rect x="12" y="45.5" width="32" height="7" rx="1.5" />
        <rect x="12" y="56" width="22" height="7" rx="1.5" />
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
