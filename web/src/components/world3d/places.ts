import {
  CircleGeometry,
  ConeGeometry,
  DoubleSide,
  ExtrudeGeometry,
  Group,
  Mesh,
  MeshStandardMaterial,
  PlaneGeometry,
  Shape,
  type Object3D,
} from 'three'
import { signTexture } from './brand'
import { box, cylinder, glass, mat, mesh, PALETTE } from './kit'

/**
 * The places the course sends a promoter to, each one a building a student
 * recognises in a Spanish town: the town hall with its clock and flags, the
 * property registry behind its columns, the notary's brass plate, the bank's
 * glass tower… Every model is drawn facing +z (local), centred on its
 * footprint, standing on y = 0, then turned to face the camera's side of the
 * board. Signs are in Spanish, as the student will find them in Spain; the
 * labels over them (worldScene) are in the student's language.
 */

export type PlaceKey =
  | 'site'
  | 'promotor'
  | 'ayuntamiento'
  | 'registro_propiedad'
  | 'registro_mercantil'
  | 'catastro'
  | 'notaria'
  | 'hacienda'
  | 'banco'
  | 'aseguradora'
  | 'estudio_arquitectura'
  | 'colegio_profesional'
  | 'constructora'
  | 'laboratorio_oct'
  | 'organismo_sectorial'
  | 'suministros'
  | 'oficina_ventas'
  | 'autoridad_laboral'

export interface PlaceDef {
  key: PlaceKey
  x: number
  z: number
  /** Which way its front looks: +z (south) or +x (east). */
  facing: 'z' | 'x'
  /** How tall it stands: where its pin and label sit. */
  height: number
  /** How far the camera stands when it flies here. */
  distance: number
  build(): Group
}

function sign(lines: string[], w: number, h: number, options: Parameters<typeof signTexture>[1] = {}): Mesh {
  const material = new MeshStandardMaterial({ map: signTexture(lines, options), roughness: 0.6, side: DoubleSide })
  const m = new Mesh(new PlaneGeometry(w, h), material)
  m.castShadow = false
  m.receiveShadow = false
  return m
}

/** A triangular pediment, `w` wide, `h` high, `d` deep, its base on y = 0. */
function pediment(w: number, h: number, d: number, color: string): Mesh {
  const shape = new Shape()
  shape.moveTo(-w / 2, 0)
  shape.lineTo(w / 2, 0)
  shape.lineTo(0, h)
  shape.closePath()
  const geometry = new ExtrudeGeometry(shape, { depth: d, bevelEnabled: false }).translate(0, 0, -d / 2)
  return mesh(geometry, mat(color))
}

/** A row of windows on the front face (z = +depth/2), floor after floor. */
function frontWindows(g: Group, width: number, depth: number, floors: number, floorH: number, startY: number, spacing = 2.6, color = 'glassDark') {
  for (let f = 0; f < floors; f++) {
    for (let x = -width / 2 + spacing / 2 + 0.4; x < width / 2 - 0.6; x += spacing) {
      g.add(box(1.3, 1.7, 0.12, x + 0.2, startY + f * floorH, depth / 2 + 0.02, mat(color, { rough: 0.25, metal: 0.2 }), { cast: false }))
    }
  }
}

function flag(colors: string[], x: number, y: number, z: number): Group {
  const g = new Group()
  g.add(cylinder(0.08, 3.2, 0, 0, 0, mat('steel', { metal: 0.6, rough: 0.4 })))
  colors.forEach((c, i) => g.add(box(1.8, 1.2 / colors.length, 0.04, 0.95, 2.0 + (colors.length - 1 - i) * (1.2 / colors.length), 0, mat(c, { rough: 0.7 }), { cast: false })))
  g.position.set(x, y, z)
  return g
}

const SPAIN = [PALETTE.red, PALETTE.yellow, PALETTE.yellow, PALETTE.red]
const EUROPE = ['#1F4FA0']

// ── The models ───────────────────────────────────────────────────────

function ayuntamiento(): Group {
  const g = new Group()
  const W = 22
  const D = 10
  g.add(box(W + 2, 0.5, D + 2, 0, 0, 0, mat('stoneDark')))
  g.add(box(W, 11, D, 0, 0.5, 0, mat('stone')))
  g.add(box(W + 0.6, 0.6, D + 0.6, 0, 11.5, 0, mat('stoneDark')))
  // Arcade on the ground floor.
  for (let i = -2; i <= 2; i++) g.add(box(2.6, 3.4, 0.2, i * 4, 0.5, D / 2 + 0.02, mat('navy3', { rough: 0.6 }), { cast: false }))
  frontWindows(g, W, D, 2, 3.3, 5, 4, 'glassDark')
  // The balcony where the mayor speaks, and its flags.
  g.add(box(7, 0.3, 1.4, 0, 4.4, D / 2 + 0.7, mat('stoneDark')))
  g.add(flag(SPAIN, -2.4, 4.7, D / 2 + 1.1))
  g.add(flag(EUROPE, 1.2, 4.7, D / 2 + 1.1))
  // The clock tower.
  g.add(box(5, 6.5, 5, 0, 12.1, 0, mat('stone')))
  const roof = mesh(new ConeGeometry(3.9, 3.4, 4).rotateY(Math.PI / 4), mat('tile'), 0, 20.3, 0)
  g.add(roof)
  const clock = mesh(new CircleGeometry(1.5, 32), mat('#FFFFFF', { rough: 0.5 }), 0, 16.2, 2.52, { cast: false })
  g.add(clock)
  g.add(box(0.12, 1.1, 0.05, 0, 16.2, 2.55, mat('ink'), { cast: false }))
  g.add(box(0.8, 0.12, 0.05, 0.35, 16.2, 2.55, mat('ink'), { cast: false }))
  const s = sign(['AYUNTAMIENTO'], 9, 1.2, { bg: PALETTE.stone, fg: PALETTE.navy })
  s.position.set(0, 9.6, D / 2 + 0.05)
  g.add(s)
  g.add(box(9, 0.3, 2.2, 0, 0.5, D / 2 + 1.6, mat('stoneDark')))
  return g
}

function registroPropiedad(): Group {
  const g = new Group()
  const W = 12
  const D = 10
  g.add(box(W + 1, 0.6, D + 1, 0, 0, 0, mat('stoneDark')))
  g.add(box(W, 9, D, 0, 0.6, -0.8, mat('stone')))
  for (let i = 0; i < 6; i++) g.add(cylinder(0.8, 7.2, -W / 2 + 1 + i * 2, 0.6, D / 2 - 0.4, mat('creme')))
  g.add(box(W, 0.8, 2.4, 0, 7.8, D / 2 - 0.9, mat('stoneDark')))
  const p = pediment(W, 2.4, 2.4, PALETTE.stone)
  p.position.set(0, 8.6, D / 2 - 0.9)
  g.add(p)
  const s = sign(['REGISTRO DE LA', 'PROPIEDAD'], 7.4, 1.6, { bg: PALETTE.navy, fg: '#FFFFFF' })
  s.position.set(0, 8.2, D / 2 + 0.32)
  g.add(s)
  g.add(box(3, 4.2, 0.2, 0, 0.6, D / 2 - 2.0, mat('navy3', { rough: 0.6 }), { cast: false }))
  return g
}

function notaria(): Group {
  const g = new Group()
  const W = 10
  const D = 10
  g.add(box(W, 12, D, 0, 0, 0, mat('brick')))
  g.add(box(W + 0.4, 0.5, D + 0.4, 0, 12, 0, mat('stoneDark')))
  frontWindows(g, W, D, 3, 3, 4, 3.2, 'glassDark')
  for (let f = 0; f < 3; f++) g.add(box(W - 2, 0.2, 1.1, 0, 3.6 + f * 3, D / 2 + 0.5, mat('navy3', { metal: 0.4, rough: 0.5 })))
  g.add(box(2, 3, 0.2, 0, 0, D / 2 + 0.02, mat('wood'), { cast: false }))
  const plate = sign(['NOTARÍA'], 3.2, 0.8, { bg: '#B8913A', fg: PALETTE.navy })
  plate.position.set(2.4, 2.2, D / 2 + 0.06)
  g.add(plate)
  return g
}

function catastro(): Group {
  const g = new Group()
  const W = 12
  const D = 10
  g.add(box(W, 12, D, 0, 0, 0, mat('#E3E6EA')))
  for (let f = 0; f < 3; f++) g.add(box(W + 0.05, 1.4, D + 0.05, 0, 2.8 + f * 3.4, 0, mat('glassDark', { rough: 0.2, metal: 0.2 }), { cast: false }))
  // A cadastral map on the roof: the parcels of the town.
  for (let i = 0; i < 4; i++) for (let j = 0; j < 3; j++) g.add(box(2.4, 0.15, 2.4, -4.2 + i * 2.8, 12, -2.8 + j * 2.8, mat((i + j) % 2 ? 'grass' : 'creme2'), { cast: false }))
  const s = sign(['CATASTRO'], 6, 1.1, { bg: PALETTE.brand, fg: '#FFFFFF' })
  s.position.set(0, 1.2, D / 2 + 0.05)
  g.add(s)
  return g
}

function hacienda(): Group {
  const g = new Group()
  const W = 14
  const D = 12
  g.add(box(W, 16, D, 0, 0, 0, mat('#ECEAE6')))
  for (let x = -W / 2 + 1; x <= W / 2 - 1; x += 1.6) g.add(box(0.35, 13, 0.6, x, 2.4, D / 2 + 0.3, mat('stoneDark')))
  g.add(box(W - 1, 13, 0.1, 0, 2.4, D / 2 + 0.02, glass(), { cast: false }))
  g.add(flag(SPAIN, W / 2 - 1, 16, 0))
  const s = sign(['HACIENDA'], 7, 1.3, { bg: PALETTE.navy, fg: PALETTE.gold })
  s.position.set(0, 1.1, D / 2 + 0.7)
  g.add(s)
  return g
}

function registroMercantil(): Group {
  const g = new Group()
  const W = 12
  const D = 10
  g.add(box(W, 9, D, 0, 0, 0, mat('stone')))
  g.add(box(W + 0.4, 0.5, D + 0.4, 0, 9, 0, mat('stoneDark')))
  for (let i = 0; i < 4; i++) g.add(cylinder(0.7, 6, -4.5 + i * 3, 0, D / 2 + 0.6, mat('creme')))
  g.add(box(W, 0.6, 1.6, 0, 6, D / 2 + 0.6, mat('stoneDark')))
  frontWindows(g, W, D, 2, 3.4, 1.4, 3, 'glassDark')
  const s = sign(['REGISTRO MERCANTIL'], 8, 1, { bg: PALETTE.navy3, fg: '#FFFFFF' })
  s.position.set(0, 7.3, D / 2 + 1.42)
  g.add(s)
  return g
}

function promotor(): Group {
  const g = new Group()
  g.add(box(12, 3.6, 9, 0, 0, 0, glass()))
  g.add(box(12.4, 0.4, 9.4, 0, 3.6, 0, mat('white')))
  g.add(box(13, 3.4, 9.6, 0.4, 4, -0.2, mat('white')))
  frontWindows(g, 13, 9.6, 1, 3, 4.8, 2.4, 'glass')
  // Desks and screens: where the search for the plot begins.
  g.add(box(3.2, 0.9, 1.2, -2, 0, 1.5, mat('wood'), { cast: false }))
  g.add(box(1.4, 0.9, 0.08, -2, 0.9, 1.4, mat('navy', { emissive: '#1A5FD0' }), { cast: false }))
  const s = sign(['OFICINA DEL PROMOTOR'], 8, 0.9, { bg: PALETTE.white, fg: PALETTE.navy, mark: true })
  s.position.set(0, 7.0, 4.82)
  g.add(s)
  return g
}

function banco(): Group {
  const g = new Group()
  g.add(box(14, 4, 14, 0, 0, 0, mat('navy2', { rough: 0.5 })))
  g.add(box(11, 26, 11, 0, 4, 0, glass()))
  for (let y = 7; y < 30; y += 3.2) g.add(box(11.1, 0.25, 11.1, 0, y, 0, mat('navy3', { rough: 0.4, metal: 0.4 }), { cast: false }))
  g.add(box(11.4, 1.6, 11.4, 0, 30, 0, mat('navy', { rough: 0.4 })))
  const s = sign(['BANCO'], 6, 1.3, { bg: PALETTE.navy, fg: '#FFFFFF' })
  s.position.set(0, 30.8, 5.75)
  g.add(s)
  const s2 = sign(['BANCO'], 6, 1.3, { bg: PALETTE.navy, fg: '#FFFFFF' })
  s2.position.set(0, 2, 7.05)
  g.add(s2)
  return g
}

function aseguradora(): Group {
  const g = new Group()
  g.add(box(12, 16, 10, 0, 0, 0, mat('white')))
  for (let f = 0; f < 4; f++) g.add(box(12.05, 1.6, 10.05, 0, 3 + f * 3.4, 0, mat('glass', { rough: 0.2, metal: 0.2 }), { cast: false }))
  // The shield of an insurer.
  const shield = new Shape()
  shield.moveTo(0, 1.6)
  shield.lineTo(1.3, 1.1)
  shield.lineTo(1.1, -0.4)
  shield.lineTo(0, -1.4)
  shield.lineTo(-1.1, -0.4)
  shield.lineTo(-1.3, 1.1)
  shield.closePath()
  g.add(mesh(new ExtrudeGeometry(shield, { depth: 0.3, bevelEnabled: false }), mat('brand', { rough: 0.4 }), 0, 17.6, 0))
  const s = sign(['SEGUROS'], 5.5, 1.1, { bg: PALETTE.brand, fg: '#FFFFFF' })
  s.position.set(0, 1.2, 5.05)
  g.add(s)
  return g
}

function estudioArquitectura(): Group {
  const g = new Group()
  g.add(box(12, 4, 9, -1, 0, 0, glass()))
  g.add(box(16, 4.2, 10, 1, 4, 0, mat('white')))
  g.add(box(14, 2.6, 0.1, 1, 4.8, 5.02, glass(), { cast: false }))
  // A drafting table and the model of a building, behind the glass.
  g.add(box(3, 1, 1.6, -3, 0, 1.2, mat('wood'), { cast: false }))
  g.add(box(1, 1.4, 1, -3, 1, 1.2, mat('white'), { cast: false }))
  const s = sign(['ESTUDIO DE ARQUITECTURA'], 9, 0.9, { bg: PALETTE.white, fg: PALETTE.ink })
  s.position.set(1, 8.7, 5.06)
  g.add(s)
  return g
}

function colegioProfesional(): Group {
  const g = new Group()
  g.add(box(11, 8, 9, 0, 0, 0, mat('#E8DDC8')))
  g.add(box(11.4, 0.5, 9.4, 0, 8, 0, mat('stoneDark')))
  frontWindows(g, 11, 9, 2, 3.2, 1.4, 3, 'glassDark')
  const s = sign(['COLEGIO PROFESIONAL'], 7.6, 0.9, { bg: PALETTE.navy3, fg: '#FFFFFF' })
  s.position.set(0, 6.9, 4.55)
  g.add(s)
  return g
}

function laboratorio(): Group {
  const g = new Group()
  g.add(box(10, 5, 8, 0, 0, 0, mat('white')))
  g.add(box(10.05, 1.4, 8.05, 0, 2.4, 0, glass(), { cast: false }))
  for (let i = 0; i < 3; i++) g.add(cylinder(0.5, 1.2, -3 + i * 1.2, 5, 1.5, mat('concrete'), { cast: false }))
  const s = sign(['LABORATORIO · OCT'], 7, 0.9, { bg: PALETTE.green, fg: '#FFFFFF' })
  s.position.set(0, 1, 4.05)
  g.add(s)
  return g
}

function constructora(): Group {
  const g = new Group()
  g.add(box(22, 0.12, 12, 0, 0, 0, mat('soil'), { cast: false }))
  for (const [x, c] of [[-7, PALETTE.brand], [-1, PALETTE.crane], [5, '#C9CED6']] as const) g.add(box(5.6, 2.5, 2.4, x, 0.12, -3.6, mat(c, { rough: 0.6 })))
  g.add(box(6, 3, 3.2, 6, 0.12, 2.6, mat('white')))
  g.add(box(6.05, 1, 3.25, 6, 1.4, 2.6, glass(), { cast: false }))
  // A truck.
  g.add(box(3, 2.2, 2.4, -7, 0.4, 3, mat('crane', { rough: 0.5 })))
  g.add(box(5, 1.8, 2.4, -3, 0.6, 3, mat('steel', { rough: 0.5 })))
  for (const x of [-8, -4.5, -1.8]) for (const z of [1.8, 4.2]) g.add(cylinder(1, 0.4, x, 0.12, z, mat('ink'), { cast: false }))
  const s = sign(['CONSTRUCTORA'], 6, 1, { bg: PALETTE.crane, fg: PALETTE.ink })
  s.position.set(6, 3.7, 4.25)
  g.add(s)
  return g
}

function organismoSectorial(): Group {
  const g = new Group()
  g.add(box(12, 7, 8, 0, 0, 0, mat('#E7EEF2')))
  frontWindows(g, 12, 8, 2, 3, 1.3, 3, 'glassDark')
  // A gauge in the river: the water authority's.
  g.add(box(0.3, 4, 0.3, 4, 0, -7, mat('red'), { cast: false }))
  const s = sign(['CONFEDERACIÓN', 'HIDROGRÁFICA'], 6.4, 1.4, { bg: '#1F6FA0', fg: '#FFFFFF' })
  s.position.set(0, 7.9, 0)
  g.add(s)
  // AESA's control tower, further along the bank.
  const tower = new Group()
  tower.add(cylinder(1.6, 12, 0, 0, 0, mat('white')))
  tower.add(cylinder(4, 2.4, 0, 12, 0, glass()))
  tower.add(cylinder(4.4, 0.4, 0, 14.4, 0, mat('navy3')))
  const aesa = sign(['AESA'], 2.6, 0.8, { bg: PALETTE.navy, fg: '#FFFFFF' })
  aesa.position.set(0, 10, 0.85)
  tower.add(aesa)
  tower.position.set(-40, 0, -2)
  g.add(tower)
  return g
}

function suministros(): Group {
  const g = new Group()
  g.add(box(14, 0.12, 8, -2, 0, 0, mat('concrete'), { cast: false }))
  for (const x of [-6, -2, 2]) {
    g.add(box(2.4, 2.6, 2, x, 0.12, 0, mat('steel', { rough: 0.5 })))
    g.add(cylinder(0.4, 1.4, x, 2.7, 0, mat('#D9DEE6'), { cast: false }))
  }
  // Fence.
  for (const z of [-4, 4]) g.add(box(14, 2, 0.08, -2, 0, z, mat('steel', { opacity: 0.45 }), { cast: false }))
  // The water tank.
  g.add(cylinder(0.6, 9, 9, 0, 0, mat('concrete')))
  g.add(cylinder(4.6, 3.4, 9, 9, 0, mat('white')))
  const s = sign(['AGUA · LUZ · TELECOM'], 7, 0.9, { bg: PALETTE.navy3, fg: '#FFFFFF' })
  s.position.set(-2, 3.6, 4.1)
  g.add(s)
  return g
}

function oficinaVentas(): Group {
  const g = new Group()
  g.add(box(5.4, 0.3, 4, 0, 0, 0, mat('wood')))
  g.add(box(5, 3, 3.6, 0, 0.3, 0, glass()))
  g.add(box(5.8, 0.45, 4.4, 0, 3.3, 0, mat('navy')))
  const s = sign(['OFICINA DE VENTAS'], 5.4, 0.8, { bg: PALETTE.navy, fg: '#FFFFFF', mark: true })
  s.position.set(0, 4.25, 0)
  g.add(s)
  return g
}

function autoridadLaboral(): Group {
  const g = new Group()
  g.add(box(10, 10, 9, 0, 0, 0, mat('#E5E8EC')))
  frontWindows(g, 10, 9, 3, 3, 1.3, 2.8, 'glassDark')
  const s = sign(['SEGURIDAD SOCIAL'], 6.6, 0.9, { bg: '#1F4FA0', fg: '#FFFFFF' })
  s.position.set(0, 9.2, 4.55)
  g.add(s)
  return g
}

/** The site itself has no building of its own: site.ts dresses it, step after step. */
function site(): Group {
  return new Group()
}

/**
 * Where everything stands. The west column faces east, the east column
 * faces east too (towards the board's edge and the camera), the south row
 * faces south onto its plaza and park, the north strip faces the back street.
 */
export const PLACES: PlaceDef[] = [
  { key: 'site', x: 0, z: 0, facing: 'z', height: 20, distance: 92, build: site },
  { key: 'ayuntamiento', x: -14.5, z: 32, facing: 'z', height: 24, distance: 78, build: ayuntamiento },
  { key: 'notaria', x: 8, z: 30, facing: 'z', height: 13, distance: 60, build: notaria },
  { key: 'registro_propiedad', x: 21.5, z: 30, facing: 'z', height: 12, distance: 60, build: registroPropiedad },
  { key: 'catastro', x: -52, z: -18, facing: 'x', height: 13, distance: 62, build: catastro },
  { key: 'hacienda', x: -52, z: 2, facing: 'x', height: 17, distance: 68, build: hacienda },
  { key: 'registro_mercantil', x: -51, z: 22, facing: 'x', height: 10, distance: 60, build: registroMercantil },
  { key: 'autoridad_laboral', x: -51, z: 40, facing: 'x', height: 11, distance: 60, build: autoridadLaboral },
  { key: 'promotor', x: -51, z: 58, facing: 'x', height: 9, distance: 60, build: promotor },
  { key: 'aseguradora', x: 51, z: -20, facing: 'x', height: 19, distance: 68, build: aseguradora },
  { key: 'banco', x: 51, z: 0, facing: 'x', height: 32, distance: 91, build: banco },
  { key: 'estudio_arquitectura', x: 51, z: 22, facing: 'x', height: 9, distance: 60, build: estudioArquitectura },
  { key: 'colegio_profesional', x: 51, z: 40, facing: 'x', height: 9, distance: 57, build: colegioProfesional },
  { key: 'laboratorio_oct', x: 51, z: 58, facing: 'x', height: 6, distance: 52, build: laboratorio },
  { key: 'constructora', x: -50, z: -45, facing: 'z', height: 5, distance: 62, build: constructora },
  { key: 'organismo_sectorial', x: 12, z: -46, facing: 'z', height: 9, distance: 78, build: organismoSectorial },
  { key: 'suministros', x: 52, z: -46, facing: 'z', height: 13, distance: 68, build: suministros },
  { key: 'oficina_ventas', x: -1, z: 24, facing: 'z', height: 5, distance: 44, build: oficinaVentas },
]

export function placeDef(key: PlaceKey): PlaceDef {
  return PLACES.find((p) => p.key === key) ?? PLACES[0]
}

/** The place built and set on the board. */
export function buildPlace(def: PlaceDef): Object3D {
  const g = def.build()
  g.position.set(def.x, 0, def.z)
  g.rotation.y = def.facing === 'x' ? Math.PI / 2 : 0
  g.name = `place:${def.key}`
  g.userData.place = def.key
  return g
}
