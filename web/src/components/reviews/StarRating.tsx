import { Star } from 'lucide-react'
import { cn } from '@/lib/utils'

const SIZES = {
  sm: 'h-3.5 w-3.5',
  md: 'h-4 w-4',
  lg: 'h-5 w-5',
} as const

/**
 * Read-only star display. `value` may be fractional (e.g. 4.5); a star is
 * filled once the value reaches it, so 4.5 shows 4 full + 1 half.
 */
export function StarRating({
  value,
  size = 'md',
  className,
}: {
  value: number
  size?: keyof typeof SIZES
  className?: string
}) {
  return (
    <span className={cn('inline-flex items-center gap-0.5', className)} aria-hidden>
      {[1, 2, 3, 4, 5].map((star) => {
        const fill = Math.max(0, Math.min(1, value - star + 1))
        return (
          <span key={star} className="relative inline-block">
            <Star className={cn(SIZES[size], 'text-muted-foreground/35')} />
            {fill > 0 && (
              // Clip the filled star to the fractional part. In RTL the row is
              // mirrored by the parent, so clip from the inline start either way.
              <span
                className="absolute inset-y-0 start-0 overflow-hidden"
                style={{ width: `${fill * 100}%` }}
              >
                <Star className={cn(SIZES[size], 'fill-accent text-accent')} />
              </span>
            )}
          </span>
        )
      })}
    </span>
  )
}

/** Interactive 1-5 star picker used in the review form. */
export function StarPicker({
  value,
  onChange,
  label,
}: {
  value: number
  onChange: (value: number) => void
  label: string
}) {
  return (
    <div className="flex items-center gap-1" role="radiogroup" aria-label={label}>
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          role="radio"
          aria-checked={value === star}
          aria-label={String(star)}
          onClick={() => onChange(star)}
          className="rounded p-0.5 outline-none transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring"
        >
          <Star
            className={cn(
              'h-7 w-7',
              star <= value ? 'fill-accent text-accent' : 'text-muted-foreground/40',
            )}
          />
        </button>
      ))}
    </div>
  )
}
