/**
 * The hero's building, described once and drawn twice: as stacked SVGs for
 * every visitor (BuildingSvg) and in WebGL for the devices that can afford it
 * (buildingScene). Both read these numbers, so the two never drift apart.
 *
 * Twelve storeys, one per phase of the course (landing.method.s1 … s12). One
 * storey is one unit high. The long façade faces +z, the gable faces +x, and
 * both renderers look at the corner between them.
 */
export const STOREYS = 12
export const STOREY_H = 1
export const SLAB_T = 0.16
/** How far a floor slab runs past the walls. */
export const OVERHANG = 0.2
/** The body, wall to wall: the long façade (x) and the gable (z). */
export const BODY_W = 5.4
export const BODY_D = 3.4
export const FRONT_BAYS = 6
export const SIDE_BAYS = 4
export const BALCONY_D = 0.75
export const RAIL_H = 0.42

/** The dismantled model: how far apart the storeys float before they stack. */
export const EXPLODED_GAP = 0.7
export const EXPLODED_GAP_COMPACT = 0.42
export const EXPLODED_LIFT = 0.8

export interface Storey {
  index: number
  /** How far the storey steps back on every side (the penthouse). */
  inset: number
  /** One entry per bay, read along the façade: true is glazed, false a solid panel. */
  front: boolean[]
  side: boolean[]
  /** First and last bay of the long façade's balcony, or null. */
  balcony: [number, number] | null
  /** The glazed bays whose window is lit on the last beat. */
  litFront: number[]
  litSide: number[]
}

/** A fixed scatter: the same windows come on at every visit, in both renderers. */
function isLit(storey: number, bay: number, salt: number): boolean {
  return (storey * 7 + bay * 13 + salt * 5) % 5 < 3
}

function storey(index: number): Storey {
  const ground = index === 0
  const top = index === STOREYS - 1
  // Solid panels step sideways from one storey to the next: a diagonal across
  // the façade instead of a chequerboard. The ground floor and the penthouse
  // are glazed from end to end.
  const front = Array.from({ length: FRONT_BAYS }, (_, bay) => ground || top || (bay + index) % 4 !== 0)
  const side = Array.from({ length: SIDE_BAYS }, (_, bay) => ground || top || (bay + index * 2) % 3 !== 0)

  return {
    index,
    inset: top ? 0.55 : 0,
    front,
    side,
    // Balconies alternate ends, storey after storey.
    balcony: ground || top ? null : index % 2 === 1 ? [0, 2] : [3, 5],
    litFront: front.flatMap((glazed, bay) => (glazed && isLit(index, bay, 1) ? [bay] : [])),
    litSide: side.flatMap((glazed, bay) => (glazed && isLit(index, bay, 2) ? [bay] : [])),
  }
}

export const BUILDING: readonly Storey[] = Array.from({ length: STOREYS }, (_, index) => storey(index))

/** Where a storey rests once the building is up. */
export function stackedY(index: number): number {
  return index * STOREY_H
}

/** Where it floats before, in the dismantled model. */
export function explodedY(index: number, gap: number): number {
  return index * (STOREY_H + gap) + EXPLODED_LIFT
}

/** The height of the dismantled model, ground to roof. */
export function explodedHeight(gap: number): number {
  return explodedY(STOREYS - 1, gap) + STOREY_H
}
