import {
  Color,
  ConeGeometry,
  CylinderGeometry,
  DirectionalLight,
  DoubleSide,
  Fog,
  Group,
  HemisphereLight,
  Mesh,
  MeshBasicMaterial,
  MeshStandardMaterial,
  NeutralToneMapping,
  PCFSoftShadowMap,
  PerspectiveCamera,
  PlaneGeometry,
  PMREMGenerator,
  Raycaster,
  RingGeometry,
  Scene,
  SphereGeometry,
  SRGBColorSpace,
  Vector2,
  Vector3,
  WebGLRenderer,
  type Object3D,
} from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js'
import { CSS2DObject, CSS2DRenderer } from 'three/addons/renderers/CSS2DRenderer.js'
import { paperTexture } from './brand'
import { easeInOutCubic, easeOutBack, easeOutCubic, lerp, PALETTE, popPiece, type Piece } from './kit'
import { buildPlace, placeDef, PLACES, type PlaceKey } from './places'
import { buildSite } from './site'
import { buildTown } from './town'

/**
 * The 3D world: one board, one camera, the town, its institutions and the
 * plot. The page (World3D) drives it with what the course's step says:
 * which pieces stand, where the camera goes, which place is lit, which paper
 * comes out of which door. Rendering stops when nothing moves.
 */

export interface WorldMode {
  /** 0 the façade solid, 1 see-through (installations, homes). */
  cutaway: number
  /** 0 day, 1 dusk with the homes lit. */
  dusk: number
}

export interface WorldOptions {
  reduced: boolean
  maxPixelRatio: number
  /** A place's name in the student's language, for its label. */
  placeName(key: PlaceKey): string
  onPick(key: PlaceKey): void
  onFirstFrame(): void
  onFail(reason: 'context'): void
}

export interface WorldHandle {
  setPieces(keys: string[], options?: { instant?: boolean }): void
  focus(key: PlaceKey | null, options?: { instant?: boolean }): void
  setActive(keys: PlaceKey[]): void
  setMode(mode: Partial<WorldMode>, options?: { instant?: boolean }): void
  emit(from: PlaceKey, kind: 'paper' | 'tax' | 'stamp'): void
  zoom(factor: number): void
  overview(): void
  setLabels(visible: boolean): void
  /** The camera turns slowly round what it looks at (the tour of the promotion). */
  setAutoRotate(on: boolean): void
  /** How much of the canvas the panels cover, in CSS pixels. */
  setInsets(insets: { right: number; bottom: number; top?: number }): void
  resize(): void
  dispose(): void
}

interface Tween {
  from: number
  to: number
  t: number
  duration: number
  delay: number
  apply(value: number): void
  done?(): void
}

const DAY_SKY = new Color('#DCE6F2')
const DUSK_SKY = new Color('#1B2B48')
const DAY_FOG = new Color('#E4EBF4')
const DUSK_FOG = new Color('#22324F')

export function createWorld(canvas: HTMLCanvasElement, labelHost: HTMLElement, options: WorldOptions): WorldHandle {
  let renderer: WebGLRenderer
  try {
    renderer = new WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' })
  } catch {
    options.onFail('context')
    return nullWorld()
  }
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, options.maxPixelRatio))
  renderer.outputColorSpace = SRGBColorSpace
  renderer.toneMapping = NeutralToneMapping
  renderer.toneMappingExposure = 1.05
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = PCFSoftShadowMap

  const scene = new Scene()
  scene.background = DAY_SKY.clone()
  scene.fog = new Fog(DAY_FOG.clone(), 260, 520)
  const pmrem = new PMREMGenerator(renderer)
  const environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture
  scene.environment = environment
  scene.environmentIntensity = 0.45

  const hemi = new HemisphereLight('#F4F7FF', '#B7AE9C', 1.25)
  scene.add(hemi)
  const sun = new DirectionalLight('#FFF4E2', 2.4)
  sun.position.set(70, 120, 60)
  sun.castShadow = true
  const coarse = window.matchMedia('(pointer: coarse)').matches
  sun.shadow.mapSize.set(coarse ? 1024 : 2048, coarse ? 1024 : 2048)
  const sc = sun.shadow.camera
  sc.left = -95
  sc.right = 95
  sc.top = 95
  sc.bottom = -95
  sc.near = 20
  sc.far = 320
  sun.shadow.bias = -0.0004
  sun.shadow.normalBias = 0.6
  scene.add(sun)
  scene.add(sun.target)

  const camera = new PerspectiveCamera(30, 1, 1, 1200)
  const HOME_TARGET = new Vector3(0, 4, 6)
  const HOME_POS = new Vector3(96, 150, 128)
  camera.position.copy(HOME_POS)

  const controls = new OrbitControls(camera, renderer.domElement)
  controls.target.copy(HOME_TARGET)
  controls.enableDamping = !options.reduced
  controls.dampingFactor = 0.08
  controls.minDistance = 22
  controls.maxDistance = 300
  controls.minPolarAngle = 0.32
  controls.maxPolarAngle = 1.22
  controls.screenSpacePanning = false
  controls.zoomToCursor = true
  controls.update()

  // ── Labels, drawn by the browser over the canvas ──────────────────────
  const labels = new CSS2DRenderer()
  labels.domElement.className = 'w3d-labels'
  labelHost.appendChild(labels.domElement)

  // ── The world ──────────────────────────────────────────────────────────
  const town = buildTown()
  scene.add(town.root)
  const placeObjects = new Map<PlaceKey, Object3D>()
  const labelObjects = new Map<PlaceKey, { object: CSS2DObject; element: HTMLDivElement }>()
  const pieceTs = new Map<string, { piece: Piece; t: number; target: number }>()

  const site = buildSite()
  scene.add(site.root)
  for (const piece of site.pieces.values()) pieceTs.set(piece.key, { piece, t: 0, target: 0 })

  for (const def of PLACES) {
    const object = buildPlace(def)
    placeObjects.set(def.key, object)
    if (def.key === 'oficina_ventas') {
      // The sales office opens with the marketing, as a piece of its own.
      const holder = new Group()
      holder.add(object)
      const piece = popPiece('place.oficina_ventas', holder, { duration: 1.1 })
      piece.set(0)
      pieceTs.set(piece.key, { piece, t: 0, target: 0 })
      scene.add(holder)
    } else {
      scene.add(object)
    }
    if (def.key !== 'site') {
      const element = document.createElement('div')
      element.className = 'w3d-label'
      element.textContent = options.placeName(def.key)
      element.dataset.place = def.key
      element.addEventListener('click', () => options.onPick(def.key))
      const label = new CSS2DObject(element)
      label.position.set(def.x, def.height + 2.2, def.z)
      label.visible = def.key !== 'oficina_ventas'
      scene.add(label)
      labelObjects.set(def.key, { object: label, element })
    }
  }

  // ── The pin over the place of the step, and its ring on the ground ────
  const pinMat = new MeshStandardMaterial({ color: PALETTE.brand, roughness: 0.35, metalness: 0.1, emissive: new Color('#0A3C9A'), emissiveIntensity: 0.4 })
  const pin = new Group()
  pin.add(new Mesh(new SphereGeometry(1.5, 24, 16), pinMat))
  const tip = new Mesh(new ConeGeometry(1.1, 2.6, 24), pinMat)
  tip.rotation.x = Math.PI
  tip.position.y = -2
  pin.add(tip)
  const pinDot = new Mesh(new SphereGeometry(0.6, 16, 12), new MeshBasicMaterial({ color: '#FFFFFF' }))
  pinDot.position.set(0, 0.2, 1.2)
  pin.add(pinDot)
  pin.visible = false
  scene.add(pin)
  const ring = new Mesh(new RingGeometry(2.6, 3.4, 48), new MeshBasicMaterial({ color: PALETTE.brand, transparent: true, opacity: 0.5, side: DoubleSide, depthWrite: false }))
  ring.rotation.x = -Math.PI / 2
  ring.visible = false
  scene.add(ring)

  let pinBase = new Vector3()

  // ── Animation ──────────────────────────────────────────────────────────
  const tweens: Tween[] = []
  const tween = (from: number, to: number, duration: number, apply: (v: number) => void, delay = 0, done?: () => void) => {
    if (options.reduced || duration <= 0) {
      apply(to)
      done?.()
      return
    }
    tweens.push({ from, to, t: 0, duration, delay, apply, done })
    wake()
  }

  const mode: WorldMode = { cutaway: 0, dusk: 0 }
  const applyMode = () => {
    site.setCutaway(mode.cutaway)
    site.setLit(mode.dusk)
    ;(scene.background as Color).copy(DAY_SKY).lerp(DUSK_SKY, mode.dusk)
    ;(scene.fog as Fog).color.copy(DAY_FOG).lerp(DUSK_FOG, mode.dusk)
    sun.intensity = lerp(2.4, 0.35, mode.dusk)
    sun.color.set('#FFF4E2').lerp(new Color('#FFB27A'), mode.dusk)
    hemi.intensity = lerp(1.25, 0.45, mode.dusk)
    const windowMat = town.windows.material as MeshStandardMaterial
    windowMat.emissive.set(PALETTE.lit)
    windowMat.emissiveIntensity = 0.75 * mode.dusk
    const lampMat = town.lamps.material as MeshStandardMaterial
    lampMat.emissive.set('#FFE2A8')
    lampMat.emissiveIntensity = 2 * mode.dusk
  }
  applyMode()

  // Papers, taxes and stamps rising out of a door.
  const floaters: { mesh: Mesh; age: number; life: number; from: Vector3 }[] = []
  const paperTex = { paper: paperTexture(PALETTE.brand), tax: paperTexture(PALETTE.gold), stamp: paperTexture(PALETTE.green) }
  const coinGeometry = new CylinderGeometry(1, 1, 0.25, 32)
  const coinMat = new MeshStandardMaterial({ color: '#E2B54C', metalness: 0.85, roughness: 0.3 })

  let running = false
  let idleSince = performance.now()
  let last = performance.now()
  let firstFrame = false
  let visible = true

  const frame = () => {
    const now = performance.now()
    // Real time, up to a tenth of a second a frame: a slow device still finishes
    // an animation on time, and a tab coming back does not jump to the end.
    const dt = Math.min(0.1, (now - last) / 1000)
    last = now
    let busy = false

    for (let i = tweens.length - 1; i >= 0; i--) {
      const tw = tweens[i]
      if (tw.delay > 0) {
        tw.delay -= dt
        busy = true
        continue
      }
      tw.t = Math.min(1, tw.t + dt / tw.duration)
      tw.apply(lerp(tw.from, tw.to, tw.t))
      busy = true
      if (tw.t >= 1) {
        tweens.splice(i, 1)
        tw.done?.()
      }
    }

    for (const p of pieceTs.values()) {
      if (p.t > 0.999 && p.piece.tick && p.piece.root.visible) {
        p.piece.tick(dt)
        busy = true
      }
    }

    if (pin.visible) {
      const bob = Math.sin(now / 380) * 0.8
      pin.position.set(pinBase.x, pinBase.y + 2 + bob, pinBase.z)
      pin.rotation.y += dt * 1.2
      const k = (now / 1400) % 1
      ring.scale.setScalar(0.6 + k * 1.6)
      ;(ring.material as MeshBasicMaterial).opacity = 0.55 * (1 - k)
      busy = true
    }

    for (let i = floaters.length - 1; i >= 0; i--) {
      const f = floaters[i]
      f.age += dt
      const k = f.age / f.life
      f.mesh.position.set(f.from.x, f.from.y + easeOutCubic(k) * 9, f.from.z)
      f.mesh.quaternion.copy(camera.quaternion)
      f.mesh.scale.setScalar(Math.max(0.0001, easeOutBack(Math.min(1, k * 4)) * (1 - Math.max(0, (k - 0.75) / 0.25))))
      busy = true
      if (k >= 1) {
        scene.remove(f.mesh)
        floaters.splice(i, 1)
      }
    }

    const moved = controls.update(dt)
    if (moved || controls.autoRotate) busy = true

    renderer.render(scene, camera)
    labels.render(scene, camera)
    if (!firstFrame) {
      firstFrame = true
      options.onFirstFrame()
    }

    if (busy) idleSince = now
    else if (now - idleSince > 1200) stop()
  }

  function wake() {
    idleSince = performance.now()
    if (running || !visible) return
    running = true
    last = performance.now()
    renderer.setAnimationLoop(frame)
  }
  function stop() {
    if (!running) return
    running = false
    renderer.setAnimationLoop(null)
  }

  controls.addEventListener('start', wake)
  controls.addEventListener('change', wake)

  const onVisibility = () => {
    visible = document.visibilityState === 'visible'
    if (visible) wake()
    else stop()
  }
  document.addEventListener('visibilitychange', onVisibility)

  // ── Picking: a tap on a building opens what happens there ──────────────
  const raycaster = new Raycaster()
  const pointer = new Vector2()
  let down: { x: number; y: number } | null = null
  const onDown = (e: PointerEvent) => {
    down = { x: e.clientX, y: e.clientY }
  }
  const onUp = (e: PointerEvent) => {
    if (!down || Math.hypot(e.clientX - down.x, e.clientY - down.y) > 6) return
    const rect = canvas.getBoundingClientRect()
    pointer.set(((e.clientX - rect.left) / rect.width) * 2 - 1, -((e.clientY - rect.top) / rect.height) * 2 + 1)
    raycaster.setFromCamera(pointer, camera)
    const hits = raycaster.intersectObjects([...placeObjects.values(), site.root], true)
    for (const hit of hits) {
      let o: Object3D | null = hit.object
      while (o && !o.userData.place && o.name !== 'site') o = o.parent
      if (o) {
        options.onPick(o.name === 'site' ? 'site' : (o.userData.place as PlaceKey))
        return
      }
    }
  }
  canvas.addEventListener('pointerdown', onDown)
  canvas.addEventListener('pointerup', onUp)

  // ── The camera's flights ───────────────────────────────────────────────
  const fly = (position: Vector3, target: Vector3, instant: boolean) => {
    const p0 = camera.position.clone()
    const t0 = controls.target.clone()
    if (instant || options.reduced) {
      camera.position.copy(position)
      controls.target.copy(target)
      controls.update()
      wake()
      return
    }
    const distance = p0.distanceTo(position)
    tween(0, 1, Math.min(2.4, 1.1 + distance / 220), (v) => {
      const k = easeInOutCubic(v)
      // A little arc: rise over the town between two places.
      const lift = Math.sin(k * Math.PI) * Math.min(40, distance * 0.18)
      camera.position.lerpVectors(p0, position, k).y += lift
      controls.target.lerpVectors(t0, target, k)
    })
  }

  const viewOf = (key: PlaceKey) => {
    const def = placeDef(key)
    const front = def.facing === 'x' ? new Vector3(1, 0, 0) : new Vector3(0, 0, 1)
    const dir = front.applyAxisAngle(new Vector3(0, 1, 0), def.facing === 'x' ? -0.55 : 0.55)
    const target = new Vector3(def.x, def.height * 0.38, def.z)
    // The plot sits between party walls: it is seen from higher up.
    const elevation = key === 'site' ? 0.92 : 0.62
    const position = target.clone().add(dir.multiplyScalar(Math.cos(elevation) * def.distance)).add(new Vector3(0, Math.sin(elevation) * def.distance, 0))
    return { position, target }
  }

  // The panels over the canvas: the scene's centre moves into the free part,
  // so what the step points at is not hidden behind them.
  const size = { w: 1, h: 1 }
  const insets = { right: 0, bottom: 0, top: 0 }
  const applyInsets = () => {
    const ox = insets.right / 2
    const oy = (insets.bottom - insets.top) / 2
    if (ox === 0 && oy === 0) camera.clearViewOffset()
    else camera.setViewOffset(size.w, size.h, ox, oy, size.w, size.h)
    camera.updateProjectionMatrix()
  }

  const handle: WorldHandle = {
    setPieces(keys, opts = {}) {
      const wanted = new Set(keys)
      // What arrives comes in the order of the works (site.ts lists the pieces
      // in that order), each starting a little before the previous ends: the
      // building goes up floor after floor. What goes, goes once it is all up;
      // only the soil sinks first, as the excavation opens.
      const changes = [...pieceTs].filter(([key, p]) => (wanted.has(key) ? 1 : 0) !== p.target)
      let clock = 0
      const arrivals = new Map<string, number>()
      for (const [key, p] of changes) {
        if (!wanted.has(key)) continue
        arrivals.set(key, clock)
        clock += p.piece.duration * (changes.length > 3 ? 0.55 : 0.3)
      }
      for (const [key, p] of changes) {
        const target = wanted.has(key) ? 1 : 0
        p.target = target
        const from = p.t
        const delay = target === 1 ? (arrivals.get(key) ?? 0) : key === 'plot.ground' ? 0 : clock
        if (opts.instant) {
          p.t = target
          p.piece.set(target)
        } else {
          tween(from, target, p.piece.duration * (target === 1 ? 1 : 0.55), (v) => {
            p.t = v
            p.piece.set(v)
          }, delay)
        }
      }
      // A place that is a piece (the sales office) shows its name only once it stands.
      const sales = labelObjects.get('oficina_ventas')
      if (sales) sales.object.visible = wanted.has('place.oficina_ventas')
      wake()
    },
    focus(key, opts = {}) {
      if (key === null) return handle.overview()
      const view = viewOf(key)
      fly(view.position, view.target, !!opts.instant)
    },
    setActive(keys) {
      for (const [key, label] of labelObjects) label.element.classList.toggle('is-active', keys.includes(key))
      const first = keys[0]
      if (!first) {
        pin.visible = false
        ring.visible = false
      } else {
        const def = placeDef(first)
        pinBase = new Vector3(def.x, (first === 'site' ? Math.max(6, def.height * 0.6) : def.height) + 4, def.z)
        pin.visible = true
        ring.visible = true
        ring.position.set(def.x, 0.25, def.z)
      }
      wake()
    },
    setMode(next, opts = {}) {
      for (const k of ['cutaway', 'dusk'] as const) {
        const target = next[k]
        if (target === undefined || target === mode[k]) continue
        tween(mode[k], target, opts.instant ? 0 : 1.6, (v) => {
          mode[k] = v
          applyMode()
        })
      }
      wake()
    },
    emit(from, kind) {
      const def = placeDef(from)
      const origin = new Vector3(def.x, (from === 'site' ? 8 : def.height) + 1, def.z)
      const meshObject =
        kind === 'tax'
          ? new Mesh(coinGeometry, coinMat)
          : new Mesh(new PlaneGeometry(2.4, 3.2), new MeshBasicMaterial({ map: paperTex[kind], transparent: true, side: DoubleSide }))
      if (kind === 'tax') meshObject.rotation.x = Math.PI / 2
      scene.add(meshObject)
      floaters.push({ mesh: meshObject, age: options.reduced ? 1.4 : 0, life: 2.2, from: origin })
      wake()
    },
    zoom(factor) {
      const offset = camera.position.clone().sub(controls.target)
      const length = Math.min(controls.maxDistance, Math.max(controls.minDistance, offset.length() * factor))
      fly(controls.target.clone().add(offset.setLength(length)), controls.target.clone(), false)
    },
    overview() {
      fly(HOME_POS.clone(), HOME_TARGET.clone(), false)
    },
    setAutoRotate(on) {
      controls.autoRotate = on && !options.reduced
      controls.autoRotateSpeed = 0.55
      if (on) wake()
    },
    setLabels(on) {
      labels.domElement.style.display = on ? '' : 'none'
      wake()
    },
    resize() {
      const parent = canvas.parentElement
      if (!parent) return
      size.w = parent.clientWidth
      size.h = parent.clientHeight
      renderer.setSize(size.w, size.h, false)
      labels.setSize(size.w, size.h)
      camera.aspect = size.w / Math.max(1, size.h)
      // A narrow screen sees the board from a little further.
      camera.fov = size.w < 640 ? 40 : 30
      applyInsets()
      wake()
    },
    setInsets(next) {
      insets.right = next.right
      insets.bottom = next.bottom
      insets.top = next.top ?? 0
      applyInsets()
      wake()
    },
    dispose() {
      stop()
      document.removeEventListener('visibilitychange', onVisibility)
      canvas.removeEventListener('pointerdown', onDown)
      canvas.removeEventListener('pointerup', onUp)
      controls.dispose()
      labels.domElement.remove()
      scene.traverse((o) => {
        const m = o as Mesh
        if (m.geometry) m.geometry.dispose()
      })
      environment.dispose()
      pmrem.dispose()
      renderer.dispose()
    },
  }
  handle.resize()
  wake()
  if (import.meta.env.DEV) (window as unknown as { __w3d: unknown }).__w3d = { pieces: pieceTs, scene, camera, controls }
  return handle
}

function nullWorld(): WorldHandle {
  const noop = () => {}
  return { setPieces: noop, focus: noop, setActive: noop, setMode: noop, emit: noop, zoom: noop, overview: noop, setLabels: noop, setAutoRotate: noop, setInsets: noop, resize: noop, dispose: noop }
}
