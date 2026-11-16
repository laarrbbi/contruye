import {
  BoxGeometry,
  ConeGeometry,
  CylinderGeometry,
  Group,
  IcosahedronGeometry,
  InstancedMesh,
  Matrix4,
  Mesh,
  Object3D,
  Quaternion,
  Vector3,
  type Material,
} from 'three'
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js'
import { box, cylinder, mat, PALETTE } from './kit'

/**
 * The town around the plot: a diorama board, its streets, the plot's
 * neighbours, a plaza, a river, a railway, trees and lamps. The layout is
 * fixed and the institutions (places.ts) stand on its blocks.
 *
 *   z −74 ─ railway ─ river ─ north strip ─ back street ─ OUR BLOCK (plot at 0,0) ─ front street ─ plaza · notary · registry ─ z +70
 *   x −78 ─ west blocks (Hacienda, Catastro) ─ cross street ─ our block ─ cross street ─ east blocks (bank, insurer, architects) ─ x +78
 */

export const BOARD = { x0: -78, x1: 78, z0: -74, z1: 70 }

/** The plot: 20 × 17.2 m, 344 m², the course's own example of a solar. */
export const PLOT = { x0: -10, x1: 10, z0: -8.6, z1: 8.6 }

/** How deep the ground goes under the plot: room for the excavation. */
export const DEPTH = 2.8

export const STREETS = {
  front: { z0: 11, z1: 19 },
  back: { z0: -38, z1: -30 },
  west: { x0: -36, x1: -28 },
  east: { x0: 28, x1: 36 },
}

export const RIVER = { z0: -62, z1: -52 }
export const RAIL = { z0: -70, z1: -66 }

/** Many copies of one shape in a single draw: windows, trees, lamps, dashes. */
class Batch {
  private matrices: Matrix4[] = []
  constructor(
    private geometry: BoxGeometry | CylinderGeometry | ConeGeometry | IcosahedronGeometry,
    private material: Material,
    private shadows = true,
  ) {}

  add(x: number, y: number, z: number, sx: number, sy: number, sz: number, rotY = 0) {
    const m = new Matrix4()
    m.compose(new Vector3(x, y, z), new Quaternion().setFromAxisAngle(new Vector3(0, 1, 0), rotY), new Vector3(sx, sy, sz))
    this.matrices.push(m)
  }

  build(): InstancedMesh {
    const mesh = new InstancedMesh(this.geometry, this.material, Math.max(1, this.matrices.length))
    this.matrices.forEach((m, i) => mesh.setMatrixAt(i, m))
    mesh.count = this.matrices.length
    mesh.castShadow = this.shadows
    mesh.receiveShadow = true
    mesh.instanceMatrix.needsUpdate = true
    return mesh
  }
}

const unit = new BoxGeometry(1, 1, 1).translate(0, 0.5, 0)

export interface Town {
  root: Group
  /** The neighbours' and the town's windows: they light up at dusk. */
  windows: InstancedMesh
  lamps: InstancedMesh
}

export function buildTown(): Town {
  const root = new Group()
  root.name = 'town'

  // ── The board ──────────────────────────────────────────────────────
  const w = BOARD.x1 - BOARD.x0
  const d = BOARD.z1 - BOARD.z0
  const cx = (BOARD.x0 + BOARD.x1) / 2
  const cz = (BOARD.z0 + BOARD.z1) / 2
  // The board is hollow under the plot, down to DEPTH: the excavation (site.ts)
  // digs into it, and the soil that fills it until then belongs to the plot.
  const slab = new Mesh(new RoundedBoxGeometry(w, 3, d, 4, 1.5), mat('boardEdge', { rough: 0.6 }))
  slab.position.set(cx, -DEPTH - 1.5, cz)
  slab.receiveShadow = true
  root.add(slab)
  const fill = mat('board', { rough: 0.95 })
  const fx0 = BOARD.x0 + 0.6
  const fx1 = BOARD.x1 - 0.6
  const fz0 = BOARD.z0 + 0.6
  const fz1 = BOARD.z1 - 0.6
  root.add(box(PLOT.x0 - fx0, DEPTH, fz1 - fz0, (fx0 + PLOT.x0) / 2, -DEPTH, (fz0 + fz1) / 2, fill, { cast: false }))
  root.add(box(fx1 - PLOT.x1, DEPTH, fz1 - fz0, (PLOT.x1 + fx1) / 2, -DEPTH, (fz0 + fz1) / 2, fill, { cast: false }))
  root.add(box(PLOT.x1 - PLOT.x0, DEPTH, PLOT.z0 - fz0, 0, -DEPTH, (fz0 + PLOT.z0) / 2, fill, { cast: false }))
  root.add(box(PLOT.x1 - PLOT.x0, DEPTH, fz1 - PLOT.z1, 0, -DEPTH, (PLOT.z1 + fz1) / 2, fill, { cast: false }))

  const dashes = new Batch(unit, mat('roadLine', { rough: 0.6 }), false)
  const roadMat = mat('road', { rough: 0.9 })
  const walkMat = mat('sidewalk', { rough: 0.95 })

  // ── Streets along x ───────────────────────────────────────────────
  for (const street of [STREETS.front, STREETS.back]) {
    root.add(box(w - 2, 0.06, street.z1 - street.z0, cx, 0, (street.z0 + street.z1) / 2, roadMat, { cast: false }))
    for (let x = BOARD.x0 + 4; x < BOARD.x1 - 4; x += 6) {
      if (Math.abs(x - 32) < 6 || Math.abs(x + 32) < 6) continue
      dashes.add(x, 0.065, (street.z0 + street.z1) / 2, 2.6, 0.02, 0.25)
    }
    // Sidewalks either side.
    root.add(box(w - 2, 0.18, 2.4, cx, 0, street.z0 - 1.2, walkMat, { cast: false }))
    root.add(box(w - 2, 0.18, 2.4, cx, 0, street.z1 + 1.2, walkMat, { cast: false }))
  }
  // ── Streets along z ───────────────────────────────────────────────
  for (const street of [STREETS.west, STREETS.east]) {
    const sx = (street.x0 + street.x1) / 2
    const z0 = RIVER.z1 + 2
    const z1 = BOARD.z1 - 1
    root.add(box(street.x1 - street.x0, 0.065, z1 - z0, sx, 0, (z0 + z1) / 2, roadMat, { cast: false }))
    for (let z = z0 + 3; z < z1 - 3; z += 6) {
      if (Math.abs(z - 15) < 6 || Math.abs(z + 34) < 6) continue
      dashes.add(sx, 0.07, z, 0.25, 0.02, 2.6)
    }
    root.add(box(2.2, 0.18, z1 - z0, street.x0 - 1.1, 0, (z0 + z1) / 2, walkMat, { cast: false }))
    root.add(box(2.2, 0.18, z1 - z0, street.x1 + 1.1, 0, (z0 + z1) / 2, walkMat, { cast: false }))
  }
  // Zebra crossings in front of the plot and at the plaza.
  for (const x of [-20, 22]) {
    for (let i = 0; i < 6; i++) dashes.add(x - 3 + i * 1.2, 0.07, (STREETS.front.z0 + STREETS.front.z1) / 2, 0.6, 0.02, 6.4)
  }
  root.add(dashes.build())

  // ── The plaza (Plaza Mayor), in front of the town hall ────────────
  // The town hall's back is to the street; its front opens onto the plaza.
  root.add(box(25, 0.2, 24, -14.5, 0, 50, mat('creme2', { rough: 0.95 }), { cast: false }))
  const fountain = new Group()
  fountain.add(cylinder(6.5, 0.6, 0, 0, 0, mat('stone')))
  fountain.add(cylinder(5.4, 0.5, 0, 0.12, 0, mat('water', { rough: 0.15, metal: 0.1 }), { cast: false }))
  fountain.add(cylinder(0.8, 2.2, 0, 0, 0, mat('stone')))
  fountain.add(cylinder(1.8, 0.3, 0, 1.6, 0, mat('stone')))
  fountain.position.set(-14.5, 0.2, 50)
  root.add(fountain)

  // ── Grass: the courtyard of our block, the river banks ────────────
  root.add(box(52, 0.12, 15, 0, 0, -19, mat('grass'), { cast: false }))
  root.add(box(26, 0.12, 24, 15, 0, 51, mat('grass'), { cast: false }))
  root.add(box(w - 2, 0.12, 12, cx, 0, RIVER.z0 - 1, mat('grass'), { cast: false }))

  // ── River, bridge, railway ────────────────────────────────────────
  root.add(box(w - 2, 0.1, RIVER.z1 - RIVER.z0, cx, -0.02, (RIVER.z0 + RIVER.z1) / 2, mat('water', { rough: 0.12, metal: 0.15 }), { cast: false }))
  const bridge = new Group()
  bridge.add(box(10, 0.8, RIVER.z1 - RIVER.z0 + 6, 0, 0.3, 0, mat('concrete')))
  bridge.add(box(0.4, 1, RIVER.z1 - RIVER.z0 + 6, -4.8, 1.1, 0, mat('concreteDark')))
  bridge.add(box(0.4, 1, RIVER.z1 - RIVER.z0 + 6, 4.8, 1.1, 0, mat('concreteDark')))
  bridge.position.set(32, 0, (RIVER.z0 + RIVER.z1) / 2)
  root.add(bridge)
  const sleepers = new Batch(unit, mat('wood'), false)
  for (let x = BOARD.x0 + 2; x < BOARD.x1 - 2; x += 1.6) sleepers.add(x, 0.05, (RAIL.z0 + RAIL.z1) / 2, 0.5, 0.12, 3.4)
  root.add(sleepers.build())
  for (const dz of [-0.75, 0.75]) root.add(box(w - 4, 0.14, 0.14, cx, 0.17, (RAIL.z0 + RAIL.z1) / 2 + dz, mat('steel', { rough: 0.4, metal: 0.6 }), { cast: false }))

  // ── Neighbours ────────────────────────────────────────────────────
  const windows = new Batch(unit, mat('glassDark', { rough: 0.2, metal: 0.2 }), false)
  const neighbour = (x0: number, x1: number, z0: number, z1: number, floors: number, color: string, facing: 'z' | 'x' | 'both' = 'z') => {
    const h = floors * 3 + 0.6
    const g = new Group()
    g.add(box(x1 - x0, h, z1 - z0, (x0 + x1) / 2, 0, (z0 + z1) / 2, mat(color)))
    g.add(box(x1 - x0 + 0.4, 0.5, z1 - z0 + 0.4, (x0 + x1) / 2, h, (z0 + z1) / 2, mat('roof')))
    for (let f = 0; f < floors; f++) {
      const y = 1.1 + f * 3
      if (facing !== 'x') {
        for (let x = x0 + 1.6; x < x1 - 1; x += 3) {
          windows.add(x + 0.6, y, z1 + 0.02, 1.4, 1.6, 0.08)
          windows.add(x + 0.6, y, z0 - 0.02, 1.4, 1.6, 0.08)
        }
      }
      if (facing !== 'z') {
        for (let z = z0 + 1.6; z < z1 - 1; z += 3) {
          windows.add(x1 + 0.02, y, z + 0.6, 0.08, 1.6, 1.4)
          windows.add(x0 - 0.02, y, z + 0.6, 0.08, 1.6, 1.4)
        }
      }
    }
    root.add(g)
  }
  // Either side of the plot: party walls (medianeras), as in a Spanish street.
  neighbour(-27, -10.4, -8.6, 8.6, 4, PALETTE.plaster)
  neighbour(10.4, 27, -8.6, 8.6, 5, '#EADFCF')
  // Behind, across the courtyard.
  neighbour(-27, -6, -28, -24, 3, '#E4E1DA')
  neighbour(-4, 27, -28, -24, 4, PALETTE.plaster)
  // The rest of the town, quieter.
  // Behind the institutions, never between them and the camera: every
  // institution shows its front to the south-east, where the view comes from.
  neighbour(-76, -62, -26, -10, 4, '#E7E3DB', 'both')
  neighbour(-76, -64, 30, 46, 3, PALETTE.plaster, 'both')
  neighbour(-22, -2, -48, -42, 3, '#E4E1DA', 'both')

  const windowMesh = windows.build()
  windowMesh.name = 'windows'
  root.add(windowMesh)

  // ── Trees ─────────────────────────────────────────────────────────
  const trunks = new Batch(new CylinderGeometry(0.18, 0.24, 1, 6).translate(0, 0.5, 0), mat('wood'))
  const crowns = new Batch(new IcosahedronGeometry(1, 0), mat('grassDark', { rough: 0.9 }))
  const tree = (x: number, z: number, s = 1) => {
    trunks.add(x, 0.15, z, s, 1.8 * s, s)
    crowns.add(x, 2.6 * s, z, 1.6 * s, 1.8 * s, 1.6 * s, (x * 13 + z * 7) % 6)
  }
  for (let x = BOARD.x0 + 5; x < BOARD.x1 - 4; x += 9) {
    if (Math.abs(x) < 12 || Math.abs(x - 32) < 6 || Math.abs(x + 32) < 6) continue
    tree(x, STREETS.front.z1 + 1.2, 0.9)
    tree(x + 4, STREETS.back.z0 - 1.2, 0.9)
  }
  const spots: [number, number][] = [[-25, 42], [-4, 42], [-25, 60], [-4, 60], [-8, -16], [6, -18], [18, -15], [-20, -18], [38, -48], [-36, -46], [70, -36], [-70, 0], [72, 64], [64, -20]]
  // The park in front of the notary and the registry.
  for (const x of [4, 12, 20, 26]) for (const z of [44, 52, 60]) spots.push([x + ((z / 4) % 2), z])
  for (const [x, z] of spots) tree(x, z, 1.1)
  for (let x = BOARD.x0 + 6; x < BOARD.x1 - 6; x += 7) tree(x, RIVER.z1 + 3.5, 0.8)
  root.add(trunks.build())
  root.add(crowns.build())

  // ── Lamps ─────────────────────────────────────────────────────────
  const poles = new Batch(new CylinderGeometry(0.08, 0.1, 1, 6).translate(0, 0.5, 0), mat('navy3', { rough: 0.5, metal: 0.4 }), false)
  const bulbs = new Batch(new IcosahedronGeometry(1, 1), mat('#FFF3D6', { emissive: '#000000' }), false)
  for (let x = BOARD.x0 + 8; x < BOARD.x1 - 6; x += 14) {
    for (const z of [STREETS.front.z0 - 0.4, STREETS.front.z1 + 0.4]) {
      poles.add(x, 0.15, z, 1, 4.2, 1)
      bulbs.add(x, 4.4, z, 0.32, 0.22, 0.32)
    }
  }
  root.add(poles.build())
  const lamps = bulbs.build()
  lamps.name = 'lamps'
  root.add(lamps)

  // ── A few parked cars ─────────────────────────────────────────────
  const car = (x: number, z: number, rot: number, color: string) => {
    const g = new Group()
    g.add(box(4.2, 0.9, 1.8, 0, 0.35, 0, mat(color, { rough: 0.4, metal: 0.3 })))
    g.add(box(2.4, 0.7, 1.6, -0.2, 1.25, 0, mat('glassDark', { rough: 0.2 })))
    for (const [wx, wz] of [[-1.3, -0.9], [1.3, -0.9], [-1.3, 0.9], [1.3, 0.9]]) g.add(cylinder(0.7, 0.3, wx, 0.1, wz, mat('ink'), { cast: false }))
    g.position.set(x, 0, z)
    g.rotation.y = rot
    root.add(g)
  }
  car(-40, 12.6, 0, '#D9DEE6')
  car(46, 17.4, Math.PI, PALETTE.brand)
  car(-58, 17.4, Math.PI, '#E8E3D8')
  car(31, 30, Math.PI / 2, PALETTE.navy3)
  car(14, -32, 0, '#C9CED6')

  return { root, windows: windowMesh, lamps }
}

/** Where a pin, label or camera aims at a place: its centre and how tall it stands. */
export interface Anchor {
  x: number
  z: number
  height: number
}

export function anchorOf(object: Object3D, height: number): Anchor {
  return { x: object.position.x, z: object.position.z, height }
}
