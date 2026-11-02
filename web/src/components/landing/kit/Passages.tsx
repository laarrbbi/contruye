import { useVu, vars } from './tools'

/** Buildings going up along the horizon: where each stands, how wide, how many floors are poured. */
const SITES = [
  { x: 40, w: 150, floors: 4 },
  { x: 250, w: 110, floors: 7 },
  { x: 420, w: 190, floors: 3 },
  { x: 700, w: 130, floors: 6 },
  { x: 900, w: 210, floors: 4 },
  { x: 1170, w: 120, floors: 8 },
  { x: 1320, w: 100, floors: 3 },
]
const CRANES = [
  { x: 330, h: 250, jib: 150, back: 46 },
  { x: 850, h: 215, jib: -170, back: -50 },
  { x: 1255, h: 270, jib: 130, back: 40 },
]
const GROUND = 292
const FLOOR = 24

/**
 * Night gives way to paper, and the passage is never an empty screen: a
 * horizon of buildings going up and tower cranes, drawn in thin lines that
 * trace themselves, the sky behind them turning from night to dawn. It is a
 * little over half a screen high, so the next section's title is already
 * coming up under it. The header follows: night over its top, paper below.
 */
export function Aube() {
  const box = useVu<HTMLDivElement>(0.25)
  return (
    <div ref={box} aria-hidden className="relative h-[56svh] overflow-hidden bg-[linear-gradient(to_bottom,#070F1C_0%,#0F2038_22%,#213F68_44%,#5C7396_60%,#C9AD86_76%,#F1DFC0_88%,#F6F2EA_100%)]">
      <div data-tone="night" className="absolute inset-x-0 top-0 h-[55%]" />
      <div data-tone="paper" className="absolute inset-x-0 bottom-0 h-[45%]" />
      {/* The light coming up behind the sites. */}
      <div className="absolute bottom-[4%] left-1/2 h-[60%] w-[70%] -translate-x-1/2 rounded-[50%] bg-[radial-gradient(closest-side,rgb(241_184_96/0.55),transparent)] blur-2xl" />
      <svg viewBox="0 0 1440 300" preserveAspectRatio="xMidYMax slice" className="absolute inset-x-0 bottom-0 h-[70%] w-full" fill="none" stroke="#141A22" strokeOpacity="0.62" strokeWidth="1.1" strokeLinecap="round">
        {/* The ground everything stands on: below it, the paper. */}
        <rect x="-10" y={GROUND} width="1460" height="20" fill="#F6F2EA" stroke="none" />
        <path d={`M0 ${GROUND} H1440`} pathLength={1} className="trace" style={vars({ '--d': '0ms' })} />
        {SITES.map((site, index) => {
          const top = GROUND - site.floors * FLOOR
          const columns = Math.max(2, Math.round(site.w / 38))
          return (
            <g key={site.x}>
              {/* The frame: columns and the slabs already poured. */}
              <path
                pathLength={1}
                className="trace"
                style={vars({ '--d': `${200 + index * 140}ms` })}
                d={[
                  ...Array.from({ length: columns + 1 }, (_, c) => `M${(site.x + (site.w * c) / columns).toFixed(0)} ${GROUND} V${top}`),
                  ...Array.from({ length: site.floors }, (_, f) => `M${site.x} ${GROUND - (f + 1) * FLOOR} H${site.x + site.w}`),
                ].join(' ')}
              />
              {/* Rebar waiting above the last slab. */}
              <path
                pathLength={1}
                className="trace"
                strokeOpacity="0.4"
                style={vars({ '--d': `${700 + index * 140}ms` })}
                d={Array.from({ length: columns + 1 }, (_, c) => `M${(site.x + (site.w * c) / columns).toFixed(0)} ${top} v-12`).join(' ')}
              />
            </g>
          )
        })}
        {CRANES.map((crane, index) => {
          const top = GROUND - crane.h
          return (
            <path
              key={crane.x}
              pathLength={1}
              className="trace"
              stroke="#8F5A0E"
              strokeOpacity="0.9"
              style={vars({ '--d': `${900 + index * 260}ms` })}
              d={`M${crane.x} ${GROUND} V${top - 16} M${crane.x - crane.back} ${top} H${crane.x + crane.jib} M${crane.x} ${top - 16} L${crane.x + crane.jib * 0.55} ${top} M${crane.x} ${top - 16} L${crane.x - crane.back * 0.8} ${top} M${crane.x + crane.jib * 0.7} ${top} v${crane.h * 0.3}`}
            />
          )
        })}
      </svg>
    </div>
  )
}
