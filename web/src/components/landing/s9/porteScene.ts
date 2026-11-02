import {
  ACESFilmicToneMapping,
  Box3,
  CanvasTexture,
  Color,
  DirectionalLight,
  DoubleSide,
  EquirectangularReflectionMapping,
  Group,
  type Material,
  MathUtils,
  Mesh,
  MeshBasicMaterial,
  MeshStandardMaterial,
  type Object3D,
  PCFSoftShadowMap,
  PMREMGenerator,
  PerspectiveCamera,
  PlaneGeometry,
  PointLight,
  SRGBColorSpace,
  Scene,
  SpotLight,
  TextureLoader,
  Vector3,
  WebGLRenderer,
} from 'three'
import { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader.js'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import { HDRLoader } from 'three/examples/jsm/loaders/HDRLoader.js'

/**
 * The handover (S9), with real models: a brass key comes to the lock of a
 * walnut front door, goes in, turns; the door swings open on a warm light
 * that also comes through its side lights. Night outside, lit by the moon
 * and the Lebombo HDRI (Poly Haven). Assets made by scripts/modeles-3d.mjs.
 *
 * `setProgress` is the scroll, 0 to 1: the key comes up until NEAR, goes in
 * until IN, turns until TURNED, then the door opens until OPEN. The scene is
 * drawn only when something has changed.
 */
export interface PorteScene {
  setProgress(p: number): void
  setRunning(running: boolean): void
  resize(): void
  dispose(): void
}

export const NEAR = 0.3
export const IN = 0.38
export const TURNED = 0.5
export const OPEN = 0.78

const clamp = (value: number) => Math.min(1, Math.max(0, value))
const between = (p: number, from: number, to: number) => clamp((p - from) / (to - from))
const smooth = (t: number) => t * t * (3 - 2 * t)
const mix = (a: Vector3, b: Vector3, t: number) => a.clone().lerp(b, t)

/** The key's length in its own units, brought to a real key's, about 5.5 cm (in inches, like the door). */
const KEY_SCALE = 1.15

export async function createPorteScene(canvas: HTMLCanvasElement): Promise<PorteScene> {
  const renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' })
  renderer.outputColorSpace = SRGBColorSpace
  renderer.toneMapping = ACESFilmicToneMapping
  renderer.toneMappingExposure = 0.95
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = PCFSoftShadowMap
  renderer.setClearAlpha(0)

  // The site's Content-Security-Policy allows no blob: worker, no blob: fetch
  // and no WebAssembly: Draco is decoded by a worker file served from here, in
  // plain JS (scripts/modeles-3d.mjs), and textures are read through <img>.
  const draco = new DRACOLoader().setDecoderConfig({ type: 'js' }).setWorkerLimit(1) // two models: one worker is enough
  const decoder = draco as unknown as { decoderPending: Promise<void> | null; workerSourceURL: string; _initDecoder(): Promise<void> }
  decoder._initDecoder = () =>
    (decoder.decoderPending ??= Promise.resolve().then(() => {
      decoder.workerSourceURL = '/draco/draco_worker.js'
    }))
  const gltf = new GLTFLoader().setDRACOLoader(draco)
  gltf.register((parser) => {
    const reader = parser as unknown as { textureLoader: TextureLoader; options: { manager: ConstructorParameters<typeof TextureLoader>[0] } }
    reader.textureLoader = new TextureLoader(reader.options.manager)
    return { name: 'construye_textures_img' }
  })
  const [porte, cle, hdr] = await Promise.all([gltf.loadAsync('/modeles/porte.glb'), gltf.loadAsync('/modeles/cle.glb'), new HDRLoader().loadAsync('/modeles/lebombo-512.hdr')])
  draco.dispose()

  const scene = new Scene()
  const pmrem = new PMREMGenerator(renderer)
  hdr.mapping = EquirectangularReflectionMapping
  const environment = pmrem.fromEquirectangular(hdr)
  hdr.dispose()
  pmrem.dispose()
  scene.environment = environment.texture
  scene.environmentIntensity = 0.12

  // ── The door, and where its parts are ─────────────────────────────────────
  const door = porte.scene
  scene.add(door)
  door.updateMatrixWorld(true)
  // The keyhole: the slot itself, not the middle of the lock (it sits lower, on the plug).
  // It is the lock's one part that is narrow and upright.
  const lock = door.getObjectByName('instance_0')!
  const lockBox = new Box3().setFromObject(lock)
  let slot: Box3 | null = null
  lock.traverse((object) => {
    if (!(object as Mesh).isMesh) return
    const box = new Box3().setFromObject(object)
    const size = box.getSize(new Vector3())
    if (size.x < 0.3 && size.y > size.x * 2 && (!slot || size.x < slot.getSize(new Vector3()).x)) slot = box
  })
  const slotBox: Box3 = slot ?? lockBox
  const keyhole = slotBox.getCenter(new Vector3()).setZ(lockBox.max.z)
  const leaf = door.getObjectByName('battant')!
  const leafBox = new Box3().setFromObject(leaf)
  const all = new Box3().setFromObject(door)
  const centre = all.getCenter(new Vector3())

  // The leaf swings on its hinges, on the side away from the handle.
  // The hinge sits in the scene itself, upright, whatever the model's own axes.
  const hinge = new Group()
  hinge.position.set(leafBox.min.x, 0, (leafBox.min.z + leafBox.max.z) / 2)
  scene.add(hinge)
  hinge.updateMatrixWorld(true)
  hinge.attach(leaf)

  const glass: MeshStandardMaterial[] = []
  const materials = new Set<Material>()
  door.traverse((object) => {
    const mesh = object as Mesh
    if (!mesh.isMesh) return
    mesh.castShadow = true
    mesh.receiveShadow = true
    const material = mesh.material as MeshStandardMaterial
    materials.add(material)
    switch (material.name) {
      case 'noyer':
        material.color.set('#8C6B55') // the veneer, a shade darker: a noble walnut
        material.envMapIntensity = 0.8
        break
      case 'cadre':
        material.color.set('#E7DFCF')
        material.roughness = 0.62
        break
      case 'poignee':
        material.color.set('#C08F38')
        material.metalness = 1
        material.roughness = 0.3
        material.envMapIntensity = 2.2
        break
      case 'vitrail':
        material.color.set('#3A2C1A')
        material.transparent = true
        material.opacity = 0.62
        material.roughness = 0.08
        material.metalness = 0
        material.emissive = new Color('#FFB44A')
        material.emissiveIntensity = 0
        material.side = DoubleSide
        mesh.castShadow = false
        if (!glass.includes(material)) glass.push(material)
        break
    }
  })

  // ── The wall the door is set in, the step, and the light inside ───────────
  const wallMaterial = new MeshStandardMaterial({ color: '#28313D', roughness: 0.95 })
  const groundMaterial = new MeshStandardMaterial({ color: '#1B2028', roughness: 0.9 })
  const plane = new PlaneGeometry(1, 1)
  const wall = (x0: number, x1: number, y0: number, y1: number) => {
    const piece = new Mesh(plane, wallMaterial)
    piece.scale.set(x1 - x0, y1 - y0, 1)
    piece.position.set((x0 + x1) / 2, (y0 + y1) / 2, all.min.z + 0.5)
    piece.receiveShadow = true
    scene.add(piece)
  }
  wall(all.min.x - 400, all.min.x + 2, all.min.y, all.max.y + 300)
  wall(all.max.x - 2, all.max.x + 400, all.min.y, all.max.y + 300)
  wall(all.min.x, all.max.x, all.max.y - 2, all.max.y + 300)
  const ground = new Mesh(plane, groundMaterial)
  ground.rotation.x = -Math.PI / 2
  ground.scale.set(1200, 800, 1)
  ground.position.set(centre.x, all.min.y, 0)
  ground.receiveShadow = true
  scene.add(ground)

  // Inside: a warm glow far behind the door, seen through it and its glass.
  const glowCanvas = document.createElement('canvas')
  glowCanvas.width = glowCanvas.height = 256
  const g = glowCanvas.getContext('2d')!
  const radial = g.createRadialGradient(128, 128, 0, 128, 128, 128)
  radial.addColorStop(0, '#FFF1D6')
  radial.addColorStop(0.35, '#FBD9A0')
  radial.addColorStop(0.7, '#E8A33D')
  radial.addColorStop(1, '#7A4A12')
  g.fillStyle = radial
  g.fillRect(0, 0, 256, 256)
  const glowTexture = new CanvasTexture(glowCanvas)
  glowTexture.colorSpace = SRGBColorSpace
  const inside = new MeshBasicMaterial({ map: glowTexture, color: '#000000' })
  const backdrop = new Mesh(plane, inside)
  backdrop.scale.set(420, 300, 1)
  backdrop.position.set(centre.x, centre.y, all.min.z - 90)
  scene.add(backdrop)

  const warm = new PointLight('#FFB054', 0, 0, 1.4)
  warm.position.set(centre.x, centre.y + 14, all.min.z - 70)
  scene.add(warm)

  // Outside: the moon from the upper left, soft shadows; a little porch light on the lock.
  const moon = new DirectionalLight('#AFC3FF', 1)
  moon.position.set(centre.x - 90, centre.y + 140, 150)
  moon.target.position.copy(centre)
  moon.castShadow = true
  moon.shadow.mapSize.set(2048, 2048)
  moon.shadow.radius = 6
  moon.shadow.blurSamples = 16
  moon.shadow.bias = -0.0005
  moon.shadow.normalBias = 0.4
  Object.assign(moon.shadow.camera, { left: -110, right: 110, top: 110, bottom: -110, near: 10, far: 500 })
  scene.add(moon, moon.target)
  const porch = new SpotLight('#FFD9A0', 1800, 170, MathUtils.degToRad(17), 0.95, 1.6)
  porch.position.set(keyhole.x + 16, keyhole.y + 62, keyhole.z + 44)
  porch.target.position.copy(keyhole)
  scene.add(porch, porch.target)

  // ── The key: brass, its blade upright, its tip towards the lock ──────────
  const key = new Group()
  const keyMesh = cle.scene
  keyMesh.scale.setScalar(KEY_SCALE)
  keyMesh.rotation.x = Math.PI / 2 // its length along the way into the door
  key.add(keyMesh)
  // The blade is not on the model's own axis: the key is moved so that the
  // axis it turns on runs down the middle of its blade, near the tip.
  keyMesh.updateMatrixWorld(true)
  const tip = new Box3()
  const point = new Vector3()
  let tipZ = Infinity
  keyMesh.traverse((object) => {
    const mesh = object as Mesh
    if (!mesh.isMesh) return
    const position = mesh.geometry.attributes.position
    for (let i = 0; i < position.count; i++) tipZ = Math.min(tipZ, point.fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld).z)
    for (let i = 0; i < position.count; i++) {
      point.fromBufferAttribute(position, i).applyMatrix4(mesh.matrixWorld)
      if (point.z < tipZ + 0.45) tip.expandByPoint(point)
    }
  })
  const blade = tip.getCenter(new Vector3())
  keyMesh.position.set(-blade.x, -blade.y, 0)
  /** How far the blade goes into the lock. */
  const DEPTH = 0.75
  keyMesh.traverse((object) => {
    const mesh = object as Mesh
    if (!mesh.isMesh) return
    mesh.castShadow = true
    const material = mesh.material as MeshStandardMaterial
    material.envMapIntensity = 1.6
    materials.add(material)
  })
  hinge.add(key) // it swings with the door once in
  // The plug (the slot and the rings around it) turns with the key: every part of the lock centred on the slot.
  const plug = new Group()
  hinge.add(plug)
  plug.position.copy(hinge.worldToLocal(keyhole.clone().setZ(0)))
  plug.updateMatrixWorld(true)
  const plugParts: Mesh[] = []
  lock.traverse((object) => {
    const mesh = object as Mesh
    if (!mesh.isMesh) return
    const centre = new Box3().setFromObject(mesh).getCenter(new Vector3())
    if (Math.abs(centre.x - keyhole.x) < 0.05 && Math.abs(centre.y - keyhole.y) < 0.05) plugParts.push(mesh)
  })
  plugParts.forEach((mesh) => plug.attach(mesh))
  const inHinge = (world: Vector3) => world.clone().sub(hinge.getWorldPosition(new Vector3()))
  // In: the blade inside the lock, the bow standing just off its face.
  const keyIn = inHinge(keyhole.clone().add(new Vector3(0, 0, -DEPTH - tipZ)))
  const keyNear = keyIn.clone().add(new Vector3(0, 0, 3.2))
  const keyFar = keyIn.clone().add(new Vector3(2.6, 1.4, 6.5))

  // ── The camera's path ─────────────────────────────────────────────────────
  const camera = new PerspectiveCamera(32, 1, 0.5, 4000)
  // Square on the door, at its own height: straight and in the middle of the screen.
  const wide = { at: centre.clone(), from: new Vector3() }
  const close = { at: keyhole.clone().add(new Vector3(-0.4, -0.2, 2.2)), from: keyhole.clone().add(new Vector3(8.5, 2.8, 14.5)) }
  const through = { at: new Vector3(centre.x, centre.y, all.min.z - 60), from: new Vector3() }
  const into = { at: through.at, from: new Vector3() }
  function frameShots() {
    // Far enough to hold the whole door, whatever the screen's shape.
    const v = MathUtils.degToRad(camera.fov / 2)
    const h = Math.atan(Math.tan(v) * camera.aspect)
    const size = all.getSize(new Vector3())
    const distance = Math.max((size.y * 1.18) / 2 / Math.tan(v), (size.x * 1.08) / 2 / Math.tan(h))
    wide.from.set(centre.x, centre.y, all.max.z + distance)
    through.from.set(centre.x, centre.y, all.max.z + distance * 0.62)
    into.from.set(centre.x, centre.y, all.max.z + distance * 0.18)
  }

  let progress = 0
  let dirty = true
  function apply() {
    const p = progress
    // The key: comes up, goes in, turns a quarter.
    const coming = smooth(between(p, 0.12, NEAR))
    const going = smooth(between(p, NEAR, IN))
    const turning = smooth(between(p, IN, TURNED))
    key.position.copy(going > 0 ? mix(keyNear, keyIn, going) : mix(keyFar, keyNear, coming))
    key.rotation.set(0, (1 - coming) * -0.5, Math.PI / 2 - turning * (Math.PI / 2))
    plug.rotation.z = -turning * (Math.PI / 2)
    key.visible = p > 0.1

    // The door swings in, the light comes from inside, through the glass too.
    const opening = smooth(between(p, TURNED + 0.02, OPEN))
    hinge.rotation.y = opening * MathUtils.degToRad(80)
    const glow = smooth(between(p, TURNED - 0.02, OPEN))
    warm.intensity = glow * 70000
    inside.color.setScalar(0.06 + glow * 0.95)
    for (const material of glass) material.emissiveIntensity = glow * 1.8
    scene.environmentIntensity = 0.12 + glow * 0.3
    porch.intensity = 1800 * (1 - glow * 0.6)

    // The camera: the whole door, close on the lock, back to see it open, then through it.
    const closing = smooth(between(p, 0.04, 0.26))
    const leaving = smooth(between(p, TURNED, 0.66))
    const entering = smooth(between(p, 0.7, 0.92))
    const at = mix(mix(mix(wide.at, close.at, closing), through.at, leaving), into.at, entering)
    const from = mix(mix(mix(wide.from, close.from, closing), through.from, leaving), into.from, entering)
    camera.position.copy(from)
    camera.lookAt(at)
    dirty = true
  }

  let running = false
  let disposed = false
  function frame() {
    if (!dirty || disposed) return
    dirty = false
    renderer.render(scene, camera)
  }

  function resize() {
    const width = canvas.clientWidth
    const height = canvas.clientHeight
    if (!width || !height) return
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, width < 700 ? 1.5 : 2))
    renderer.setSize(width, height, false)
    camera.aspect = width / height
    camera.updateProjectionMatrix()
    frameShots()
    apply()
  }
  resize()

  return {
    setProgress(p) {
      if (p === progress) return
      progress = p
      apply()
    },
    setRunning(next) {
      if (disposed || next === running) return
      running = next
      renderer.setAnimationLoop(running ? frame : null)
    },
    resize,
    dispose() {
      if (disposed) return
      disposed = true
      renderer.setAnimationLoop(null)
      const geometries = new Set<{ dispose(): void }>()
      scene.traverse((object: Object3D) => {
        const mesh = object as Mesh
        if (mesh.isMesh) geometries.add(mesh.geometry)
      })
      geometries.forEach((geometry) => geometry.dispose())
      materials.forEach((material) => {
        for (const value of Object.values(material)) if (value && typeof value === 'object' && 'isTexture' in value) (value as { dispose(): void }).dispose()
        material.dispose()
      })
      ;[wallMaterial, groundMaterial, inside].forEach((material) => material.dispose())
      glowTexture.dispose()
      environment.dispose()
      renderer.dispose()
    },
  }
}
