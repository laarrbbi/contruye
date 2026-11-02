import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Eye, EyeOff } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Material / Gmail style outlined text field.
 *
 * The label starts inside the box as a placeholder, then floats up and sits
 * ON the border line once the field is focused or filled, notching the outline
 * behind it.
 *
 * Implementation notes:
 * - `placeholder=" "` (a single space) is required: it lets `:placeholder-shown`
 *   detect emptiness in pure CSS, so the label also stays floated for values
 *   restored by the browser or set programmatically — no React state needed.
 * - The notch is the label's own background, so it works on any surface as long
 *   as `bgClassName` matches the surrounding one.
 */
export interface FloatingInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
  /** Must match the surface behind the field so the notch blends in. */
  bgClassName?: string
  endAdornment?: React.ReactNode
}

export const FloatingInput = React.forwardRef<HTMLInputElement, FloatingInputProps>(
  ({ className, label, error, bgClassName = 'bg-background', endAdornment, id, ...props }, ref) => {
    const generatedId = React.useId()
    const inputId = id ?? generatedId
    const errorId = `${inputId}-error`
    // A field written left to right (an address, a password): its padding is on
    // its own right, or on its left in Arabic, where the adornment sits at the field's end.
    const ltr = props.dir === 'ltr'

    return (
      <div className="w-full">
        <div className="relative">
          <input
            id={inputId}
            ref={ref}
            placeholder=" "
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? errorId : undefined}
            className={cn(
              'peer h-14 w-full rounded-lg border bg-transparent px-4 pt-2 text-base',
              'outline-none transition-colors',
              'placeholder:text-transparent',
              'focus:border-primary focus:ring-1 focus:ring-primary',
              'disabled:cursor-not-allowed disabled:opacity-50',
              error ? 'border-destructive focus:border-destructive focus:ring-destructive' : 'border-input',
              endAdornment && (ltr ? 'pr-12 rtl:pl-12 rtl:pr-4' : 'pe-12'),
              className,
            )}
            {...props}
          />

          <label
            htmlFor={inputId}
            className={cn(
              'pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 px-1 text-base text-muted-foreground',
              'transition-[top,font-size,color] duration-150 ease-out-strong',
              bgClassName,
              // Floated: sits on the border line, small.
              'peer-focus:top-0 peer-focus:text-xs peer-focus:text-primary',
              // Stays floated whenever the field has a value.
              'peer-[:not(:placeholder-shown)]:top-0 peer-[:not(:placeholder-shown)]:text-xs',
              error && 'peer-focus:text-destructive peer-[:not(:placeholder-shown)]:text-destructive',
            )}
          >
            {label}
          </label>

          {endAdornment && (
            <div className="absolute end-3 top-1/2 -translate-y-1/2">{endAdornment}</div>
          )}
        </div>

        {error && (
          <p id={errorId} className="mt-1.5 text-sm text-destructive">
            {error}
          </p>
        )}
      </div>
    )
  },
)
FloatingInput.displayName = 'FloatingInput'

/**
 * A password field with its eye. The icon says the state (hidden: the eye
 * struck out; shown: the eye open), the label read aloud says the action.
 */
export const FloatingPasswordInput = React.forwardRef<HTMLInputElement, Omit<FloatingInputProps, 'type' | 'endAdornment'>>(
  (props, ref) => {
    const { t } = useTranslation()
    const [shown, setShown] = React.useState(false)
    return (
      <FloatingInput
        ref={ref}
        type={shown ? 'text' : 'password'}
        dir="ltr"
        endAdornment={
          <button
            type="button"
            onClick={() => setShown((value) => !value)}
            aria-label={shown ? t('auth.hidePassword') : t('auth.showPassword')}
            aria-pressed={shown}
            className="grid h-9 w-9 place-items-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
          >
            {shown ? <Eye className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} /> : <EyeOff className="h-[1.125rem] w-[1.125rem]" strokeWidth={1.75} />}
          </button>
        }
        {...props}
      />
    )
  },
)
FloatingPasswordInput.displayName = 'FloatingPasswordInput'
