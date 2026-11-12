import * as React from 'react'
import * as SwitchPrimitives from '@radix-ui/react-switch'
import { cn } from '@/lib/utils'

const Switch = React.forwardRef<
  React.ElementRef<typeof SwitchPrimitives.Root>,
  React.ComponentPropsWithoutRef<typeof SwitchPrimitives.Root>
>(({ className, ...props }, ref) => (
  // 24px tall to the eye, 44px to a finger (the ::before around it).
  <SwitchPrimitives.Root
    className={cn(
      'peer relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full before:absolute before:-inset-x-0.5 before:-inset-y-2.5 before:content-[""] border-2 border-transparent transition-[background-color,transform] duration-150 ease-out-strong active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input',
      className,
    )}
    {...props}
    ref={ref}
  >
    <SwitchPrimitives.Thumb
      className={cn(
        'pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform duration-150 ease-out-strong data-[state=unchecked]:translate-x-0 data-[state=checked]:translate-x-5 rtl:data-[state=checked]:-translate-x-5',
      )}
    />
  </SwitchPrimitives.Root>
))
Switch.displayName = SwitchPrimitives.Root.displayName

export { Switch }
