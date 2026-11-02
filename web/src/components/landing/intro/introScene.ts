import {
  AmbientLight,
  BackSide,
  BoxGeometry,
  type BufferGeometry,
  CylinderGeometry,
  DataTexture,
  DirectionalLight,
  Group,
  type Material,
  Mesh,
  MeshBasicMaterial,
  MeshToonMaterial,
  NearestFilter,
  type Object3D,
  PerspectiveCamera,
  PlaneGeometry,
  RedFormat,
  Scene,
  SRGBColorSpace,
  TorusGeometry,
  Vector3,
  WebGLRenderer,
} from 'three'

/**
 * The world of the intro (S0), drawn like an architect's cartoon: flat
 * colours in three steps of light (MeshToonMaterial), a thin black line round
 * every object, night blue, amber and cream only. A bare plot, a tower crane,
 * the things of the trade floating above it, a town on the horizon.
 *
 * Everything is built here from boxes, cylinders and rings: no model is
 * downloaded. The town is a row of plain silhouettes and stands for no
 * particular building.
 *
 * The story is told by `setChapter`: -1 is the title, 0 to 3 the four
 * chapters. The camera travels to each one; the building rises in the third
 * and is finished, windows lit, in the fourth.
 */
export interface IntroScene {
  setChapter(chapter: number): void
  setPointer(x: number, y: number): void
  /** Finish the building and light it at once: the visitor is leaving. */
  finish(): void
  resize(): void
  start(): void
  dispose(): void
}

type Triple = [number, number, number]

const INK = '#05080F'
const CREAM = '#F1E7D2'
const PAPER = '#F6F2EA'
const AMBER = '#E8A33D'
const BLUE = '#2B4D7E'
const DEEP = '#12253F'
const STONE = '#CFC7B6'
/** Half the width of the black line round an object, in metres of the scene. */
const LINE = 0.05
const STOREYS = 6
const STOREY_HEIGHT = 1.05

export function createIntroScene(canvas: HTMLCanvasElement, { compact }: { compact: boolean }): IntroScene {
  const renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' })
  renderer.outputColorSpace = SRGBColorSpace
  renderer.setClearAlpha(0)

  const scene = new Scene()
  const camera = new PerspectiveCamera(compact ? 56 : 38, 1, 0.5, 140)
  const sun = new DirectionalLight('#ffffff', 2.4)
  sun.position.set(7, 11, 9)
  scene.add(sun, new AmbientLight('#9db3d6', 1.25))

  // Three flat steps of light instead of a smooth shade: the cartoon look.
  const steps = new DataTexture(new Uint8Array([84, 168, 255]), 3, 1, RedFormat)
  steps.minFilter = steps.magFilter = NearestFilter
  steps.needsUpdate = true

  const disposables: { dispose(): void }[] = [steps]
  const toon = (color: string) => {
    const made = new MeshToonMaterial({ color, gradientMap: steps })
    disposables.push(made)
    return made
  }
  const flat = (color: string) => {
    const made = new MeshBasicMaterial({ color })
    disposables.push(made)
    return made
  }
  const ink = new MeshBasicMaterial({ color: INK, side: BackSide })
  disposables.push(ink)
  const look = { cream: toon(CREAM), paper: toon(PAPER), amber: toon(AMBER), blue: toon(BLUE), deep: toon(DEEP), stone: toon(STONE) }

  const size = new Vector3()
  /**
   * One solid, with its outline: the same shape again, black, turned inside
   * out and a line's width larger, so only its rim shows round the solid.
   */
  const piece = (geometry: BufferGeometry, material: Material, at: Triple = [0, 0, 0], turn: Triple = [0, 0, 0], outlined = true) => {
    disposables.push(geometry)
    const mesh = new Mesh(geometry, material)
    mesh.position.set(...at)
    mesh.rotation.set(...turn)
    if (outlined) {
      geometry.computeBoundingBox()
      geometry.boundingBox?.getSize(size)
      const hull = new Mesh(geometry, ink)
      hull.scale.set(1 + (2 * LINE) / Math.max(size.x, 0.05), 1 + (2 * LINE) / Math.max(size.y, 0.05), 1 + (2 * LINE) / Math.max(size.z, 0.05))
      mesh.add(hull)
    }
    return mesh
  }
  const box = (w: number, h: number, d: number) => new BoxGeometry(w, h, d)

  // ── The ground, the bare plot and its four boundary stakes ───────────────
  const ground = piece(new PlaneGeometry(160, 160), flat('#0D2040'), [0, 0, 0], [-Math.PI / 2, 0, 0], false)
  const plot = piece(box(11, 0.12, 8), look.cream, [0.8, 0.06, 0])
  scene.add(ground, plot)
  for (const [x, z] of [
    [-4.7, -4],
    [6.3, -4],
    [-4.7, 4],
    [6.3, 4],
  ]) {
    scene.add(piece(box(0.14, 0.9, 0.14), look.amber, [x, 0.45, z]))
  }
  const cord = flat(AMBER)
  scene.add(piece(box(11, 0.03, 0.03), cord, [0.8, 0.82, 4], [0, 0, 0], false), piece(box(11, 0.03, 0.03), cord, [0.8, 0.82, -4], [0, 0, 0], false))
  scene.add(piece(box(0.03, 0.03, 8), cord, [-4.7, 0.82, 0], [0, 0, 0], false), piece(box(0.03, 0.03, 8), cord, [6.3, 0.82, 0], [0, 0, 0], false))

  // ── A town on the horizon: plain silhouettes, no building in particular ──
  const far = flat('#1B3764')
  const farther = flat('#152B50')
  const town: [number, number, number][] = [
    [-34, 7, 3.4],
    [-27, 11, 4],
    [-19, 6, 5],
    [-11, 9, 3.6],
    [-3, 5, 6],
    [6, 12, 4.2],
    [14, 7, 5],
    [22, 10, 3.8],
    [30, 6, 5.4],
    [37, 9, 4],
  ]
  town.forEach(([x, height, width], index) => {
    if (compact && index % 2 === 1) return
    scene.add(piece(box(width, height, 3), index % 3 === 0 ? farther : far, [x, height / 2, -40 - (index % 3) * 4], [0, 0, 0], false))
  })

  // ── The tower crane ──────────────────────────────────────────────────────
  const crane = new Group()
  crane.position.set(compact ? -4.4 : -8.6, 0, -2.6)
  const jib = new Group()
  jib.position.y = 10
  jib.add(
    piece(box(9.4, 0.26, 0.26), look.amber, [3.1, 0, 0]),
    piece(box(1.1, 0.75, 0.7), look.blue, [-1.5, -0.1, 0]),
    piece(box(0.75, 0.75, 0.75), look.paper, [0.55, -0.55, 0.1]),
    piece(new CylinderGeometry(0.025, 0.025, 3.4, 6), flat(INK), [6.2, -1.8, 0], [0, 0, 0], false),
    piece(new TorusGeometry(0.2, 0.06, 8, 18, Math.PI * 1.45), look.blue, [6.2, -3.65, 0], [0, 0, 0.5]),
  )
  crane.add(piece(box(0.5, 10.4, 0.5), look.amber, [0, 5.2, 0]), piece(box(1.5, 0.3, 1.5), look.blue, [0, 0.15, 0]), jib)
  scene.add(crane)

  // ── The building: six storeys that rise, then a roof ─────────────────────
  const building = new Group()
  building.position.set(0.8, 0.12, 0)
  const dark = flat('#0F2140')
  const lit = flat('#FFC56B')
  const pane = new PlaneGeometry(0.62, 0.5)
  disposables.push(pane)
  const windows: Mesh[] = []
  const storeys: Group[] = []
  for (let level = 0; level <= STOREYS; level++) {
    const storey = new Group()
    storey.position.y = level * STOREY_HEIGHT
    if (level < STOREYS) {
      storey.add(piece(box(5.3, 0.14, 4.3), look.blue, [0, 0.07, 0]), piece(box(5, 0.91, 4), look.cream, [0, 0.595, 0]))
      for (const x of [-1.8, -0.6, 0.6, 1.8]) {
        const front = new Mesh(pane, dark)
        front.position.set(x, 0.62, 2.012)
        windows.push(front)
        storey.add(front)
      }
      for (const z of [-1.2, 0, 1.2]) {
        const side = new Mesh(pane, dark)
        side.position.set(2.512, 0.62, z)
        side.rotation.y = Math.PI / 2
        const back = side.clone()
        back.position.x = -2.512
        back.rotation.y = -Math.PI / 2
        windows.push(side, back)
        storey.add(side, back)
      }
    } else {
      storey.add(piece(box(5.5, 0.2, 4.5), look.blue, [0, 0.1, 0]), piece(box(1.6, 0.7, 1.4), look.paper, [-1, 0.55, -0.6]))
    }
    storey.visible = false
    storeys.push(storey)
    building.add(storey)
  }
  scene.add(building)
  // The order the windows come on: scattered, the same on every visit.
  const order = windows.map((_, index) => index).sort((a, b) => ((a * 7919) % 31) - ((b * 7919) % 31))

  // ── What floats above the site ───────────────────────────────────────────
  const beam = new Group()
  beam.add(piece(box(2.6, 0.1, 0.55), look.blue, [0, 0.3, 0]), piece(box(2.6, 0.1, 0.55), look.blue, [0, -0.3, 0]), piece(box(2.6, 0.5, 0.1), look.blue))
  const concrete = piece(box(1.1, 0.8, 0.8), look.stone)
  const breeze = new Group()
  breeze.add(piece(box(1.3, 0.6, 0.6), look.stone), piece(box(0.4, 0.02, 0.36), flat(INK), [-0.32, 0.31, 0], [0, 0, 0], false), piece(box(0.4, 0.02, 0.36), flat(INK), [0.32, 0.31, 0], [0, 0, 0], false))
  const key = new Group()
  key.add(
    piece(new TorusGeometry(0.42, 0.13, 12, 28), look.amber, [0, 0.95, 0]),
    piece(new CylinderGeometry(0.09, 0.09, 1.6, 12), look.amber, [0, -0.2, 0]),
    piece(box(0.36, 0.16, 0.12), look.amber, [0.2, -0.7, 0]),
    piece(box(0.26, 0.16, 0.12), look.amber, [0.16, -0.96, 0]),
  )
  const roll = new Group()
  roll.add(piece(new CylinderGeometry(0.22, 0.22, 2.3, 16), look.paper, [0, 0, 0], [0, 0, Math.PI / 2]), piece(new CylinderGeometry(0.245, 0.245, 0.3, 16), look.blue, [0.3, 0, 0], [0, 0, Math.PI / 2]))
  const file = new Group()
  file.add(
    piece(box(1.5, 1.95, 0.08), look.amber),
    piece(box(1.36, 1.8, 0.04), look.paper, [0.06, 0.05, 0.08], [0, 0, -0.05]),
    piece(box(1.36, 1.8, 0.04), look.paper, [-0.03, 0.02, 0.14], [0, 0, 0.04]),
    piece(box(0.5, 0.18, 0.1), look.blue, [0, 0.92, 0.16]),
  )

  interface Floater {
    group: Object3D
    home: Triple
    spin: number
    phase: number
  }
  const floaters: Floater[] = compact
    ? [
        { group: key, home: [3.4, 12.5, 2], spin: 0.5, phase: 0 },
        { group: beam, home: [-3, 13.6, 0], spin: 0.22, phase: 1.3 },
        { group: file, home: [-4.6, 8.6, 1], spin: 0.3, phase: 2.6 },
        { group: breeze, home: [4.6, 5.4, 2], spin: -0.3, phase: 4 },
      ]
    : [
        { group: key, home: [6.4, 9.6, 2], spin: 0.5, phase: 0 },
        { group: beam, home: [-3.6, 10.2, 0], spin: 0.22, phase: 1.3 },
        { group: file, home: [12.6, 6.6, 1], spin: 0.3, phase: 2.6 },
        { group: roll, home: [11.6, 3.9, 2.2], spin: -0.4, phase: 3.4 },
        { group: breeze, home: [-13.2, 3.4, 1], spin: -0.3, phase: 4 },
        { group: concrete, home: [-12.6, 8.4, -2], spin: 0.26, phase: 5.2 },
      ]
  key.rotation.set(0.2, 0.4, -0.5)
  beam.rotation.set(0.3, 0.5, 0.25)
  roll.rotation.set(0.2, 0.3, 0.6)
  file.rotation.set(-0.15, -0.35, 0.12)
  breeze.rotation.set(0.35, 0.6, 0.2)
  concrete.rotation.set(0.4, 0.3, 0.2)
  floaters.forEach((floater) => scene.add(floater.group))
  // Each one sways about the angle it was set at: it never shows its edge.
  const facing = floaters.map((floater) => floater.group.rotation.y)
  const fileHome = floaters[2].home

  // ── Where the camera stands for the title and for each chapter ───────────
  // On a phone the frame is tall: the same views from further back.
  const reach = compact ? 1.45 : 1
  const view = (position: Triple, target: Triple) => {
    const eye = new Vector3(...position)
    const aim = new Vector3(...target)
    return { eye: aim.clone().add(eye.sub(aim).multiplyScalar(reach)), aim }
  }
  const title = compact ? view([0, 5.6, 15], [0, 6.6, 0]) : view([0, 5.2, 22], [0, 4.4, 0])
  const views = [
    view([7.4, 2.5, 12.5], [0.8, 0.9, 0]),
    // The file stands right of the middle: the words are read on the left.
    view([fileHome[0] - 3.6, fileHome[1] - 0.6, 8.6], [fileHome[0] - 2, fileHome[1] - 1, 0]),
    view([compact ? -8 : -11, 4.4, 13.5], [-1.4, 4.2, 0]),
    view([5.8, 4.8, 14], [0.8, 3.7, 0]),
  ]
  /** How many storeys stand in each chapter (the roof is the seventh). */
  const heights = [0, 0, 3.6, STOREYS + 1]

  const eye = title.eye.clone()
  const aim = title.aim.clone()
  camera.position.copy(eye)
  camera.lookAt(aim)

  let chapter = -1
  let built = 0
  let lights = 0
  let pointerX = 0
  let pointerY = 0
  let easedX = 0
  let easedY = 0
  let last = 0
  let disposed = false
  const keyAt = new Vector3(...floaters[0].home)
  const keyGoal = new Vector3()
  const offset = new Vector3()

  function frame(time: number) {
    if (disposed) return
    const seconds = time / 1000
    const delta = last === 0 ? 0.016 : Math.min(0.05, seconds - last)
    last = seconds

    // The camera travels: it closes a fixed share of the distance each instant.
    const goal = chapter < 0 ? title : views[chapter]
    const travel = 1 - Math.exp(-delta * 1.7)
    eye.lerp(goal.eye, travel)
    aim.lerp(goal.aim, travel)
    const follow = 1 - Math.exp(-delta * 3)
    easedX += (pointerX - easedX) * follow
    easedY += (pointerY - easedY) * follow
    offset.set(easedX * 0.7 + Math.sin(seconds * 0.23) * 0.25, -easedY * 0.4 + Math.sin(seconds * 0.31) * 0.12, 0)
    camera.position.copy(eye).add(offset)
    camera.lookAt(aim)

    // The building rises a storey at a time, each one growing from its floor.
    const target = chapter < 0 ? 0 : heights[chapter]
    const pace = target > built ? 1.25 : 4
    built += Math.sign(target - built) * Math.min(Math.abs(target - built), delta * pace)
    storeys.forEach((storey, level) => {
      const grown = Math.min(1, Math.max(0, built - level))
      storey.visible = grown > 0.001
      storey.scale.y = 1 - (1 - grown) ** 3
    })
    // Once the roof is on, the windows come on one after the other.
    const wanted = built >= STOREYS + 0.9 ? windows.length : 0
    if (wanted > lights) lights = Math.min(wanted, lights + delta * 22)
    else if (wanted < lights) lights = 0
    order.forEach((index, rank) => {
      windows[index].material = rank < lights ? lit : dark
    })

    jib.rotation.y = Math.sin(seconds * 0.25) * 0.3 - 0.2
    for (const [index, floater] of floaters.entries()) {
      const [x, y, z] = floater.home
      floater.group.rotation.y = facing[index] + Math.sin(seconds * floater.spin * 1.6 + floater.phase) * 0.45
      if (floater.group === key) {
        // In the last chapter the key comes down in front of the building.
        keyGoal.set(chapter === 3 ? 6 : x, chapter === 3 ? 5.3 : y, chapter === 3 ? 4.5 : z)
        keyAt.lerp(keyGoal, 1 - Math.exp(-delta * 1.4))
        floater.group.position.set(keyAt.x, keyAt.y + Math.sin(seconds * 0.7) * 0.2, keyAt.z)
      } else {
        floater.group.position.set(x, y + Math.sin(seconds * 0.6 + floater.phase) * 0.28, z)
      }
    }
    renderer.render(scene, camera)
  }

  function resize() {
    const width = canvas.clientWidth
    const height = canvas.clientHeight
    if (width === 0 || height === 0) return
    // A phone draws fewer pixels: the black lines stay crisp enough at 1.5.
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, compact ? 1.5 : 2))
    renderer.setSize(width, height, false)
    camera.aspect = width / height
    camera.updateProjectionMatrix()
  }
  resize()

  return {
    setChapter(next) {
      chapter = Math.max(-1, Math.min(views.length - 1, next))
    },
    setPointer(x, y) {
      pointerX = x
      pointerY = y
    },
    finish() {
      chapter = views.length - 1
      built = STOREYS + 1
      lights = windows.length
    },
    resize,
    start() {
      if (!disposed) renderer.setAnimationLoop(frame)
    },
    dispose() {
      if (disposed) return
      disposed = true
      renderer.setAnimationLoop(null)
      disposables.forEach((item) => item.dispose())
      renderer.dispose()
    },
  }
}
