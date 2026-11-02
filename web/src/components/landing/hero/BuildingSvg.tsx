import { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react'
import { cn } from '@/lib/utils'
import {
  BALCONY_D,
  BODY_D,
  BODY_W,
  BUILDING,
  EXPLODED_GAP,
  EXPLODED_GAP_COMPACT,
  FRONT_BAYS,
  OVERHANG,
  RAIL_H,
  SIDE_BAYS,
  SLAB_T,
  STOREY_H,
  explodedHeight,
  type Storey,
} from './building'
import { modelTop, storeyState, storeyY } from './timeline'

/**
 * The building as a drawing: what every visitor gets first, and all that a
 * modest phone, a slow connection or a reduced-motion setting ever gets.
 *
 * Each storey is its own small SVG inside a positioned box. Stacking moves
 * the box with a transform, which the compositor does without repainting;
 * pouring the concrete is a cross-fade between the storey's line layer and
 * its solid layer (index.css, .bld-*). Nothing here animates on its own: it
 * moves when the page scrolls and is still otherwise.
 */
export interface BuildingSvgHandle {
  setProgress(p: number): void
}

type P3 = [number, number, number]

// A plain isometric view from the corner between the long façade (+z) and
// the gable (+x): x runs down to the right, z down to the left, y straight up.
const COS = Math.cos(Math.PI / 6)
function iso([x, y, z]: P3): [number, number] {
  return [(x - z) * COS, (x + z) * 0.5 - y]
}
function points(corners: P3[]): string {
  return corners.map((corner) => iso(corner).map((n) => n.toFixed(3)).join(',')).join(' ')
}
function outline(corners: P3[]): string {
  return `M${corners.map((corner) => iso(corner).map((n) => n.toFixed(3)).join(' ')).join('L')}Z`
}

interface ViewBox {
  x: number
  y: number
  w: number
  h: number
}

function viewOf(corners: P3[], pad: number): ViewBox {
  const projected = corners.map(iso)
  const xs = projected.map(([x]) => x)
  const ys = projected.map(([, y]) => y)
  const x = Math.min(...xs) - pad
  const y = Math.min(...ys) - pad
  return { x, y, w: Math.max(...xs) - x + pad, h: Math.max(...ys) - y + pad }
}

// Every storey shares one box, sized for the widest of them with its balcony.
const REACH_X = BODY_W / 2 + OVERHANG
const REACH_Z = BODY_D / 2 + OVERHANG
const STOREY_VIEW = viewOf(
  [
    [-REACH_X, 0, REACH_Z + BALCONY_D],
    [REACH_X, 0, -REACH_Z],
    [-REACH_X, STOREY_H, -REACH_Z],
    [REACH_X, 0, REACH_Z + BALCONY_D],
  ],
  0.06,
)

// The plot the building stands on: the model's base.
const SITE = { x0: -3.7, x1: 3.7, z0: -2.7, z1: 3.6, t: 0.14 }
const SITE_VIEW = viewOf(
  [
    [SITE.x0, 0, SITE.z1],
    [SITE.x1, 0, SITE.z0],
    [SITE.x0, 0, SITE.z0],
    [SITE.x1, -SITE.t, SITE.z1],
  ],
  0.06,
)

const VIEW = (v: ViewBox) => `${v.x.toFixed(3)} ${v.y.toFixed(3)} ${v.w.toFixed(3)} ${v.h.toFixed(3)}`

const TONE = {
  slabTop: '#E3E0D9',
  slabFront: '#CFCCC5',
  slabSide: '#A9A6A0',
  roof: '#D5D2CB',
  panelFront: '#F2EFEA',
  panelSide: '#C4C1BA',
  frame: '#1B2433',
}

function StoreyDrawing({ storey }: { storey: Storey }) {
  const drawing = useMemo(() => {
    const hw = BODY_W / 2 - storey.inset
    const hd = BODY_D / 2 - storey.inset
    const ox = hw + OVERHANG
    const oz = hd + OVERHANG
    const T = SLAB_T
    const H = STOREY_H
    const bw = (hw * 2) / FRONT_BAYS
    const bd = (hd * 2) / SIDE_BAYS

    const slabTop: P3[] = [[-ox, T, -oz], [ox, T, -oz], [ox, T, oz], [-ox, T, oz]]
    const slabFront: P3[] = [[-ox, 0, oz], [ox, 0, oz], [ox, T, oz], [-ox, T, oz]]
    const slabSide: P3[] = [[ox, 0, oz], [ox, 0, -oz], [ox, T, -oz], [ox, T, oz]]
    const roof: P3[] = [[-hw, H, -hd], [hw, H, -hd], [hw, H, hd], [-hw, H, hd]]

    const frontBay = (bay: number, inset = 0): P3[] => {
      const x0 = -hw + bay * bw + inset
      const x1 = -hw + (bay + 1) * bw - inset
      return [[x0, T + inset, hd], [x1, T + inset, hd], [x1, H - inset, hd], [x0, H - inset, hd]]
    }
    const sideBay = (bay: number, inset = 0): P3[] => {
      const z0 = hd - bay * bd - inset
      const z1 = hd - (bay + 1) * bd + inset
      return [[hw, T + inset, z0], [hw, T + inset, z1], [hw, H - inset, z1], [hw, H - inset, z0]]
    }

    const ghost: P3[][] = [slabTop, slabFront, slabSide, roof]
    for (let bay = 0; bay < FRONT_BAYS; bay++) ghost.push(frontBay(bay))
    for (let bay = 0; bay < SIDE_BAYS; bay++) ghost.push(sideBay(bay))

    let balcony: { top: P3[]; front: P3[]; side: P3[]; railFront: P3[]; railSide: P3[] } | null = null
    if (storey.balcony) {
      const xa = -hw + storey.balcony[0] * bw
      const xb = -hw + (storey.balcony[1] + 1) * bw
      const z1 = oz + BALCONY_D
      balcony = {
        top: [[xa, T, oz], [xb, T, oz], [xb, T, z1], [xa, T, z1]],
        front: [[xa, 0, z1], [xb, 0, z1], [xb, T, z1], [xa, T, z1]],
        side: [[xb, 0, z1], [xb, 0, oz], [xb, T, oz], [xb, T, z1]],
        railFront: [[xa, T, z1], [xb, T, z1], [xb, T + RAIL_H, z1], [xa, T + RAIL_H, z1]],
        railSide: [[xb, T, z1], [xb, T, oz], [xb, T + RAIL_H, oz], [xb, T + RAIL_H, z1]],
      }
      ghost.push(balcony.top, balcony.front, balcony.railFront, balcony.railSide)
    }

    return { slabTop, slabFront, slabSide, roof, frontBay, sideBay, balcony, ghost: ghost.map(outline).join('') }
  }, [storey])

  const { slabTop, slabFront, slabSide, roof, frontBay, sideBay, balcony } = drawing
  // Windows come on in a fixed order, a beat apart (index.css reads --rank).
  let rank = 0

  return (
    <svg viewBox={VIEW(STOREY_VIEW)} className="block h-full w-full overflow-visible" aria-hidden="true">
      <g className="bld-solid" stroke={TONE.frame} strokeOpacity={0.3} strokeWidth={0.75} strokeLinejoin="round">
        <polygon points={points(slabTop)} fill={TONE.slabTop} vectorEffect="non-scaling-stroke" />
        <polygon points={points(slabSide)} fill={TONE.slabSide} vectorEffect="non-scaling-stroke" />
        <polygon points={points(slabFront)} fill={TONE.slabFront} vectorEffect="non-scaling-stroke" />
        {storey.side.map((glazed, bay) => (
          <polygon
            key={`s${bay}`}
            points={points(sideBay(bay))}
            fill={glazed ? 'url(#bld-glass-side)' : TONE.panelSide}
            vectorEffect="non-scaling-stroke"
          />
        ))}
        {storey.front.map((glazed, bay) => (
          <polygon
            key={`f${bay}`}
            points={points(frontBay(bay))}
            fill={glazed ? 'url(#bld-glass-front)' : TONE.panelFront}
            vectorEffect="non-scaling-stroke"
          />
        ))}
        <polygon points={points(roof)} fill={TONE.roof} vectorEffect="non-scaling-stroke" />
      </g>

      <g>
        {storey.litSide.map((bay) => (
          <polygon
            key={`ls${bay}`}
            className="bld-lit"
            style={{ '--rank': rank++ } as React.CSSProperties}
            points={points(sideBay(bay, 0.07))}
            fill="url(#bld-lit-side)"
          />
        ))}
        {storey.litFront.map((bay) => (
          <polygon
            key={`lf${bay}`}
            className="bld-lit"
            style={{ '--rank': rank++ } as React.CSSProperties}
            points={points(frontBay(bay, 0.07))}
            fill="url(#bld-lit-front)"
          />
        ))}
      </g>

      {balcony && (
        <g className="bld-solid" stroke={TONE.frame} strokeOpacity={0.3} strokeWidth={0.75} strokeLinejoin="round">
          <polygon points={points(balcony.top)} fill={TONE.slabTop} vectorEffect="non-scaling-stroke" />
          <polygon points={points(balcony.side)} fill={TONE.slabSide} vectorEffect="non-scaling-stroke" />
          <polygon points={points(balcony.front)} fill={TONE.slabFront} vectorEffect="non-scaling-stroke" />
          {/* Glass balustrades: the façade shows through. */}
          <polygon points={points(balcony.railSide)} fill="#A9BDD6" fillOpacity={0.2} stroke="#F7F5F0" strokeOpacity={0.55} vectorEffect="non-scaling-stroke" />
          <polygon points={points(balcony.railFront)} fill="#A9BDD6" fillOpacity={0.26} stroke="#F7F5F0" strokeOpacity={0.55} vectorEffect="non-scaling-stroke" />
        </g>
      )}

      <path className="bld-ghost" d={drawing.ghost} />
    </svg>
  )
}

function SiteDrawing() {
  const { x0, x1, z0, z1, t } = SITE
  const grid: string[] = []
  for (let x = Math.ceil(x0); x <= x1; x++) grid.push(`M${iso([x, 0, z0]).join(' ')}L${iso([x, 0, z1]).join(' ')}`)
  for (let z = Math.ceil(z0); z <= z1; z++) grid.push(`M${iso([x0, 0, z]).join(' ')}L${iso([x1, 0, z]).join(' ')}`)
  const hw = BODY_W / 2
  const hd = BODY_D / 2

  return (
    <svg viewBox={VIEW(SITE_VIEW)} className="block h-full w-full overflow-visible" aria-hidden="true">
      <defs>
        <linearGradient id="bld-glass-front" x1="0" y1="0" x2="0.35" y2="1">
          <stop offset="0" stopColor="#3A679C" />
          <stop offset="0.45" stopColor="#1F426B" />
          <stop offset="1" stopColor="#12294A" />
        </linearGradient>
        <linearGradient id="bld-glass-side" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#1D3B60" />
          <stop offset="1" stopColor="#0C1E35" />
        </linearGradient>
        <linearGradient id="bld-lit-front" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#FFDD8A" />
          <stop offset="1" stopColor="#F59E0B" />
        </linearGradient>
        <linearGradient id="bld-lit-side" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#F7B840" />
          <stop offset="1" stopColor="#C97A06" />
        </linearGradient>
        <linearGradient id="bld-site" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#16304F" />
          <stop offset="1" stopColor="#0F2238" />
        </linearGradient>
      </defs>
      <polygon points={points([[x1, -t, z1], [x1, -t, z0], [x1, 0, z0], [x1, 0, z1]])} fill="#081220" />
      <polygon points={points([[x0, -t, z1], [x1, -t, z1], [x1, 0, z1], [x0, 0, z1]])} fill="#0B1829" />
      <polygon
        points={points([[x0, 0, z0], [x1, 0, z0], [x1, 0, z1], [x0, 0, z1]])}
        fill="url(#bld-site)"
        stroke="#A9BDD6"
        strokeOpacity={0.45}
        strokeWidth={1}
        vectorEffect="non-scaling-stroke"
      />
      <path d={grid.join('')} stroke="#A9BDD6" strokeOpacity={0.13} strokeWidth={1} fill="none" vectorEffect="non-scaling-stroke" />
      {/* The footprint, staked out before anything is built. */}
      <path
        d={outline([[-hw, 0, -hd], [hw, 0, -hd], [hw, 0, hd], [-hw, 0, hd]])}
        stroke="#F7F5F0"
        strokeOpacity={0.75}
        strokeWidth={1}
        strokeDasharray="5 4"
        fill="#F59E0B"
        fillOpacity={0.05}
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  )
}

/** Where everything sits for a given stage size, in pixels. */
function layoutFor(width: number, height: number) {
  const compact = width < 560
  const gap = compact ? EXPLODED_GAP_COMPACT : EXPLODED_GAP
  // What a storey's box shows above and below the storey itself, in units.
  const around = STOREY_VIEW.h - STOREY_H + (SITE_VIEW.y + SITE_VIEW.h - (STOREY_VIEW.y + STOREY_VIEW.h))
  const tallest = explodedHeight(gap) + around
  const unit = Math.min(width / (SITE_VIEW.w + 0.4), height / (tallest + 0.5))
  // As the model closes it gets shorter: the view moves in, as a camera would,
  // for as long as the plot still fits the width.
  const widest = (width / (SITE_VIEW.w * unit)) * 0.97
  const zoomAt = (top: number) => Math.min(widest, ((tallest + 0.5) / (top + around + 0.5)) * 0.98)
  // The point of the page where the model's origin (ground, centre) lands.
  const originX = width / 2 - (SITE_VIEW.x + SITE_VIEW.w / 2) * unit
  const originY = height - (SITE_VIEW.y + SITE_VIEW.h) * unit - 0.2 * unit
  return { gap, zoomAt, unit, originX, originY }
}

export const BuildingSvg = forwardRef<BuildingSvgHandle, { className?: string }>(function BuildingSvg(
  { className },
  ref,
) {
  const stage = useRef<HTMLDivElement>(null)
  const rig = useRef<HTMLDivElement>(null)
  const boxes = useRef<(HTMLDivElement | null)[]>([])
  const progress = useRef(0)
  const [size, setSize] = useState<{ width: number; height: number } | null>(null)

  useEffect(() => {
    const node = stage.current
    if (!node) return
    const observer = new ResizeObserver(([entry]) => {
      const { width, height } = entry.contentRect
      if (width > 0 && height > 0) setSize({ width: Math.round(width), height: Math.round(height) })
    })
    observer.observe(node)
    return () => observer.disconnect()
  }, [])

  const layout = useMemo(() => (size ? layoutFor(size.width, size.height) : null), [size])

  const apply = useCallback(
    (p: number) => {
      if (!layout) return
      const { gap, unit, zoomAt } = layout
      BUILDING.forEach((storey) => {
        const box = boxes.current[storey.index]
        if (!box) return
        const state = storeyState(storey.index, p)
        const y = storeyY(storey.index, p, gap)
        box.style.transform = `translate3d(0, ${(-y * unit).toFixed(2)}px, 0)`
        box.classList.toggle('is-active', state.active && state.material < 1)
        box.classList.toggle('is-built', state.material > 0.5)
        box.classList.toggle('is-lit', state.lit > 0)
      })
      if (rig.current) rig.current.style.transform = `scale(${zoomAt(modelTop(p, gap)).toFixed(4)})`
    },
    [layout],
  )

  useImperativeHandle(
    ref,
    () => ({
      setProgress(p: number) {
        progress.current = p
        apply(p)
      },
    }),
    [apply],
  )

  // A new size is a new scale: put every storey back where the scroll says.
  useEffect(() => apply(progress.current), [apply])

  return (
    <div ref={stage} className={cn('relative h-full w-full', className)}>
      {layout && (
        <div
          ref={rig}
          className="absolute inset-0 will-change-transform"
          style={{ transformOrigin: `${layout.originX}px ${layout.originY + 0.6 * layout.unit}px` }}
        >
          <div
            className="absolute"
            style={{
              left: layout.originX + SITE_VIEW.x * layout.unit,
              top: layout.originY + SITE_VIEW.y * layout.unit,
              width: SITE_VIEW.w * layout.unit,
              height: SITE_VIEW.h * layout.unit,
            }}
          >
            <SiteDrawing />
          </div>
          {BUILDING.map((storey) => (
            <div
              key={storey.index}
              ref={(node) => {
                boxes.current[storey.index] = node
              }}
              className="bld-storey absolute will-change-transform"
              style={{
                left: layout.originX + STOREY_VIEW.x * layout.unit,
                top: layout.originY + STOREY_VIEW.y * layout.unit,
                width: STOREY_VIEW.w * layout.unit,
                height: STOREY_VIEW.h * layout.unit,
              }}
            >
              <StoreyDrawing storey={storey} />
            </div>
          ))}
        </div>
      )}
    </div>
  )
})
