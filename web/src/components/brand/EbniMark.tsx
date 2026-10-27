import { cn } from '@/lib/utils'

/**
 * The Ebni Academy mark: a chevron above a pair of wings, together reading as
 * a diamond — the roof and the foundations.
 *
 * The outlines are traced directly from the supplied logo (Ebni-academy.png),
 * not redrawn by hand, so the silhouette is the real one. `currentColor` lets
 * it sit on any surface — brand blue in the header, white on navy, a faint
 * watermark on the bento tiles.
 */
export function EbniMark({ className, title }: { className?: string; title?: string }) {
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
        <path
          d="M1970 4063 c-19 -8 -176 -126 -350 -261 -173 -135 -592 -461 -930
-725 -338 -263 -619 -488 -623
          -500 -14 -39 188 -204 353 -287 125 -63 255
-75 352 -33 39 18 258 186 938 718 287 225 291 227 388
          205 56 -12 28 9 941
-704 274 -214 312 -237 412 -244 110 -8 214 32 385 146 164 111 225 178
          196
217 -9 13 -170 140 -822 645 -272 211 -565 438 -650 505 -439 343 -427 336
-507 334 -26 0 -64
          -7 -83 -16z"
        />
        {/* foundations */}
        <path
          d="M36 1873 c-3 -15 -4 -138 -3 -273 3 -233 4 -248 26 -293 34 -70 87
-110 204 -153 227 -83 853 -337
          1012 -411 394 -182 624 -371 744 -610 17 -35
33 -63 36 -63 3 0 19 28 36 63 125 246 355 433 771
          623 126 58 640 266 985
398 143 55 191 97 217 188 17 58 21 544 5 554 -16 9 -682 -264 -1019
          -419
-566 -260 -818 -450 -954 -722 -43 -84 -43 -84 -65 -30 -80 193 -290 395 -576
555 -228 127
          -628 307 -1160 523 -271 109 -252 104 -259 70z"
        />
      </g>
    </svg>
  )
}

/** Brand blue from the supplied artwork. */
export const EBNI_BLUE = '#006FFE'

/**
 * Mark + wordmark.
 *
 * `brand` reproduces the artwork's own colours (blue mark, navy EBNI, blue
 * ACADEMY) for light surfaces; `inherit` paints everything in currentColor so
 * it stays legible on the navy panels.
 */
export function EbniLogo({
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
      <EbniMark className={cn('h-8 w-8 shrink-0', brand && 'text-[#006FFE]')} />
      {!compact && (
        // Decorative: the link around the logo carries the accessible name,
        // and a screen reader would otherwise spell "EBNI ACADEMY" letter-spaced.
        <span className={cn('flex flex-col leading-none', wordmarkClassName)} aria-hidden>
          <span className={cn('text-lg tracking-[0.22em]', brand && 'text-[#001B4C]')}>EBNI</span>
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
