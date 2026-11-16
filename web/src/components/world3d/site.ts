import {
  BoxGeometry,
  BufferGeometry,
  Color,
  CylinderGeometry,
  EdgesGeometry,
  Float32BufferAttribute,
  Group,
  LineBasicMaterial,
  LineSegments,
  Mesh,
  MeshStandardMaterial,
  PlaneGeometry,
  SphereGeometry,
  TorusGeometry,
  type Object3D,
} from 'three'
import { hoardingTexture, signTexture } from './brand'
import {
  box,
  clamp01,
  cylinder,
  easeInOutCubic,
  easeOutBack,
  easeOutCubic,
  glass,
  group,
  lerp,
  mat,
  mesh,
  PALETTE,
  popPiece,
  risePiece,
  slice,
  type Piece,
} from './kit'
import { DEPTH, PLOT } from './town'

/**
 * The plot and everything that happens on it, from the «SE VENDE» sign to
 * the keys. Each stage is a piece (kit.ts) the course's steps call by key;
 * the state machine (stages.ts) says which are standing after each module.
 *
 * The building is the course's own example (the professional's «números
 * gordos del solar», Module 1): a plot of 344 m², ground floor plus five
 * (B+5), floors of 316.04 · 316.04 · 316.04 · 294.64 · 229.24 · 114.49 m²
 * stepping back from the street, 18 homes of which 2 are penthouses. The
 * floors keep those areas on a 18.6 m frontage.
 */

export const FRONTAGE = 18.6
const BACK = PLOT.z0 + 0.1
/** Each floor's depth from the back wall: its area over the frontage. */
const AREAS = [316.04, 316.04, 316.04, 294.64, 229.24, 114.49]
export const FLOORS = AREAS.map((area, i) => {
  const depth = Math.min(17, area / FRONTAGE)
  const y = i === 0 ? 0 : 3.6 + (i - 1) * 3.0
  const h = i === 0 ? 3.6 : 3.0
  return { index: i, depth, y, h, z0: BACK, z1: BACK + depth, zc: BACK + depth / 2 }
})
export const BUILDING_TOP = FLOORS[FLOORS.length - 1].y + FLOORS[FLOORS.length - 1].h

/** The 18 homes: four a floor from the first to the fourth, two penthouses on the fifth. */
export const UNITS = FLOORS.slice(1).flatMap((f) => {
  const count = f.index === 5 ? 2 : 4
  return Array.from({ length: count }, (_, i) => ({ floor: f.index, i, count, w: FRONTAGE / count, x: -FRONTAGE / 2 + (FRONTAGE / count) * (i + 0.5), f }))
})

export interface Site {
  root: Group
  pieces: Map<string, Piece>
  /** 0 the façade as built, 1 see-through: the floors and installations show. */
  setCutaway(t: number): void
  /** 0 day, 1 the homes lit. */
  setLit(t: number): void
}

const RAW = new Color('#B9B4AC')
const FINISHED = new Color(PALETTE.white)

export function buildSite(): Site {
  const root = new Group()
  root.name = 'site'
  const pieces = new Map<string, Piece>()
  const add = (piece: Piece) => {
    piece.set(0)
    root.add(piece.root)
    pieces.set(piece.key, piece)
  }

  const plotW = PLOT.x1 - PLOT.x0
  const plotD = PLOT.z1 - PLOT.z0

  // ── The bare plot ────────────────────────────────────────────────────
  // The soil that fills the plot down to the board's hollow: when the works
  // start, it sinks away and leaves the excavation.
  const ground = group(box(plotW, DEPTH + 0.1, plotD, 0, 0, 0, mat('soil', { rough: 1 }), { cast: false }))
  ground.position.y = -DEPTH
  // Tufts and stones: an unbuilt solar.
  for (let i = 0; i < 26; i++) {
    const x = PLOT.x0 + 1 + ((i * 7.3) % (plotW - 2))
    const z = PLOT.z0 + 1 + ((i * 4.7) % (plotD - 2))
    ground.add(i % 4 === 0 ? box(0.6, 0.3, 0.5, x, DEPTH, z, mat('concreteDark'), { cast: false }) : mesh(new SphereGeometry(0.45, 6, 4), mat('grassDark'), x, DEPTH + 0.15, z, { cast: false }))
  }
  add({ key: 'plot.ground', root: ground, duration: 1.6, set: (t) => { ground.visible = t > 0.001; ground.scale.y = Math.max(0.0001, easeInOutCubic(t)) } })

  // «SE VENDE» on a post at the front.
  const forSale = new Group()
  const forSaleSign = new Group()
  forSaleSign.add(cylinder(0.14, 3.2, -1.3, 0, 0, mat('wood')))
  forSaleSign.add(cylinder(0.14, 3.2, 1.3, 0, 0, mat('wood')))
  const board = mesh(new PlaneGeometry(3.2, 1.6), new MeshStandardMaterial({ map: signTexture(['SE VENDE', 'SOLAR'], { bg: PALETTE.gold, fg: PALETTE.navy, width: 512, height: 256 }), roughness: 0.6 }), 0, 2.4, 0.08, { cast: false })
  forSaleSign.add(board)
  forSaleSign.add(box(3.3, 1.7, 0.1, 0, 1.55, 0, mat('navy')))
  forSaleSign.position.set(4, 0, PLOT.z1 - 0.6)
  forSale.add(forSaleSign)
  add(popPiece('plot.forsale', forSale, { duration: 0.9 }))

  // ── What can be built: the envelope, see-through ───────────────────
  const envelope = new Group()
  const envelopeMat = mat('brand', { opacity: 0.16, rough: 0.4 })
  const edgeMat = new LineBasicMaterial({ color: PALETTE.brand, transparent: true, opacity: 0.85 })
  for (const f of FLOORS) {
    const b = box(FRONTAGE, f.h - 0.04, f.depth, 0, f.y + 0.3, f.zc, envelopeMat, { cast: false, receive: false })
    const edges = new LineSegments(new EdgesGeometry(new BoxGeometry(FRONTAGE, f.h - 0.04, f.depth).translate(0, (f.h - 0.04) / 2, 0)), edgeMat)
    edges.position.copy(b.position)
    envelope.add(group(b, edges))
  }
  add(risePiece('plot.envelope', envelope, { duration: 1.8, stagger: 0.6 }))

  // ── Due diligence on the ground: survey poles, a level, the services ──
  const survey = new Group()
  for (const [x, z] of [[PLOT.x0 + 0.4, PLOT.z0 + 0.4], [PLOT.x1 - 0.4, PLOT.z0 + 0.4], [PLOT.x0 + 0.4, PLOT.z1 - 0.4], [PLOT.x1 - 0.4, PLOT.z1 - 0.4]]) {
    const pole = new Group()
    for (let k = 0; k < 4; k++) pole.add(cylinder(0.12, 0.5, 0, k * 0.5, 0, mat(k % 2 ? 'white' : 'red'), { cast: true }))
    pole.position.set(x, 0, z)
    survey.add(pole)
  }
  const tripod = new Group()
  for (let k = 0; k < 3; k++) {
    const leg = cylinder(0.06, 1.6, 0, 0, 0, mat('crane'))
    leg.rotation.z = 0.25
    leg.rotation.y = (k * Math.PI * 2) / 3
    tripod.add(leg)
  }
  tripod.add(box(0.4, 0.35, 0.3, 0, 1.55, 0, mat('navy')))
  tripod.position.set(-3, 0, 3)
  survey.add(tripod)
  add(popPiece('plot.survey', survey, { duration: 1 }))

  const services = new Group()
  services.add(cylinder(1, 0.06, -6, 0, PLOT.z1 + 1.6, mat('steel', { metal: 0.6, rough: 0.4 }), { cast: false }))
  services.add(cylinder(1, 0.06, 6, 0, PLOT.z1 + 1.6, mat('steel', { metal: 0.6, rough: 0.4 }), { cast: false }))
  services.add(box(0.9, 1.4, 0.5, PLOT.x1 - 1.2, 0, PLOT.z1 + 0.6, mat('#D9DEE6')))
  for (const [x, c] of [[-6, PALETTE.water], [6, PALETTE.gold], [PLOT.x1 - 1.2, PALETTE.brand]] as const) services.add(mesh(new SphereGeometry(0.35, 12, 8), mat(c, { emissive: c }), x, 2.4, PLOT.z1 + 1.4, { cast: false }))
  add(popPiece('plot.services', services, { duration: 0.9 }))

  // The air above: AESA's ceiling over the plot, a red sheet.
  const ceiling = new Group()
  ceiling.add(box(plotW + 8, 0.06, plotD + 8, 0, 34, 0, mat('red', { opacity: 0.22 }), { cast: false, receive: false }))
  add(popPiece('plot.easement', ceiling, { duration: 0.9, lift: 6 }))

  // ── The hoarding: CONSTRUYE's colours round the plot ────────────────
  const fence = new Group()
  const hoardMat = new MeshStandardMaterial({ map: hoardingTexture(), roughness: 0.7 })
  const panel = (len: number, x: number, z: number, rot: number) => {
    const tex = hoardMat.map!.clone()
    tex.needsUpdate = true
    tex.repeat.set(len / 11, 1)
    const m = new MeshStandardMaterial({ map: tex, roughness: 0.7 })
    const p = new Mesh(new BoxGeometry(len, 2.2, 0.12).translate(0, 1.1, 0), [mat('navy'), mat('navy'), mat('navy'), mat('navy'), m, m])
    p.position.set(x, 0, z)
    p.rotation.y = rot
    p.castShadow = true
    return p
  }
  fence.add(panel(plotW - 0.2, 0, PLOT.z1 - 0.1, 0))
  fence.add(panel(plotD - 0.4, PLOT.x0 + 0.12, 0, Math.PI / 2))
  fence.add(panel(plotD - 0.4, PLOT.x1 - 0.12, 0, Math.PI / 2))
  add(popPiece('plot.fence', fence, { duration: 1.2, stagger: 0.4, lift: 2.4 }))

  // ── The product, sketched: the massing in a pale ghost ──────────────
  const ghost = new Group()
  const ghostMat = mat('#FFFFFF', { opacity: 0.55, rough: 0.6 })
  for (const f of FLOORS) ghost.add(box(FRONTAGE - 0.4, f.h - 0.2, f.depth - 0.2, 0, f.y + 0.4, f.zc, ghostMat, { cast: false }))
  add(risePiece('site.ghost', ghost, { duration: 1.6, stagger: 0.55 }))

  // ── The project: the building drawn in lines ────────────────────────
  const lines: number[] = []
  const seg = (ax: number, ay: number, az: number, bx: number, by: number, bz: number) => lines.push(ax, ay, az, bx, by, bz)
  for (const f of FLOORS) {
    const y0 = f.y + 0.3
    const y1 = f.y + 0.3 + f.h
    for (const y of [y0, y1]) {
      seg(-FRONTAGE / 2, y, f.z0, FRONTAGE / 2, y, f.z0)
      seg(-FRONTAGE / 2, y, f.z1, FRONTAGE / 2, y, f.z1)
      seg(-FRONTAGE / 2, y, f.z0, -FRONTAGE / 2, y, f.z1)
      seg(FRONTAGE / 2, y, f.z0, FRONTAGE / 2, y, f.z1)
    }
    for (let x = -FRONTAGE / 2; x <= FRONTAGE / 2 + 0.01; x += FRONTAGE / 6) {
      seg(x, y0, f.z1, x, y1, f.z1)
      seg(x, y0, f.z0, x, y1, f.z0)
    }
    seg(-FRONTAGE / 2, y0, f.z0, -FRONTAGE / 2, y1, f.z0)
    seg(-FRONTAGE / 2, y0, f.z1, -FRONTAGE / 2, y1, f.z1)
  }
  const blueprintGeometry = new BufferGeometry()
  blueprintGeometry.setAttribute('position', new Float32BufferAttribute(lines, 3))
  const blueprint = new LineSegments(blueprintGeometry, new LineBasicMaterial({ color: PALETTE.brand, transparent: true, opacity: 0.95 }))
  const blueprintRoot = group(blueprint)
  const segments = lines.length / 6
  add({
    key: 'site.blueprint',
    root: blueprintRoot,
    duration: 2.6,
    set(t) {
      blueprintRoot.visible = t > 0.001
      blueprintGeometry.setDrawRange(0, Math.floor(segments * clamp01(t)) * 2)
    },
  })

  // Geotechnical drilling rig.
  const rig = new Group()
  rig.add(box(2.4, 1, 1.6, 0, 0, 0, mat('crane', { rough: 0.5 })))
  rig.add(box(0.4, 7, 0.4, 0.9, 1, 0, mat('steel', { metal: 0.5, rough: 0.4 })))
  rig.add(cylinder(0.25, 3, 0.9, -2, 0, mat('ink')))
  rig.position.set(3, 0, -2)
  add(popPiece('site.geotech', group(rig), { duration: 1 }))

  // ── Opening the works: the licence board, the huts, the setting-out ──
  const notice = new Group()
  const noticeBoard = new Group()
  noticeBoard.add(box(0.15, 3.6, 0.15, -1.6, 0, 0, mat('steel')))
  noticeBoard.add(box(0.15, 3.6, 0.15, 1.6, 0, 0, mat('steel')))
  noticeBoard.add(mesh(new PlaneGeometry(3.6, 2.2), new MeshStandardMaterial({ map: signTexture(['LICENCIA', 'DE OBRAS'], { bg: '#FFFFFF', fg: PALETTE.navy, width: 512, height: 320 }), roughness: 0.6 }), 0, 2.4, 0.1, { cast: false }))
  noticeBoard.add(box(3.7, 2.3, 0.08, 0, 1.25, 0, mat('navy')))
  noticeBoard.position.set(-5, 2.2, PLOT.z1 + 0.05)
  notice.add(noticeBoard)
  add(popPiece('site.notice', notice, { duration: 0.9 }))

  const huts = new Group()
  huts.add(box(5.6, 2.6, 2.4, PLOT.x1 - 3.4, 0, PLOT.z1 - 2, mat('white')))
  huts.add(box(5.62, 0.9, 2.42, PLOT.x1 - 3.4, 1.2, PLOT.z1 - 2, glass(), { cast: false }))
  huts.add(box(1.2, 2.3, 1.2, PLOT.x1 - 7, 0, PLOT.z1 - 2.2, mat('brand', { rough: 0.5 })))
  huts.add(box(2.8, 1.4, 1.6, PLOT.x0 + 2, 0, PLOT.z1 - 1.6, mat('#2E7D5B', { rough: 0.6 })))
  huts.add(box(2.8, 1.4, 1.6, PLOT.x0 + 5.2, 0, PLOT.z1 - 1.6, mat('steel', { rough: 0.6 })))
  add(popPiece('site.huts', huts, { duration: 1.1 }))

  const setout = new Group()
  for (const [x, z] of [[-FRONTAGE / 2, BACK], [FRONTAGE / 2, BACK], [-FRONTAGE / 2, BACK + 17], [FRONTAGE / 2, BACK + 17]]) setout.add(box(0.15, 0.9, 0.15, x, 0, z, mat('wood'), { cast: false }))
  const outline: number[] = [-FRONTAGE / 2, 0.5, BACK, FRONTAGE / 2, 0.5, BACK, FRONTAGE / 2, 0.5, BACK, FRONTAGE / 2, 0.5, BACK + 17, FRONTAGE / 2, 0.5, BACK + 17, -FRONTAGE / 2, 0.5, BACK + 17, -FRONTAGE / 2, 0.5, BACK + 17, -FRONTAGE / 2, 0.5, BACK]
  const outlineGeometry = new BufferGeometry()
  outlineGeometry.setAttribute('position', new Float32BufferAttribute(outline, 3))
  setout.add(new LineSegments(outlineGeometry, new LineBasicMaterial({ color: '#FFFFFF' })))
  add(popPiece('site.setout', setout, { duration: 0.9 }))

  // ── The tower crane ──────────────────────────────────────────────────
  const crane = new Group()
  const mast = new Group()
  const yellow = mat('crane', { rough: 0.45, metal: 0.2 })
  for (let k = 0; k < 10; k++) {
    const segment = new Group()
    for (const [x, z] of [[-0.7, -0.7], [0.7, -0.7], [-0.7, 0.7], [0.7, 0.7]]) segment.add(box(0.16, 3, 0.16, x, 0, z, yellow))
    segment.add(box(1.5, 0.12, 0.12, 0, 1.5, -0.7, yellow, { cast: false }))
    segment.add(box(0.12, 0.12, 1.5, 0.7, 2.6, 0, yellow, { cast: false }))
    segment.position.y = k * 3
    mast.add(segment)
  }
  crane.add(mast)
  const head = new Group()
  head.add(box(36, 0.7, 1, 9, 0, 0, yellow))
  head.add(box(2.2, 2, 2, 0, -2, 0, mat('white')))
  head.add(box(3, 2, 1.8, -9, -1.8, 0, mat('concrete')))
  head.add(box(0.5, 4, 0.5, 0, 0.7, 0, yellow))
  const trolley = group(box(1, 0.4, 1.2, 0, -0.4, 0, mat('steel')), cylinder(0.05, 12, 0, -12.4, 0, mat('ink'), { cast: false }), box(0.8, 0.8, 0.8, 0, -13.2, 0, mat('red')))
  trolley.position.x = 14
  head.add(trolley)
  head.position.y = 30.6
  crane.add(head)
  crane.position.set(-6.5, 0.3, -5.5)
  const craneRoot = group(crane)
  let craneAngle = -0.6
  add({
    key: 'site.crane',
    root: craneRoot,
    duration: 2.2,
    set(t) {
      craneRoot.visible = t > 0.001
      mast.children.forEach((s, k) => {
        const local = slice(t, (k / 10) * 0.75, (k / 10) * 0.75 + 0.25)
        s.visible = local > 0.001
        s.scale.y = Math.max(0.0001, easeOutCubic(local))
      })
      const h = slice(t, 0.78, 1)
      head.visible = h > 0.001
      head.scale.setScalar(Math.max(0.0001, easeOutBack(h)))
    },
    tick(dt) {
      craneAngle += dt * 0.12
      head.rotation.y = Math.sin(craneAngle) * 0.9 - 0.4
      trolley.position.x = 12 + Math.sin(craneAngle * 1.7) * 6
    },
  })

  // ── Earthworks ───────────────────────────────────────────────────────
  const pit = new Group()
  const PIT = DEPTH - 0.2
  const pitMat = mat('soilDark', { rough: 1 })
  pit.add(box(plotW - 0.4, 0.1, plotD - 0.4, 0, -PIT, 0, pitMat, { cast: false }))
  pit.add(box(plotW - 0.4, PIT, 0.1, 0, -PIT, PLOT.z0 + 0.25, pitMat, { cast: false }))
  pit.add(box(plotW - 0.4, PIT, 0.1, 0, -PIT, PLOT.z1 - 0.25, pitMat, { cast: false }))
  pit.add(box(0.1, PIT, plotD - 0.4, PLOT.x0 + 0.25, -PIT, 0, pitMat, { cast: false }))
  pit.add(box(0.1, PIT, plotD - 0.4, PLOT.x1 - 0.25, -PIT, 0, pitMat, { cast: false }))
  // Hide the board under the pit: a dark lid that opens downwards.
  const excavator = new Group()
  excavator.add(box(3, 1, 2, 0, 0, 0, mat('crane', { rough: 0.5 })))
  excavator.add(box(1.4, 1.4, 1.4, -0.6, 1, 0, mat('crane', { rough: 0.5 })))
  const arm = box(0.4, 0.4, 4, 0, 1.6, -2, mat('steel'))
  arm.rotation.x = -0.5
  excavator.add(arm)
  excavator.position.set(2, -PIT + 0.1, 2)
  const spoil = mesh(new SphereGeometry(2.6, 8, 6, 0, Math.PI * 2, 0, Math.PI / 2), mat('soil', { rough: 1 }), 12, 0, -16, { cast: true })
  const earthworks = group(pit, excavator, spoil)
  add({
    key: 'site.excavation',
    root: earthworks,
    duration: 2,
    set(t) {
      earthworks.visible = t > 0.001
      const k = easeInOutCubic(t)
      pit.scale.y = Math.max(0.0001, k)
      pit.position.y = 0
      excavator.visible = t > 0.2 && t < 0.999
      spoil.scale.setScalar(Math.max(0.0001, k))
    },
  })

  // ── Foundations: the slab poured in the pit ──────────────────────────
  const foundation = group(box(FRONTAGE + 0.6, PIT + 0.3, 17.4, 0, -PIT, BACK + 8.6, mat('concreteDark', { rough: 0.95 })))
  add(risePiece('site.foundation', foundation, { duration: 1.6 }))

  // ── The structure, floor after floor ─────────────────────────────────
  const concrete = mat('concrete', { rough: 0.9 })
  for (const f of FLOORS) {
    const floor = new Group()
    const columns = new Group()
    for (let x = -FRONTAGE / 2 + 0.3; x <= FRONTAGE / 2 - 0.29; x += (FRONTAGE - 0.6) / 4) {
      for (let z = f.z0 + 0.3; z <= f.z1 - 0.29; z += Math.max(2.5, (f.depth - 0.6) / 3)) columns.add(box(0.4, f.h - 0.3, 0.4, x, f.y + 0.3, z, concrete))
    }
    const slab = box(FRONTAGE, 0.3, f.depth, 0, f.y + f.h, f.zc, concrete)
    floor.add(columns)
    floor.add(slab)
    const columnsList = [...columns.children]
    const columnScales = columnsList.map((c) => c.scale.y)
    add({
      key: `site.structure.${f.index}`,
      root: floor,
      duration: 1.5,
      set(t) {
        floor.visible = t > 0.001
        columnsList.forEach((c, i) => {
          const local = easeOutCubic(slice(t, (i / columnsList.length) * 0.4, (i / columnsList.length) * 0.4 + 0.35))
          c.scale.y = Math.max(0.0001, columnScales[i] * local)
          c.visible = local > 0.001
        })
        const s = easeOutBack(slice(t, 0.7, 1))
        slab.visible = s > 0.001
        slab.scale.x = Math.max(0.0001, FRONTAGE * s)
      },
    })
  }

  // ── The envelope of the building: façades, windows, balconies ─────────
  const facadeMat = new MeshStandardMaterial({ color: RAW.clone(), roughness: 0.85, transparent: true, opacity: 1 })
  const glazing = new MeshStandardMaterial({ color: new Color('#5F8FC0'), roughness: 0.08, metalness: 0.35, transparent: true, opacity: 1, emissive: new Color(PALETTE.lit), emissiveIntensity: 0 })
  const rail = mat('#DDE6F0', { opacity: 0.6, rough: 0.2, metal: 0.1 })
  const facadeMeshes: Mesh[] = []
  for (const f of FLOORS) {
    const skin = new Group()
    const bays = 6
    const bw = FRONTAGE / bays
    const y0 = f.y + 0.3
    const h = f.h - 0.3
    for (let b = 0; b < bays; b++) {
      const x = -FRONTAGE / 2 + bw * (b + 0.5)
      const glazed = f.index === 0 ? b !== 0 : (b + f.index) % 3 !== 0
      const front = glazed ? box(bw - 0.5, h - 0.6, 0.12, x, y0 + 0.3, f.z1 - 0.06, glazing, { cast: false }) : box(bw, h, 0.25, x, y0, f.z1 - 0.12, facadeMat)
      if (glazed) {
        const pier = box(0.5, h, 0.25, x - bw / 2 + 0.25, y0, f.z1 - 0.12, facadeMat)
        facadeMeshes.push(pier)
        skin.add(pier)
      } else facadeMeshes.push(front)
      skin.add(front)
      const backPanel = box(bw, h, 0.25, x, y0, f.z0 + 0.12, b % 2 ? facadeMat : glazing, { cast: b % 2 === 1 })
      skin.add(backPanel)
      if (b % 2) facadeMeshes.push(backPanel)
    }
    // Party walls (medianeras) left and right: blind.
    for (const side of [-1, 1]) {
      const wall = box(0.25, h, f.depth, side * (FRONTAGE / 2 - 0.12), y0, f.zc, facadeMat)
      facadeMeshes.push(wall)
      skin.add(wall)
    }
    // Balconies on the first floors; the stepped floors' terraces get a rail.
    if (f.index >= 1 && f.index <= 2) {
      skin.add(box(FRONTAGE - 2, 0.2, 1.2, 0, y0 - 0.15, f.z1 + 0.6, mat('concrete')))
      skin.add(box(FRONTAGE - 2, 1, 0.06, 0, y0 + 0.05, f.z1 + 1.18, rail, { cast: false }))
    }
    const next = FLOORS[f.index + 1]
    if (next && next.depth < f.depth - 0.5) skin.add(box(FRONTAGE - 0.4, 1, 0.06, 0, f.y + f.h + 0.3, f.z1 - 0.05, rail, { cast: false }))
    add(popPiece(`site.facade.${f.index}`, skin, { duration: 1.3, stagger: 0.7, lift: 0.6 }))
  }
  // The roof: parapet, solar panels, the lift's housing.
  const roof = new Group()
  const top = FLOORS[FLOORS.length - 1]
  roof.add(box(FRONTAGE, 0.9, 0.2, 0, top.y + top.h + 0.3, top.z1 - 0.1, facadeMat))
  roof.add(box(FRONTAGE, 0.9, 0.2, 0, top.y + top.h + 0.3, top.z0 + 0.1, facadeMat))
  for (let i = 0; i < 6; i++) {
    const panelMesh = box(2, 0.1, 1.2, -6 + i * 2.4, top.y + top.h + 0.6, top.zc, mat('navy3', { rough: 0.25, metal: 0.4 }), { cast: false })
    panelMesh.rotation.x = -0.3
    roof.add(panelMesh)
  }
  roof.add(box(2.6, 2.4, 2.6, 6.5, top.y + top.h + 0.3, top.z0 + 2, facadeMat))
  add(popPiece('site.roof', roof, { duration: 1.1 }))

  // ── Installations: risers and runs, seen through the façade ──────────
  const mep = new Group()
  const pipe = (color: string, x: number, z: number) => {
    const riser = cylinder(0.22, BUILDING_TOP - 0.4, x, 0.3, z, mat(color, { rough: 0.4, emissive: color }), { cast: false })
    riser.material = mat(color, { rough: 0.4 })
    return riser
  }
  mep.add(pipe('#2F80ED', -0.6, -3))
  mep.add(pipe('#E8503A', -0.1, -3))
  mep.add(pipe(PALETTE.gold, 0.4, -3))
  mep.add(pipe('#7A8594', 0.9, -3))
  for (const f of FLOORS.slice(1)) {
    for (const [color, dz] of [['#2F80ED', -0.4], [PALETTE.gold, 0.4]] as const) {
      const run = box(FRONTAGE - 3, 0.16, 0.16, 0, f.y + f.h - 0.1, -3 + dz, mat(color, { rough: 0.4 }), { cast: false })
      mep.add(run)
    }
  }
  add(risePiece('site.mep', mep, { duration: 1.8, stagger: 0.5 }))

  // ── Finishes: the raw block rendered white, the street made new ───────
  const finishes = new Group()
  for (const f of FLOORS.slice(1, 5)) finishes.add(box(0.6, 0.5, 0.6, -FRONTAGE / 2 + 1, f.y + f.h + 0.3, f.z1 - 0.5, mat('grassDark'), { cast: false }))
  add({
    key: 'site.finishes',
    root: finishes,
    duration: 1.6,
    set(t) {
      finishes.visible = t > 0.001
      facadeMat.color.copy(RAW).lerp(FINISHED, easeInOutCubic(t))
      finishes.children.forEach((c, i) => c.scale.setScalar(Math.max(0.0001, easeOutBack(slice(t, i * 0.15, i * 0.15 + 0.4)))))
    },
  })

  const street = new Group()
  street.add(box(FRONTAGE + 1.4, 0.22, 2.2, 0, 0, PLOT.z1 + 1.2, mat('#E1E4EA', { rough: 0.9 }), { cast: false }))
  for (const x of [-6, 6]) {
    street.add(box(1.2, 0.3, 1.2, x, 0, PLOT.z1 + 1.3, mat('stone'), { cast: false }))
    street.add(mesh(new SphereGeometry(0.9, 8, 6), mat('grassDark'), x, 1.6, PLOT.z1 + 1.3))
    street.add(cylinder(0.2, 1.4, x, 0.3, PLOT.z1 + 1.3, mat('wood')))
  }
  street.add(box(2.4, 0.5, 0.6, 0, 0.2, PLOT.z1 + 1.6, mat('wood'), { cast: false }))
  // The building's portal and its name.
  const portal = mesh(new PlaneGeometry(4.2, 0.7), new MeshStandardMaterial({ map: signTexture(['EDIFICIO CONSTRUYE'], { bg: PALETTE.navy, fg: '#FFFFFF', width: 768, height: 128, mark: true }), roughness: 0.5 }), -FRONTAGE / 2 + 1.55 + 0.1, 3.1, PLOT.z1 + 0.05, { cast: false })
  street.add(portal)
  add(popPiece('site.street', street, { duration: 1.2 }))

  // ── The homes: the horizontal division, sold one by one ───────────────
  const unitColors = ['#9CC4F5', '#F5C99C', '#A6DCC0', '#E6B3D9']
  const unitsRoot = new Group()
  const soldRoot = new Group()
  for (const u of UNITS) {
    const block = box(u.w - 0.5, u.f.h - 0.7, u.f.depth - 0.9, u.x, u.f.y + 0.6, u.f.zc, mat(unitColors[(u.i + u.floor) % 4], { opacity: 0.85, rough: 0.6 }), { cast: false })
    unitsRoot.add(block)
    const dot = mesh(new SphereGeometry(0.42, 16, 12), mat('green', { emissive: '#1B7A4E', rough: 0.3 }), u.x, u.f.y + u.f.h - 0.4, u.f.z1 + 0.9, { cast: false })
    soldRoot.add(dot)
  }
  add(popPiece('site.units', unitsRoot, { duration: 1.6, stagger: 0.8, lift: 0.4 }))
  add(popPiece('site.sold', soldRoot, { duration: 2.4, stagger: 0.9, lift: 0.6 }))

  // ── Snags: marks on the homes before handing them over ────────────────
  const snags = new Group()
  for (const [k, u] of UNITS.entries()) {
    if (k % 3) continue
    snags.add(mesh(new SphereGeometry(0.3, 12, 8), mat('gold', { emissive: '#B8771B' }), u.x + 1, u.f.y + 1.6, u.f.z1 + 0.5, { cast: false }))
  }
  add(popPiece('site.snags', snags, { duration: 1.4, stagger: 0.8 }))

  // ── The keys: CONSTRUYE's closing image, a brass key over the building ──
  const key = new Group()
  const brass = mat('#C9A24A', { rough: 0.3, metal: 0.85 })
  key.add(mesh(new TorusGeometry(1.4, 0.38, 16, 40), brass, 0, 0, 0))
  key.add(mesh(new CylinderGeometry(0.28, 0.28, 5, 16).rotateZ(Math.PI / 2), brass, 3.9, 0, 0))
  key.add(box(0.5, 1.2, 0.56, 5.6, -1.2, 0, brass))
  key.add(box(0.5, 0.8, 0.56, 4.6, -1, 0, brass))
  key.position.set(0, BUILDING_TOP + 9, 2)
  const keyRoot = group(key)
  let spin = 0
  add({
    key: 'site.keys',
    root: keyRoot,
    duration: 1.4,
    set(t) {
      keyRoot.visible = t > 0.001
      key.scale.setScalar(Math.max(0.0001, easeOutBack(t)))
    },
    tick(dt) {
      spin += dt
      key.rotation.y = spin * 0.8
      key.position.y = BUILDING_TOP + 9 + Math.sin(spin * 1.6) * 0.6
    },
  })

  return {
    root,
    pieces,
    setCutaway(t) {
      const opacity = lerp(1, 0.18, clamp01(t))
      facadeMat.opacity = opacity
      facadeMat.depthWrite = opacity > 0.99
      glazing.opacity = lerp(1, 0.25, clamp01(t))
      for (const m of facadeMeshes) m.castShadow = opacity > 0.5
    },
    setLit(t) {
      glazing.emissiveIntensity = 0.9 * clamp01(t)
      glazing.color.set('#5F8FC0').lerp(new Color(PALETTE.lit), 0.6 * clamp01(t))
    },
  }
}

export function sitePieceKeys(site: Site): string[] {
  return [...site.pieces.keys()]
}

export type { Object3D }
