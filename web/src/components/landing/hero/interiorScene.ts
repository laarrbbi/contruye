import {
  ACESFilmicToneMapping,
  BoxGeometry,
  BufferAttribute,
  BufferGeometry,
  Color,
  DirectionalLight,
  DoubleSide,
  EdgesGeometry,
  Group,
  HemisphereLight,
  IcosahedronGeometry,
  Line,
  LineBasicMaterial,
  LineDashedMaterial,
  LineSegments,
  Mesh,
  MeshBasicMaterial,
  MeshStandardMaterial,
  PerspectiveCamera,
  Plane,
  PlaneGeometry,
  SRGBColorSpace,
  Scene,
  Vector3,
  WebGLRenderer,
  type Material,
} from 'three'
import { EXPLODED_GAP, EXPLODED_GAP_COMPACT, STOREYS, STOREY_H, explodedHeight, stackedY } from './building'
import {
  LineBuilder,
  SolidBuilder,
  buildContactShadow,
  buildEnvironment,
  type BuildingSceneHandle,
  type BuildingSceneOptions,
  type Quad,
  type SkyPanel,
} from './sceneKit'
import { STEPS_END, clamp01, mix, modelTop, storeyState, storeyY } from './timeline'

/**
 * The hero's building, third study (`?b=v3`): the one you can see into.
 *
 * The owner's reference was a rendered cutaway of a small modern block: white
 * bands at every floor, anthracite panels between tall windows, and behind
 * the glass, furnished rooms in warm light. This keeps that language on the
 * prototype's nine storeys and two volumes.
 *
 * What makes it read: the glass is really transparent, every storey has its
 * rooms (floor, walls, furniture built from boxes), and those rooms are drawn
 * without the scene's lighting, in their own warm tint, so they glow like lit
 * interiors at no cost in lights. A storey has no ceiling: while it floats in
 * the dismantled model you look down into it, and the storey above closes it.
 *
 * Stylised, not photoreal, and still nothing downloaded.
 *
 * (The scaffolding below the storey builder repeats residenceScene's. The
 * three studies are alternatives: the two that lose are deleted, and what the
 * winner shares with nothing is no longer worth sharing.)
 */
const H = STOREY_H
/** The white band at the foot of every storey: slab edge and window sill in one. */
const BAND = 0.24
/** Where the glazing stops under the next band. */
const HEAD = 0.94
/** The finished floor inside. */
const FLOOR = 0.07
const A = { x0: -2.9, x1: 1.5, z0: -1.7, z1: 1.2 }
const B = { x0: 1.1, x1: 3.0, z0: -1.3, z1: 1.9, top: 6 }
const ATTIC = { x0: -2.3, x1: 0.9, z0: -1.7, z1: 0.45 }
const STAIR_X0 = 0.78
const BAYS = 3
const BAY_W = (STAIR_X0 - A.x0) / BAYS
/** How deep the rooms go behind the long façade. */
const ROOM_D = 1.7
/** The wing's loggia, and the glazed wall behind it. */
const LOGGIA = 0.78
const OVERSHOOT = 0.6

const TONE = {
  render: '#f3f1ec',
  panel: '#474c55',
  frame: '#20252d',
  slat: '#9c6b43',
  // Inside.
  wall: '#efe2cc',
  wood: '#b78455',
  darkWood: '#7a5234',
  tile: '#d6ccbc',
  linen: '#f4efe6',
  sofa: '#70808c',
  sofaWarm: '#c8b497',
  rug: '#b4573c',
  rugBlue: '#3f5f78',
  kitchen: '#3a3f46',
  lamp: '#ffe2a6',
  plant: '#4f7d46',
  pot: '#cbc5ba',
  art: '#c9794b',
  // The same building as a white model.
  model: '#e9edf3',
  glass: '#c3d9ee',
  ink: '#51678a',
  amber: '#f59e0b',
  site: '#12233a',
  paving: '#2a3d58',
  tree: '#5c8b52',
  trunk: '#6b4a30',
  line: '#a9bdd6',
}

function scatter(a: number, b: number, c = 0): number {
  const value = Math.sin(a * 127.1 + b * 311.7 + c * 74.7) * 43758.5453
  return value - Math.floor(value)
}

type Box = [number, number, number, number, number, number]
const span = (x0: number, x1: number, y0: number, y1: number, z0: number, z1: number): Box => [
  (x0 + x1) / 2,
  (y0 + y1) / 2,
  (z0 + z1) / 2,
  Math.abs(x1 - x0),
  y1 - y0,
  Math.abs(z1 - z0),
]

interface StoreyRig {
  group: Group
  pour: Plane
  unpoured: Plane
  model: Mesh
  real: Mesh[]
  rooms: MeshBasicMaterial
  lines: LineBasicMaterial
  restY: number
}

interface SharedMaterials {
  model: MeshStandardMaterial
  walls: MeshStandardMaterial
  rooms: MeshBasicMaterial
  glass: MeshStandardMaterial
}

/** The windows along the long façade: the lamps come on one window at a time. */
const LAMP_BAYS = 6

function buildStorey(index: number, shared: SharedMaterials, lamps: { value: number }, dusk: { value: number }): StoreyRig {
  const walls = new SolidBuilder()
  const rooms = new SolidBuilder()
  const model = new SolidBuilder()
  const glass = new SolidBuilder()
  const lines = new LineBuilder()

  const tone = (hex: string) => new Color(hex)
  const white = tone(TONE.model)
  const render = tone(TONE.render)
  const panel = tone(TONE.panel)
  const frame = tone(TONE.frame)
  const c = Object.fromEntries(Object.entries(TONE).map(([name, hex]) => [name, tone(hex)])) as Record<keyof typeof TONE, Color>

  /** A piece of the shell, lit by the sky: and the same piece in the white model. */
  const shell = (x0: number, x1: number, y0: number, y1: number, z0: number, z1: number, colour: Color = render) => {
    walls.box(...span(x0, x1, y0, y1, z0, z1), colour)
    model.box(...span(x0, x1, y0, y1, z0, z1), white)
  }
  /** A piece of a room, drawn in its own light. */
  const inside = (x0: number, x1: number, y0: number, y1: number, z0: number, z1: number, colour: Color) => {
    rooms.shadedBox(...span(x0, x1, y0, y1, z0, z1), colour)
    model.box(...span(x0, x1, y0, y1, z0, z1), white)
  }

  /**
   * A sheet of glass in the plane z = `at` (facing +z) or x = `at` (facing
   * +x), between two points along the façade and two heights, with its frame.
   * Each sheet leans a hair off the plane, by an amount of its own, so the
   * façade catches the sky in patches. The white model has the frame only:
   * through the openings you see the rooms.
   */
  const glazing = (facing: 'z' | 'x', at: number, u0: number, u1: number, y0: number, y1: number, panes: number, seed: number) => {
    const lo = Math.min(u0, u1)
    const hi = Math.max(u0, u1)
    const normal = facing === 'z' ? new Vector3(0, 0, 1) : new Vector3(1, 0, 0)
    const along = facing === 'z' ? new Vector3(1, 0, 0) : new Vector3(0, 0, 1)
    const point = (u: number, y: number) => (facing === 'z' ? new Vector3(u, y, at) : new Vector3(at, y, u))
    const bar = (a: number, b: number, ya: number, yb: number) =>
      facing === 'z' ? shell(a, b, ya, yb, at - 0.03, at + 0.03, frame) : shell(at - 0.03, at + 0.03, ya, yb, a, b, frame)
    const width = (hi - lo) / panes
    for (let pane = 0; pane < panes; pane++) {
      const a = lo + pane * width
      const b = a + width
      const corners: Quad = [point(a, y0), point(b, y0), point(b, y1), point(a, y1)]
      const lean = normal
        .clone()
        .addScaledVector(along, (scatter(index, seed + pane, 1) - 0.5) * 0.14)
        .addScaledVector(new Vector3(0, 1, 0), (scatter(index, seed + pane, 2) - 0.5) * 0.1)
        .normalize()
      glass.quad(corners, lean, render)
      if (pane > 0) bar(a - 0.014, a + 0.014, y0, y1)
    }
    bar(lo - 0.02, lo + 0.012, y0, y1)
    bar(hi - 0.012, hi + 0.02, y0, y1)
    bar(lo, hi, y1 - 0.012, y1 + 0.02)
    bar(lo, hi, y0 - 0.02, y0 + 0.012)
  }

  /** A low glass guard standing on a band or a parapet. */
  const guard = (facing: 'z' | 'x', at: number, u0: number, u1: number, y0: number, height: number) => {
    const point = (u: number, y: number) => (facing === 'z' ? new Vector3(u, y, at) : new Vector3(at, y, u))
    const normal = facing === 'z' ? new Vector3(0, 0, 1) : new Vector3(1, 0, 0)
    glass.quad([point(u0, y0), point(u1, y0), point(u1, y0 + height), point(u0, y0 + height)], normal, render)
    if (facing === 'z') shell(Math.min(u0, u1), Math.max(u0, u1), y0 + height, y0 + height + 0.022, at - 0.02, at + 0.02, frame)
    else shell(at - 0.02, at + 0.02, y0 + height, y0 + height + 0.022, Math.min(u0, u1), Math.max(u0, u1), frame)
  }

  /** A planter with something green in it. */
  const planter = (x: number, z: number, length: number, alongX: boolean) => {
    const [dx, dz] = alongX ? [length / 2, 0.09] : [0.09, length / 2]
    shell(x - dx, x + dx, 0, 0.16, z - dz, z + dz, panel)
    inside(x - dx + 0.02, x + dx - 0.02, 0.16, 0.34, z - dz + 0.02, z + dz - 0.02, c.plant)
  }

  // ─── Rooms behind the long façade ───────────────────────────────────────
  // Each is laid out from its own corner: `dx` across the bay, `dz` back from
  // the glass.
  const zGlass = A.z1 - 0.04
  const zBack = A.z1 - ROOM_D
  const furnish = (bay: number, kind: number) => {
    const ox = A.x0 + bay * BAY_W
    const put = (dx0: number, dx1: number, y0: number, y1: number, dz0: number, dz1: number, colour: Color) =>
      inside(ox + dx0, ox + dx1, FLOOR + y0, FLOOR + y1, zGlass - dz1, zGlass - dz0, colour)
    const warm = scatter(index, bay) > 0.5

    if (kind === 0) {
      // A living room: rug, sofa against the back wall, low table, a lamp, a picture.
      put(0.2, 1.02, 0, 0.012, 0.4, 1.25, warm ? c.rug : c.rugBlue)
      put(0.24, 0.98, 0, 0.15, 1.18, 1.55, warm ? c.sofaWarm : c.sofa)
      put(0.24, 0.98, 0.15, 0.3, 1.45, 1.55, warm ? c.sofaWarm : c.sofa)
      put(0.24, 0.31, 0.15, 0.23, 1.18, 1.45, warm ? c.sofaWarm : c.sofa)
      put(0.91, 0.98, 0.15, 0.23, 1.18, 1.45, warm ? c.sofaWarm : c.sofa)
      put(0.44, 0.8, 0.09, 0.12, 0.62, 0.92, c.darkWood)
      put(1.06, 1.08, 0, 0.5, 1.42, 1.44, c.frame)
      put(1.0, 1.14, 0.5, 0.62, 1.36, 1.5, c.lamp)
      put(0.42, 0.82, 0.42, 0.64, 1.6, 1.62, c.art)
    } else if (kind === 1) {
      // A dining room: table, four chairs, a pendant, a sideboard.
      put(0.3, 0.96, 0.24, 0.27, 0.55, 1.05, c.darkWood)
      for (const [dx, dz] of [[0.33, 0.58], [0.9, 0.58], [0.33, 0.99], [0.9, 0.99]]) put(dx, dx + 0.03, 0, 0.24, dz, dz + 0.03, c.darkWood)
      for (const [dx, dz] of [[0.4, 0.38], [0.72, 0.38], [0.4, 1.1], [0.72, 1.1]]) {
        put(dx, dx + 0.14, 0, 0.15, dz, dz + 0.14, c.linen)
        put(dx, dx + 0.14, 0.15, 0.31, dz < 0.5 ? dz : dz + 0.11, dz < 0.5 ? dz + 0.03 : dz + 0.14, c.linen)
      }
      put(0.62, 0.64, 0.62, 0.8, 0.79, 0.81, c.frame)
      put(0.55, 0.71, 0.52, 0.62, 0.72, 0.88, c.lamp)
      put(0.22, 1.02, 0, 0.22, 1.46, 1.62, c.wood)
    } else if (kind === 2) {
      // A bedroom: bed with its headboard, a bedside lamp, a wardrobe.
      put(0.28, 0.9, 0, 0.11, 0.78, 1.56, c.darkWood)
      put(0.3, 0.88, 0.11, 0.19, 0.8, 1.5, c.linen)
      put(0.3, 0.88, 0.19, 0.21, 0.8, 1.15, warm ? c.rug : c.rugBlue)
      put(0.34, 0.84, 0.19, 0.24, 1.34, 1.5, c.model)
      put(0.28, 0.9, 0, 0.36, 1.56, 1.62, c.darkWood)
      put(0.94, 1.08, 0, 0.16, 1.42, 1.58, c.wood)
      put(0.97, 1.05, 0.16, 0.28, 1.46, 1.54, c.lamp)
      put(0.04, 0.2, 0, 0.62, 0.95, 1.62, c.wood)
    } else {
      // A kitchen: a run of units on the back wall, an island, two stools.
      put(0.14, 1.1, 0, 0.3, 1.4, 1.62, c.kitchen)
      put(0.14, 1.1, 0.3, 0.325, 1.38, 1.62, c.tile)
      put(0.14, 1.1, 0.5, 0.74, 1.48, 1.62, c.kitchen)
      put(0.34, 0.92, 0, 0.3, 0.76, 1.02, c.wood)
      put(0.32, 0.94, 0.3, 0.325, 0.74, 1.04, c.tile)
      for (const dx of [0.44, 0.72]) put(dx, dx + 0.1, 0, 0.2, 0.54, 0.64, c.frame)
      put(0.6, 0.66, 0.56, 0.64, 0.86, 0.92, c.lamp)
    }
    // A curtain drawn to one side of the window.
    const side = (index + bay) % 2 === 0
    put(side ? 0.04 : BAY_W - 0.24, side ? 0.24 : BAY_W - 0.04, BAND - FLOOR + 0.02, HEAD - FLOOR - 0.02, 0.03, 0.06, c.linen)
  }

  const ground = index === 0
  const attic = index === STOREYS - 1
  const hasWing = index <= B.top

  // ─── The main volume ────────────────────────────────────────────────────
  shell(A.x0 - 0.06, A.x1 + 0.06, 0, 0.06, A.z0 - 0.06, A.z1 + 0.06)
  lines.box((A.x0 + A.x1) / 2, BAND / 2, (A.z0 + A.z1) / 2, A.x1 - A.x0 + 0.12, BAND, A.z1 - A.z0 + 0.12)

  if (ground) {
    // A glazed hall under an anthracite fascia: a desk, seats, tables.
    shell(A.x0 - 0.06, A.x0 + 0.08, 0, H, A.z0 - 0.06, A.z1 + 0.06)
    shell(A.x0, A.x1, 0, H, A.z0, zBack - 0.06)
    shell(STAIR_X0, A.x1, 0, H, zBack, A.z1, panel)
    shell(A.x0 - 0.08, A.x1 + 0.08, 0.8, H, A.z1 - 0.34, A.z1 + 0.1, panel)
    inside(A.x0 + 0.08, STAIR_X0, 0.06, FLOOR, zBack, zGlass, c.tile)
    inside(A.x0 + 0.08, STAIR_X0, FLOOR, 0.8, zBack - 0.06, zBack, c.wall)
    glazing('z', zGlass, A.x0 + 0.1, STAIR_X0 - 0.02, FLOOR, 0.8, 7, 0)
    const put = (x0: number, x1: number, y0: number, y1: number, dz0: number, dz1: number, colour: Color) =>
      inside(x0, x1, FLOOR + y0, FLOOR + y1, zGlass - dz1, zGlass - dz0, colour)
    put(-0.2, 0.55, 0, 0.3, 1.2, 1.42, c.wood)
    put(-0.22, 0.57, 0.3, 0.33, 1.18, 1.44, c.tile)
    put(-0.1, 0.45, 0.4, 0.62, 1.6, 1.62, c.art)
    put(-2.6, -1.9, 0, 0.15, 1.2, 1.55, c.sofaWarm)
    put(-2.6, -1.9, 0.15, 0.3, 1.45, 1.55, c.sofaWarm)
    put(-2.45, -2.05, 0.09, 0.12, 0.6, 0.9, c.darkWood)
    put(-2.7, -1.8, 0, 0.012, 0.4, 1.3, c.rug)
    for (const x of [-1.4, -0.75]) {
      put(x, x + 0.3, 0.22, 0.25, 0.5, 0.8, c.darkWood)
      put(x + 0.13, x + 0.17, 0, 0.22, 0.63, 0.67, c.frame)
      put(x - 0.16, x - 0.03, 0, 0.14, 0.58, 0.71, c.linen)
      put(x + 0.33, x + 0.46, 0, 0.14, 0.58, 0.71, c.linen)
    }
    for (const x of [-2.2, -1.2, -0.2]) put(x, x + 0.14, 0.56, 0.64, 0.6, 0.74, c.lamp)
  } else if (attic) {
    // The penthouse: a glazed room set back on a terrace behind its parapet.
    shell(A.x0 - 0.06, A.x1 + 0.06, 0, BAND, A.z1 - 0.04, A.z1 + 0.06)
    shell(A.x1 - 0.04, A.x1 + 0.06, 0, BAND, A.z0 - 0.06, A.z1 + 0.06)
    shell(A.x0 - 0.06, A.x0 + 0.04, 0, BAND, A.z0 - 0.06, A.z1 + 0.06)
    guard('z', A.z1, A.x0, A.x1, BAND, 0.2)
    guard('x', A.x1, A.z1, ATTIC.z1 - 0.4, BAND, 0.2)
    const back = ATTIC.z1 - 1.5
    shell(ATTIC.x0, ATTIC.x1, 0.06, HEAD - 0.08, ATTIC.z0, back - 0.06)
    shell(ATTIC.x0 - 0.02, ATTIC.x0 + 0.08, 0.06, HEAD - 0.08, back - 0.06, ATTIC.z1)
    shell(ATTIC.x1 - 0.1, ATTIC.x1, 0.06, HEAD - 0.08, ATTIC.z1 - 0.1, ATTIC.z1)
    shell(ATTIC.x0 - 0.2, ATTIC.x1 + 0.2, HEAD - 0.08, H + 0.02, ATTIC.z0 - 0.1, ATTIC.z1 + 0.22)
    lines.box((ATTIC.x0 + ATTIC.x1) / 2, (HEAD + H) / 2, (ATTIC.z0 + ATTIC.z1) / 2, ATTIC.x1 - ATTIC.x0 + 0.4, 0.12, ATTIC.z1 - ATTIC.z0 + 0.32, 0.7)
    inside(ATTIC.x0 + 0.08, ATTIC.x1, 0.06, FLOOR, back, ATTIC.z1, c.wood)
    inside(ATTIC.x0 + 0.08, ATTIC.x1, FLOOR, HEAD - 0.08, back - 0.06, back, c.wall)
    glazing('z', ATTIC.z1 - 0.03, ATTIC.x0 + 0.1, ATTIC.x1 - 0.1, FLOOR, HEAD - 0.1, 6, 0)
    glazing('x', ATTIC.x1 - 0.03, ATTIC.z1 - 0.1, back, FLOOR, HEAD - 0.1, 3, 8)
    const put = (x0: number, x1: number, y0: number, y1: number, dz0: number, dz1: number, colour: Color) =>
      inside(x0, x1, FLOOR + y0, FLOOR + y1, ATTIC.z1 - dz1, ATTIC.z1 - dz0, colour)
    put(-1.9, -0.7, 0, 0.012, 0.3, 1.2, c.rugBlue)
    put(-1.85, -0.8, 0, 0.15, 1.05, 1.4, c.sofaWarm)
    put(-1.85, -0.8, 0.15, 0.3, 1.3, 1.4, c.sofaWarm)
    put(-1.55, -1.1, 0.09, 0.12, 0.5, 0.8, c.darkWood)
    put(-0.2, 0.55, 0.24, 0.27, 0.5, 1.0, c.darkWood)
    put(0.13, 0.19, 0, 0.24, 0.72, 0.78, c.darkWood)
    for (const [x, z] of [[-0.12, 0.3], [0.33, 0.3], [-0.12, 1.06], [0.33, 1.06]]) put(x, x + 0.14, 0, 0.15, z, z + 0.14, c.linen)
    put(0.1, 0.24, 0.5, 0.6, 0.68, 0.82, c.lamp)
    put(-2.1, -1.96, 0.5, 0.62, 1.25, 1.39, c.lamp)
    // On the terrace and the roof.
    planter(A.x0 + 0.5, A.z1 - 0.22, 0.7, true)
    planter(-0.6, A.z1 - 0.22, 0.6, true)
    planter(A.x1 - 0.2, 0.75, 0.5, false)
    shell(-1.5, -0.7, H + 0.02, H + 0.07, -1.1, -0.4, frame)
    shell(0.1, 0.7, H + 0.02, H + 0.3, -1.5, -0.9)
  } else {
    // A storey of flats: a white band, then glass from sill to head, with an
    // anthracite panel in each bay that changes sides from one storey to the next.
    shell(A.x0 - 0.06, A.x1 + 0.06, 0, BAND, A.z1 - 0.04, A.z1 + 0.06)
    shell(A.x0 - 0.06, A.x0 + 0.08, 0, H, A.z0 - 0.06, A.z1 + 0.06)
    shell(A.x0, A.x1, 0, H, A.z0, zBack - 0.06)
    shell(A.x0 + 0.08, STAIR_X0, HEAD, H, A.z1 - 0.08, A.z1 + 0.02, frame)
    inside(A.x0 + 0.08, STAIR_X0, 0.06, FLOOR, zBack, zGlass, index % 2 === 0 ? c.wood : c.darkWood)
    inside(A.x0 + 0.08, STAIR_X0, FLOOR, H, zBack - 0.06, zBack, c.wall)
    for (let bay = 0; bay < BAYS; bay++) {
      const u0 = A.x0 + bay * BAY_W + (bay === 0 ? 0.08 : 0)
      const u1 = A.x0 + (bay + 1) * BAY_W
      const left = (index + bay) % 2 === 0
      const p0 = left ? u0 : u1 - 0.28
      shell(p0, p0 + 0.28, BAND, HEAD, A.z1 - 0.09, A.z1 + 0.02, panel)
      glazing('z', zGlass, left ? u0 + 0.28 : u0, left ? u1 : u1 - 0.28, BAND, HEAD, 2, bay * 3)
      // A party wall between two flats.
      if (bay > 0) inside(u0 - 0.03, u0 + 0.03, FLOOR, H, zBack, zGlass - 0.02, c.wall)
      furnish(bay, (index * 2 + bay) % 4)
    }
  }

  // The stair, behind a dark strip of louvres between the two volumes.
  if (!ground && !attic) {
    shell(STAIR_X0, A.x1, 0, H, zBack, A.z1 - 0.02, panel)
    for (let slat = 0; slat < 6; slat++) {
      const y = 0.1 + slat * ((H - 0.2) / 5)
      shell(STAIR_X0 + 0.02, B.x0, y - 0.016, y + 0.016, A.z1 - 0.02, A.z1 + 0.05, frame)
    }
    // Above the wing, the main volume shows its own gable: wall, band, one panel.
    if (!hasWing) {
      shell(A.x1 - 0.04, A.x1 + 0.06, 0, BAND, A.z0 - 0.06, A.z1 + 0.06)
      shell(A.x1 - 0.06, A.x1 + 0.02, BAND, HEAD, -0.9, 0.4, panel)
    }
  }

  // ─── The wing ───────────────────────────────────────────────────────────
  if (hasWing) {
    const wallZ = B.z1 - LOGGIA
    const backZ = B.z0 + 0.5
    const gableX = B.x1 - 0.04
    shell(B.x0, B.x1 + 0.06, 0, 0.06, B.z0 - 0.06, B.z1 + 0.06)
    shell(B.x0, B.x1, 0, H, B.z0, backZ - 0.06)
    shell(B.x1 - 0.12, B.x1, ground ? 0 : BAND, H, B.z1 - 0.12, B.z1)
    lines.box((B.x0 + B.x1) / 2, H / 2, (B.z0 + B.z1) / 2, B.x1 - B.x0, H, B.z1 - B.z0, 0.6)
    const put = (x0: number, x1: number, y0: number, y1: number, z0: number, z1: number, colour: Color) =>
      inside(x0, x1, FLOOR + y0, FLOOR + y1, z0, z1, colour)

    if (ground) {
      // A shop on the corner, glazed on both streets.
      shell(B.x0, B.x1 + 0.08, 0.8, H, B.z0 - 0.06, B.z1 + 0.1, panel)
      inside(B.x0 + 0.06, gableX, 0.06, FLOOR, backZ, B.z1 - 0.06, c.tile)
      inside(B.x0 + 0.06, gableX, FLOOR, 0.8, backZ - 0.06, backZ, c.wall)
      glazing('z', B.z1 - 0.05, B.x0 + 0.08, B.x1 - 0.12, FLOOR, 0.8, 3, 20)
      glazing('x', gableX, B.z1 - 0.12, backZ, FLOOR, 0.8, 4, 24)
      put(B.x0 + 0.2, B.x0 + 0.36, 0, 0.56, backZ, B.z1 - 0.5, c.wood)
      put(1.75, 2.45, 0, 0.22, 0.4, 0.85, c.darkWood)
      put(1.85, 2.0, 0.22, 0.34, 0.5, 0.65, c.art)
      put(2.15, 2.3, 0.22, 0.3, 0.55, 0.75, c.plant)
      put(1.75, 2.45, 0, 0.22, -0.45, 0.0, c.darkWood)
      for (const z of [0.6, -0.2]) put(2.02, 2.16, 0.56, 0.64, z, z + 0.14, c.lamp)
    } else {
      // A band on both streets; in front, a loggia behind a glass guard and a
      // screen of timber slats; on the gable, glass and one panel.
      shell(B.x0, B.x1 + 0.06, 0, BAND, B.z1 - 0.04, B.z1 + 0.06)
      shell(B.x1 - 0.04, B.x1 + 0.06, 0, BAND, B.z0 - 0.06, B.z1 + 0.06)
      shell(B.x1 - 0.08, B.x1 + 0.02, HEAD, H, backZ, B.z1 - 0.12, frame)
      shell(B.x0, B.x1 - 0.12, HEAD, H, B.z1 - 0.08, B.z1 + 0.02, frame)
      for (let slat = 0; slat < 6; slat++) {
        const x = B.x0 + 0.08 + slat * 0.075
        shell(x, x + 0.04, BAND, HEAD, B.z1 - 0.07, B.z1 - 0.01, c.slat)
      }
      guard('z', B.z1 + 0.01, B.x0 + 0.56, B.x1 - 0.12, BAND, 0.2)
      guard('x', B.x1 + 0.01, B.z1 - 0.12, wallZ, BAND, 0.2)
      // The loggia's deck, a chair and a plant.
      inside(B.x0 + 0.04, gableX, 0.06, FLOOR, wallZ, B.z1 - 0.04, c.darkWood)
      put(2.2, 2.5, 0, 0.14, wallZ + 0.2, wallZ + 0.5, c.linen)
      put(2.2, 2.5, 0.14, 0.3, wallZ + 0.2, wallZ + 0.25, c.linen)
      put(1.3, 1.46, 0, 0.14, wallZ + 0.12, wallZ + 0.28, c.pot)
      put(1.27, 1.49, 0.14, 0.42, wallZ + 0.09, wallZ + 0.31, c.plant)
      // The room behind.
      inside(B.x0 + 0.06, gableX, 0.06, FLOOR, backZ, wallZ, index % 2 === 0 ? c.darkWood : c.wood)
      inside(B.x0 + 0.06, gableX, FLOOR, H, backZ - 0.06, backZ, c.wall)
      inside(B.x0, B.x0 + 0.06, FLOOR, H, backZ, wallZ, c.wall)
      glazing('z', wallZ, B.x0 + 0.08, B.x1 - 0.1, FLOOR, HEAD, 3, 30)
      const panelNear = index % 2 === 0
      const z0 = wallZ - 0.04
      const pz = panelNear ? z0 - 0.36 : backZ
      shell(B.x1 - 0.09, B.x1 + 0.02, BAND, HEAD, pz, pz + 0.36, panel)
      glazing('x', gableX, panelNear ? z0 - 0.36 : z0, panelNear ? backZ : backZ + 0.36, BAND, HEAD, 3, 34)
      if (index % 2 === 0) {
        put(1.3, 1.9, 0, 0.11, backZ + 0.05, backZ + 0.85, c.darkWood)
        put(1.32, 1.88, 0.11, 0.19, backZ + 0.1, backZ + 0.83, c.linen)
        put(1.34, 1.86, 0.19, 0.24, backZ + 0.08, backZ + 0.26, c.model)
        put(2.0, 2.14, 0, 0.16, backZ + 0.04, backZ + 0.18, c.wood)
        put(2.03, 2.11, 0.16, 0.28, backZ + 0.07, backZ + 0.15, c.lamp)
        put(2.3, 2.8, 0, 0.012, backZ + 0.5, backZ + 1.3, c.rugBlue)
      } else {
        put(1.3, 1.65, 0, 0.15, backZ + 0.3, backZ + 1.1, c.sofa)
        put(1.3, 1.4, 0.15, 0.3, backZ + 0.3, backZ + 1.1, c.sofa)
        put(1.9, 2.25, 0.09, 0.12, backZ + 0.5, backZ + 0.9, c.darkWood)
        put(1.25, 2.3, 0, 0.012, backZ + 0.2, backZ + 1.2, c.rug)
        put(2.5, 2.52, 0, 0.5, backZ + 0.12, backZ + 0.14, c.frame)
        put(2.44, 2.58, 0.5, 0.62, backZ + 0.06, backZ + 0.2, c.lamp)
        put(1.5, 2.2, 0.42, 0.62, backZ, backZ + 0.02, c.art)
      }
    }

    if (index === B.top) {
      // The wing stops two storeys short: its roof is a terrace.
      shell(B.x0, B.x1 + 0.06, HEAD, H, B.z0 - 0.06, B.z1 + 0.06)
      guard('z', B.z1 + 0.02, B.x0 + 0.1, B.x1 + 0.02, H, 0.22)
      guard('x', B.x1 + 0.02, B.z1 + 0.02, B.z0, H, 0.22)
      const lift = (x0: number, x1: number, y0: number, y1: number, z0: number, z1: number, colour: Color, lit = true) =>
        lit ? inside(x0, x1, H + y0, H + y1, z0, z1, colour) : shell(x0, x1, H + y0, H + y1, z0, z1, colour)
      lift(2.0, 2.8, 0, 0.16, 1.5, 1.68, panel, false)
      lift(2.02, 2.78, 0.16, 0.36, 1.52, 1.66, c.plant)
      lift(2.62, 2.8, 0, 0.16, -0.9, -0.1, panel, false)
      lift(2.64, 2.78, 0.16, 0.34, -0.88, -0.12, c.plant)
      lift(1.5, 2.2, 0, 0.012, 0.2, 1.1, c.darkWood)
      lift(1.6, 1.9, 0, 0.14, 0.5, 0.9, c.linen)
      lift(1.6, 1.9, 0.14, 0.28, 0.5, 0.56, c.linen)
    }
  }

  const restY = stackedY(index)
  const pour = new Plane(new Vector3(0, -1, 0), restY)
  const unpoured = new Plane(new Vector3(0, 1, 0), -restY)

  const modelMaterial = shared.model.clone()
  modelMaterial.clippingPlanes = [unpoured]
  const wallMaterial = shared.walls.clone()
  wallMaterial.clippingPlanes = [pour]
  const roomMaterial = shared.rooms.clone()
  roomMaterial.clippingPlanes = [pour]
  // Every point of a room knows which window it stands behind, and each
  // window has its own moment (scattered, the same on every visit) on the
  // evening's clock: the rooms' own colours are multiplied by day or by
  // lamplight accordingly, in the vertex shader.
  const roomGeometry = rooms.build()
  const points = roomGeometry.getAttribute('position')
  roomGeometry.computeBoundingBox()
  const left = roomGeometry.boundingBox?.min.x ?? 0
  const run = Math.max(1e-6, (roomGeometry.boundingBox?.max.x ?? 1) - left)
  const moments = new Float32Array(points.count)
  for (let point = 0; point < points.count; point++) {
    const bay = Math.min(LAMP_BAYS - 1, Math.floor(((points.getX(point) - left) / run) * LAMP_BAYS))
    const scattered = Math.sin((index * LAMP_BAYS + bay + 1) * 12.9898) * 43758.5453
    moments[point] = scattered - Math.floor(scattered)
  }
  roomGeometry.setAttribute('aLamp', new BufferAttribute(moments, 1))
  roomMaterial.onBeforeCompile = (shader) => {
    shader.uniforms.uLamps = lamps
    shader.uniforms.uDusk = dusk
    shader.uniforms.uDay = { value: ROOM_DAY }
    shader.uniforms.uOff = { value: ROOM_OFF }
    shader.uniforms.uNight = { value: ROOM_NIGHT }
    shader.vertexShader = shader.vertexShader
      .replace('#include <common>', '#include <common>\nattribute float aLamp;\nuniform float uLamps;\nuniform float uDusk;\nuniform vec3 uDay;\nuniform vec3 uOff;\nuniform vec3 uNight;')
      .replace('#include <color_vertex>', '#include <color_vertex>\nvColor.rgb *= mix(mix(uDay, uOff, uDusk), uNight, clamp((uLamps - aLamp * 0.8) / 0.2, 0.0, 1.0));')
  }
  roomMaterial.customProgramCacheKey = () => 'rooms-by-lamplight'
  const glassMaterial = shared.glass.clone()
  glassMaterial.clippingPlanes = [pour]
  const lineMaterial = new LineBasicMaterial({ color: TONE.ink, vertexColors: true, transparent: true, opacity: 0.75 })

  const group = new Group()
  const modelMesh = new Mesh(model.build(), modelMaterial)
  const glassMesh = new Mesh(glass.build(), glassMaterial)
  // Glass is drawn after what stands behind it.
  glassMesh.renderOrder = 2
  const real = [new Mesh(walls.build(), wallMaterial), new Mesh(roomGeometry, roomMaterial), glassMesh]
  const lineMesh = new LineSegments(lines.build(), lineMaterial)
  lineMesh.renderOrder = 3
  group.add(modelMesh, ...real, lineMesh)

  return { group, pour, unpoured, model: modelMesh, real, rooms: roomMaterial, lines: lineMaterial, restY }
}

const SKY: SkyPanel[] = [
  { colour: '#dcebff', strength: 5, at: [-8, -4, 34], size: [22, 9] },
  { colour: '#ffd9a8', strength: 4.5, at: [20, -3, 26], size: [14, 7] },
  { colour: '#9fc0ee', strength: 3.5, at: [34, 2, 4], size: [12, 16] },
  { colour: '#ffffff', strength: 6, at: [6, 6, 36], size: [9, 5] },
]

/** A room by day, and the same room with every lamp on at dusk. */
const ROOM_DAY = new Color(1.02, 0.9, 0.76)
const ROOM_NIGHT = new Color(1.62, 1.16, 0.62)
/** A room at nightfall, before its lamp is on. */
const ROOM_OFF = new Color(0.3, 0.34, 0.46)

export function createInteriorScene(canvas: HTMLCanvasElement, options: BuildingSceneOptions): BuildingSceneHandle {
  const renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' })
  renderer.outputColorSpace = SRGBColorSpace
  renderer.toneMapping = ACESFilmicToneMapping
  renderer.toneMappingExposure = 1.02
  renderer.localClippingEnabled = true
  renderer.setClearAlpha(0)

  const scene = new Scene()
  const environment = buildEnvironment(renderer, SKY)
  scene.environment = environment.texture

  const camera = new PerspectiveCamera(26, 1, 0.5, 200)
  const key = new DirectionalLight('#fff3e2', 2.2)
  key.position.set(-6, 13, 11)
  const fill = new HemisphereLight('#b7cbe6', '#101c30', 1.05)
  scene.add(key, fill)

  const gap = options.compact ? EXPLODED_GAP_COMPACT : EXPLODED_GAP
  const behind = { polygonOffset: true, polygonOffsetFactor: 1, polygonOffsetUnits: 1 }
  const shared: SharedMaterials = {
    model: new MeshStandardMaterial({ vertexColors: true, roughness: 1, metalness: 0, envMapIntensity: 0.4, side: DoubleSide, ...behind }),
    walls: new MeshStandardMaterial({ vertexColors: true, roughness: 0.88, metalness: 0, envMapIntensity: 0.5, side: DoubleSide, ...behind }),
    // Unlit: a room's brightness is its own, whatever the sun is doing outside.
    rooms: new MeshBasicMaterial({ vertexColors: true, side: DoubleSide, ...behind }),
    glass: new MeshStandardMaterial({
      color: TONE.glass,
      roughness: 0.04,
      metalness: 0.25,
      envMapIntensity: 2.4,
      transparent: true,
      opacity: 0.2,
      depthWrite: false,
      side: DoubleSide,
    }),
  }

  const model = new Group()
  scene.add(model)

  // The base of the model: the plot, the paving round the building, trees.
  const centreX = (A.x0 + B.x1) / 2
  const centreZ = (A.z0 + B.z1) / 2 + 0.35
  const siteW = B.x1 - A.x0 + 4
  const siteD = B.z1 - A.z0 + 4.2
  const site = new SolidBuilder()
  site.box(centreX, -0.12, centreZ, siteW, 0.24, siteD, new Color(TONE.site))
  site.box(centreX, 0.008, centreZ - 0.15, siteW - 1.6, 0.016, siteD - 1.9, new Color(TONE.paving))
  const trees: [number, number, number][] = [
    [-3.85, 2.75, 0.5],
    [-2.6, 3.5, 0.4],
    [-4.15, 0.5, 0.44],
    [3.75, 3.25, 0.42],
  ]
  for (const [x, z] of trees) site.box(x, 0.3, z, 0.07, 0.6, 0.07, new Color(TONE.trunk))
  const siteMaterial = new MeshStandardMaterial({ vertexColors: true, roughness: 0.95, metalness: 0, envMapIntensity: 0.35 })
  const siteMesh = new Mesh(site.build(), siteMaterial)
  const crownGeometry = new IcosahedronGeometry(1, 1)
  const crownMaterial = new MeshStandardMaterial({ color: TONE.tree, roughness: 0.95, metalness: 0, envMapIntensity: 0.3, flatShading: true })
  const crowns = trees.map(([x, z, size]) => {
    const crown = new Mesh(crownGeometry, crownMaterial)
    crown.position.set(x, 0.6 + size * 0.8, z)
    crown.scale.set(size, size * 1.15, size)
    return crown
  })

  const plateGeometry = new BoxGeometry(siteW, 0.24, siteD)
  const edgeGeometry = new EdgesGeometry(plateGeometry)
  plateGeometry.dispose()
  const edgeMaterial = new LineBasicMaterial({ color: TONE.line, transparent: true, opacity: 0.45 })
  const edges = new LineSegments(edgeGeometry, edgeMaterial)
  edges.position.set(centreX, -0.12, centreZ)

  const stake = (x: number, z: number) => new Vector3(x, 0.022, z)
  const footprintGeometry = new BufferGeometry().setFromPoints([
    stake(A.x0, A.z0),
    stake(A.x1, A.z0),
    stake(A.x1, B.z0),
    stake(B.x1, B.z0),
    stake(B.x1, B.z1),
    stake(B.x0, B.z1),
    stake(B.x0, A.z1),
    stake(A.x0, A.z1),
    stake(A.x0, A.z0),
  ])
  const footprintMaterial = new LineDashedMaterial({ color: '#f7f5f0', dashSize: 0.22, gapSize: 0.16, transparent: true, opacity: 0.8 })
  const footprint = new Line(footprintGeometry, footprintMaterial)
  footprint.computeLineDistances()

  const shadowTexture = buildContactShadow()
  const shadowMaterial = new MeshBasicMaterial({ map: shadowTexture, transparent: true, opacity: 0, depthWrite: false })
  const shadow = new Mesh(new PlaneGeometry(siteW - 1, siteD - 1), shadowMaterial)
  shadow.rotation.x = -Math.PI / 2
  shadow.position.set(centreX, 0.02, centreZ - 0.2)

  model.add(siteMesh, ...crowns, edges, footprint, shadow)

  // The evening's clock: 0 by day, 1 when every lamp is on.
  const lamps = { value: 0 }
  // Nightfall comes first: the rooms go dark, then light up.
  const nightfall = { value: 0 }
  const storeys = Array.from({ length: STOREYS }, (_, index) => buildStorey(index, shared, lamps, nightfall))
  storeys.forEach((rig) => model.add(rig.group))

  const guide = (corners: [number, number][]) => {
    const geometry = new BufferGeometry().setFromPoints(corners.flatMap(([x, z]) => [new Vector3(x, 0, z), new Vector3(x, 1, z)]))
    const material = new LineDashedMaterial({ color: TONE.line, dashSize: 0.16, gapSize: 0.2, transparent: true, opacity: 0.4 })
    const segments = new LineSegments(geometry, material)
    segments.computeLineDistances()
    model.add(segments)
    return { segments, material }
  }
  const mainGuide = guide([[A.x0, A.z1]])
  const wingGuide = guide([[B.x1, B.z1], [B.x1, B.z0], [B.x0, B.z1]])

  const ink = new Color(TONE.ink)
  const amber = new Color(TONE.amber)

  let progress = 0
  let pointerX = 0
  let pointerY = 0
  let easedX = 0
  let easedY = 0
  let running = false
  let disposed = false
  let firstFrame = true
  let last = 0
  let sampled = 0
  let sampledTime = 0
  const WARM_UP = 20
  const SAMPLE = 70

  const floatHeight = explodedHeight(gap)
  const builtHeight = STOREYS * H
  const target = new Vector3()
  const width = B.x1 - A.x0 + 5.4

  function frame(time: number) {
    if (disposed) return
    const seconds = time / 1000
    const delta = last === 0 ? 0.016 : Math.min(0.05, seconds - last)
    last = seconds

    const follow = 1 - Math.exp(-delta * 5)
    easedX += (pointerX - easedX) * follow
    easedY += (pointerY - easedY) * follow

    const dusk = clamp01((progress - STEPS_END) / (1 - STEPS_END))
    const top = modelTop(progress, gap)
    const open = clamp01((top - builtHeight) / (floatHeight - builtHeight))
    let landed = 0

    storeys.forEach((rig, index) => {
      const state = storeyState(index, progress)
      const floating = 1 - state.descent
      const bob = Math.sin(seconds * 0.9 + index * 0.85) * 0.06 * floating
      rig.group.position.set(((index % 3) - 1) * 0.14 * floating, storeyY(index, progress, gap) + bob, (((index + 1) % 3) - 1) * 0.1 * floating)
      rig.group.rotation.y = (index % 2 === 0 ? -1 : 1) * (0.025 + index * 0.003) * floating

      const level = rig.restY + state.material * (H + OVERSHOOT) - 0.02
      rig.pour.constant = level
      rig.unpoured.constant = -level
      rig.real.forEach((mesh) => (mesh.visible = state.material > 0))
      rig.model.visible = state.material < 1
      rig.lines.opacity = mix(0.75, 0.08, state.material)
      rig.lines.color.copy(state.active && state.material < 1 ? amber : ink)
      landed += state.descent
    })

    // The lamps come on at the end, one window after another.
    nightfall.value = clamp01((progress - STEPS_END) / 0.012)
    lamps.value = clamp01((progress - (STEPS_END + 0.012)) / (1 - STEPS_END - 0.02))

    mainGuide.segments.scale.y = top
    mainGuide.material.scale = top
    mainGuide.material.opacity = 0.42 * open
    const wingTop = storeyY(B.top, progress, gap) + H
    wingGuide.segments.scale.y = wingTop
    wingGuide.material.scale = wingTop
    wingGuide.material.opacity = 0.42 * open
    shadowMaterial.opacity = clamp01(landed / STOREYS) * 0.9

    // Evening: the daylight drops and the rooms carry the picture.
    key.intensity = mix(2.2, 0.45, dusk)
    fill.intensity = mix(1.05, 0.36, dusk)
    renderer.toneMappingExposure = mix(1.02, 0.94, dusk)

    const aspect = camera.aspect
    const bottom = -2.1
    const height = top + 1.5 - bottom
    const halfFov = (camera.fov * Math.PI) / 360
    const fitHeight = height / 2 / Math.tan(halfFov)
    const fitWidth = width / 2 / (Math.tan(halfFov) * aspect)
    const radius = Math.max(fitHeight, fitWidth) * 1.04
    const azimuth = 0.26 + progress * 0.4 + Math.sin(seconds * 0.11) * 0.08 + easedX * 0.16
    const elevation = 0.3 - easedY * 0.06 + (1 - open) * 0.04
    target.set(centreX, (top + 1.5 + bottom) / 2 + height * 0.02, 0.3)
    camera.position.set(
      target.x + Math.sin(azimuth) * Math.cos(elevation) * radius,
      target.y + Math.sin(elevation) * radius,
      target.z + Math.cos(azimuth) * Math.cos(elevation) * radius,
    )
    camera.lookAt(target)
    model.rotation.z = -easedX * 0.012

    renderer.render(scene, camera)

    if (firstFrame) {
      firstFrame = false
      options.onFirstFrame()
    }
    if (options.watchdog && sampled < WARM_UP + SAMPLE) {
      sampled++
      if (sampled > WARM_UP) sampledTime += delta
      if (sampled === WARM_UP + SAMPLE && sampledTime / SAMPLE > 0.04) options.onFail('slow')
    }
  }

  function resize() {
    const w = canvas.clientWidth
    const h = canvas.clientHeight
    if (w === 0 || h === 0) return
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, options.maxPixelRatio))
    renderer.setSize(w, h, false)
    camera.aspect = w / h
    camera.updateProjectionMatrix()
  }

  const onContextLost = (event: Event) => {
    event.preventDefault()
    options.onFail('context')
  }
  canvas.addEventListener('webglcontextlost', onContextLost)

  resize()

  return {
    setProgress(p) {
      progress = p
    },
    setPointer(x, y) {
      pointerX = x
      pointerY = y
    },
    setRunning(next) {
      if (disposed || next === running) return
      running = next
      last = 0
      renderer.setAnimationLoop(running ? frame : null)
    },
    resize,
    dispose() {
      if (disposed) return
      disposed = true
      renderer.setAnimationLoop(null)
      canvas.removeEventListener('webglcontextlost', onContextLost)
      const materials = new Set<Material>(Object.values(shared))
      scene.traverse((object) => {
        const mesh = object as Mesh
        if (mesh.geometry) mesh.geometry.dispose()
        const material = mesh.material
        if (Array.isArray(material)) material.forEach((item) => materials.add(item))
        else if (material) materials.add(material)
      })
      materials.forEach((material) => material.dispose())
      shadowTexture.dispose()
      environment.dispose()
      renderer.dispose()
    },
  }
}
