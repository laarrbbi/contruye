import {
  BoxGeometry,
  Color,
  CylinderGeometry,
  Group,
  Mesh,
  MeshStandardMaterial,
  type BufferGeometry,
  type Material,
  type Object3D,
} from 'three'

/**
 * What every model of the 3D world is made of: a palette in CONSTRUYE's
 * colours, a few primitives placed from their base (a box standing on y),
 * shared materials, easings, and the «piece»: anything that can appear in the
 * world, popping up from the ground, and go again.
 *
 * Units are metres. x runs along the plot's street, z towards the viewer, y up.
 */

export const PALETTE = {
  navy: '#0B1A2E',
  navy2: '#12253F',
  navy3: '#1F3A5F',
  brand: '#006FFE',
  gold: '#E8A33D',
  creme: '#F6F2EA',
  creme2: '#EFE9DE',
  ink: '#141A22',
  board: '#E6E9EF',
  boardEdge: '#1F3A5F',
  road: '#A7AEBB',
  roadLine: '#F4F5F7',
  sidewalk: '#D5D9E1',
  grass: '#B9D3A0',
  grassDark: '#93B57A',
  soil: '#B98E62',
  soilDark: '#8C6A47',
  stone: '#E9E1D3',
  stoneDark: '#CFC3AE',
  brick: '#C9875F',
  white: '#FBFBFA',
  plaster: '#F1EEE8',
  concrete: '#B8BCC2',
  concreteDark: '#9097A0',
  glass: '#8FB8DD',
  glassDark: '#4E7EA8',
  wood: '#B07C4F',
  roof: '#7D8691',
  tile: '#B5583A',
  water: '#6FA8D6',
  steel: '#69727D',
  crane: '#F2B632',
  red: '#C8102E',
  yellow: '#F1BF00',
  green: '#2E9E6B',
  lit: '#FFD9A0',
} as const

export type PaletteKey = keyof typeof PALETTE

const materials = new Map<string, MeshStandardMaterial>()

/** One material per colour and finish, shared by every model. */
export function mat(color: PaletteKey | string, finish: { rough?: number; metal?: number; emissive?: string; opacity?: number } = {}): MeshStandardMaterial {
  const hex = color in PALETTE ? PALETTE[color as PaletteKey] : color
  const key = `${hex}|${finish.rough ?? 0.85}|${finish.metal ?? 0}|${finish.emissive ?? ''}|${finish.opacity ?? 1}`
  let material = materials.get(key)
  if (!material) {
    material = new MeshStandardMaterial({
      color: new Color(hex),
      roughness: finish.rough ?? 0.85,
      metalness: finish.metal ?? 0,
      emissive: finish.emissive ? new Color(finish.emissive) : new Color(0x000000),
      emissiveIntensity: finish.emissive ? 1 : 0,
      transparent: (finish.opacity ?? 1) < 1,
      opacity: finish.opacity ?? 1,
      depthWrite: (finish.opacity ?? 1) >= 1,
    })
    materials.set(key, material)
  }
  return material
}

export const glass = () => mat('glass', { rough: 0.12, metal: 0.2 })

const unitBox = new BoxGeometry(1, 1, 1).translate(0, 0.5, 0)
const unitCylinder = new CylinderGeometry(0.5, 0.5, 1, 16).translate(0, 0.5, 0)

interface Placement {
  cast?: boolean
  receive?: boolean
}

/** A box of w × h × d standing on (x, y, z): its base, not its centre. */
export function box(w: number, h: number, d: number, x: number, y: number, z: number, material: Material, place: Placement = {}): Mesh {
  const mesh = new Mesh(unitBox, material)
  mesh.scale.set(w, h, d)
  mesh.position.set(x, y, z)
  mesh.castShadow = place.cast ?? true
  mesh.receiveShadow = place.receive ?? true
  return mesh
}

/** A cylinder of diameter `diameter` and height h standing on (x, y, z). */
export function cylinder(diameter: number, h: number, x: number, y: number, z: number, material: Material, place: Placement = {}): Mesh {
  const mesh = new Mesh(unitCylinder, material)
  mesh.scale.set(diameter, h, diameter)
  mesh.position.set(x, y, z)
  mesh.castShadow = place.cast ?? true
  mesh.receiveShadow = place.receive ?? true
  return mesh
}

/** A mesh of its own geometry, placed. */
export function mesh(geometry: BufferGeometry, material: Material, x = 0, y = 0, z = 0, place: Placement = {}): Mesh {
  const m = new Mesh(geometry, material)
  m.position.set(x, y, z)
  m.castShadow = place.cast ?? true
  m.receiveShadow = place.receive ?? true
  return m
}

export function group(...children: Object3D[]): Group {
  const g = new Group()
  for (const child of children) g.add(child)
  return g
}

// ── Easings ────────────────────────────────────────────────────────────

export const clamp01 = (v: number) => (v < 0 ? 0 : v > 1 ? 1 : v)
export const lerp = (a: number, b: number, t: number) => a + (b - a) * t
export const easeOutCubic = (t: number) => 1 - Math.pow(1 - clamp01(t), 3)
export const easeInOutCubic = (t: number) => {
  const x = clamp01(t)
  return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2
}
/** Past 1 and back: the little bounce of something landing. */
export const easeOutBack = (t: number) => {
  const x = clamp01(t)
  const c1 = 1.70158
  const c3 = c1 + 1
  return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2)
}
/** The share of `t` that belongs to the slice [from, to]. */
export const slice = (t: number, from: number, to: number) => clamp01((t - from) / (to - from))

// ── Pieces ─────────────────────────────────────────────────────────────

/**
 * Anything that appears in the world: a sign, a fence, a storey, a crane.
 * `set(t)` draws it from 0 (not there) to 1 (in place); the scene runs t
 * over `duration` seconds when the piece arrives, and back when it goes.
 */
export interface Piece {
  key: string
  root: Object3D
  duration: number
  set(t: number): void
  /** Optional life once in place (a crane turning, water flowing). */
  tick?(seconds: number): void
}

/**
 * The default arrival: up from below the ground with a small bounce, each
 * child a moment after the previous one, so a group of things pops up in a
 * wave rather than all at once.
 */
export function popPiece(key: string, root: Object3D, options: { duration?: number; stagger?: number; lift?: number } = {}): Piece {
  const children = [...root.children]
  const base = children.map((c) => ({ y: c.position.y, scale: c.scale.clone() }))
  const stagger = options.stagger ?? 0.5
  const lift = options.lift ?? 1.2
  const n = Math.max(1, children.length)
  return {
    key,
    root,
    duration: options.duration ?? 1.1,
    set(t) {
      root.visible = t > 0.001
      children.forEach((child, i) => {
        const start = n === 1 ? 0 : (i / (n - 1)) * stagger
        const local = slice(t, start, start + (1 - stagger))
        const s = Math.max(0.0001, easeOutBack(local))
        const b = base[i]
        child.scale.set(b.scale.x * s, b.scale.y * s, b.scale.z * s)
        child.position.y = b.y - (1 - easeOutCubic(local)) * lift
        child.visible = local > 0.001
      })
    },
  }
}

/**
 * Growing out of the ground, scaling in height only: a column, a wall, a
 * storey being poured, the crane's mast.
 */
export function risePiece(key: string, root: Object3D, options: { duration?: number; stagger?: number } = {}): Piece {
  const children = [...root.children]
  const base = children.map((c) => c.scale.clone())
  const stagger = options.stagger ?? 0.35
  const n = Math.max(1, children.length)
  return {
    key,
    root,
    duration: options.duration ?? 1.4,
    set(t) {
      root.visible = t > 0.001
      children.forEach((child, i) => {
        const start = n === 1 ? 0 : (i / (n - 1)) * stagger
        const local = easeInOutCubic(slice(t, start, start + (1 - stagger)))
        child.scale.y = Math.max(0.0001, base[i].y * local)
        child.visible = local > 0.001
      })
    },
  }
}
