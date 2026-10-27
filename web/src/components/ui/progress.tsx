import * as React from 'react'
import * as ProgressPrimitive from '@radix-ui/react-progress'
import { cn } from '@/lib/utils'

/**
 * Progress bar. The indicator is width-based (rather than the usual
 * translateX) so it fills from the inline-start edge in both LTR and RTL.
 */
const Progress = React.forwardRef<
  React.ElementRef<typeof ProgressPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof ProgressPrimitive.Root>
>(({ className, value, ...props }, ref) => (
  <ProgressPrimitive.Root
    ref={ref}
    className={cn('relative h-2 w-full overflow-hidden rounded-full bg-muted', className)}
    value={value}
    {...props}
  >
    <ProgressPrimitive.Indicator
      className="h-full rounded-full bg-primary transition-[width] duration-300 ease-out-strong"
      style={{ width: `${Math.min(100, Math.max(0, value ?? 0))}%` }}
    />
  </ProgressPrimitive.Root>
))
Progress.displayName = ProgressPrimitive.Root.displayName

export { Progress }
