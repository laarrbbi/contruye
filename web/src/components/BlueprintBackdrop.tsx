import { cn } from '@/lib/utils'

/**
 * Decorative backdrop for the navy bands (sales hero, account and checkout
 * pages): the trade's drafting grid, and the line elevation of a block of
 * flats going up beside its crane, drawn in faint white.
 *
 * A drawing, not a photograph: the site shows no picture of a project it
 * cannot attribute (constitution I). Nothing here moves, so there is nothing
 * to switch off for reduced motion, and nothing to download.
 */
export function BlueprintBackdrop({ className }: { className?: string }) {
  return (
    <div aria-hidden className={cn('pointer-events-none absolute inset-0 -z-10 overflow-hidden', className)}>
      {/* Drafting grid, fading out from the top. */}
      <div className="absolute inset-0 bg-[linear-gradient(to_right,rgb(255_255_255/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.06)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_90%_80%_at_50%_0%,black,transparent)]" />

      {/* Elevation: five storeys on a ground line, the top one still a frame,
          and the crane that lifts it. Anchored to the end corner. */}
      <svg
        viewBox="0 0 480 400"
        className="absolute -bottom-6 end-0 h-[85%] max-h-[440px] w-auto text-white/[0.09] max-md:hidden"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        {/* ground */}
        <path d="M20 380H470" />
        {/* finished storeys */}
        <path d="M80 380V170H300V380" />
        <path d="M80 338H300M80 296H300M80 254H300M80 212H300" />
        {/* windows, three per storey */}
        {[338, 296, 254, 212].map((y) => (
          <g key={y}>
            <rect x="102" y={y - 32} width="36" height="22" rx="2" />
            <rect x="172" y={y - 32} width="36" height="22" rx="2" />
            <rect x="242" y={y - 32} width="36" height="22" rx="2" />
          </g>
        ))}
        {/* entrance */}
        <path d="M172 380V352H208V380" />
        {/* top storey: columns and slab only */}
        <path d="M80 170V128H300V170" strokeDasharray="6 6" />
        <path d="M150 170V128M230 170V128" strokeDasharray="6 6" />
        {/* crane: mast, jib, counter-jib, hook */}
        <path d="M380 380V60" />
        <path d="M372 380V68M388 380V68" strokeWidth="1" />
        <path d="M380 60H160M380 60H440" />
        <path d="M380 60L380 30L160 60M380 30L440 60" strokeWidth="1.5" />
        <path d="M250 60V112" strokeWidth="1.5" />
        <path d="M242 112H258L254 124H246Z" />
        <rect x="424" y="62" width="16" height="14" />
      </svg>
    </div>
  )
}
