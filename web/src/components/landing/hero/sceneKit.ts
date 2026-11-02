import {
  BackSide,
  BufferGeometry,
  CanvasTexture,
  Color,
  DoubleSide,
  Float32BufferAttribute,
  Mesh,
  MeshBasicMaterial,
  PMREMGenerator,
  PlaneGeometry,
  Scene,
  SphereGeometry,
  Vector3,
  type WebGLRenderTarget,
  type WebGLRenderer,
} from 'three'
import { clamp01 } from './timeline'

/**
 * What the hero's WebGL scenes share: the handle the page drives them with,
 * the builders that turn boxes into one geometry per storey, the generated
 * sky and the painted contact shadow. Nothing here is downloaded.
 */
export interface BuildingSceneHandle {
  setProgress(p: number): void
  /** Where the pointer (or the phone's tilt) is, each axis from -1 to 1. */
  setPointer(x: number, y: number): void
  setRunning(running: boolean): void
  resize(): void
  dispose(): void
}

export interface BuildingSceneOptions {
  /** A narrow stage: the dismantled model is packed tighter. */
  compact: boolean
  maxPixelRatio: number
  /** Give the scene up when it runs under 25 frames a second. Off when the scene is forced on. */
  watchdog: boolean
  onFirstFrame(): void
  /** The graphics context was lost, or the scene is too slow for this device. */
  onFail(reason: 'context' | 'slow'): void
}

export type Quad = [Vector3, Vector3, Vector3, Vector3]

/** Collects boxes into one geometry: a storey's concrete is a single draw. */
export class SolidBuilder {
  private positions: number[] = []
  private normals: number[] = []
  private colors: number[] = []
  private indices: number[] = []

  box(cx: number, cy: number, cz: number, sx: number, sy: number, sz: number, color: Color) {
    const x0 = cx - sx / 2
    const x1 = cx + sx / 2
    const y0 = cy - sy / 2
    const y1 = cy + sy / 2
    const z0 = cz - sz / 2
    const z1 = cz + sz / 2
    const v = (x: number, y: number, z: number) => new Vector3(x, y, z)
    this.quad([v(x1, y0, z1), v(x1, y0, z0), v(x1, y1, z0), v(x1, y1, z1)], v(1, 0, 0), color)
    this.quad([v(x0, y0, z0), v(x0, y0, z1), v(x0, y1, z1), v(x0, y1, z0)], v(-1, 0, 0), color)
    this.quad([v(x0, y1, z1), v(x1, y1, z1), v(x1, y1, z0), v(x0, y1, z0)], v(0, 1, 0), color)
    this.quad([v(x0, y0, z0), v(x1, y0, z0), v(x1, y0, z1), v(x0, y0, z1)], v(0, -1, 0), color)
    this.quad([v(x0, y0, z1), v(x1, y0, z1), v(x1, y1, z1), v(x0, y1, z1)], v(0, 0, 1), color)
    this.quad([v(x1, y0, z0), v(x0, y0, z0), v(x0, y1, z0), v(x1, y1, z0)], v(0, 0, -1), color)
  }

  /**
   * A box whose faces carry their own shade: lightest on top, darker on the
   * sides. For geometry drawn without lighting (a room glowing from inside),
   * where nothing else would tell one face from the next.
   */
  shadedBox(cx: number, cy: number, cz: number, sx: number, sy: number, sz: number, color: Color) {
    const x0 = cx - sx / 2
    const x1 = cx + sx / 2
    const y0 = cy - sy / 2
    const y1 = cy + sy / 2
    const z0 = cz - sz / 2
    const z1 = cz + sz / 2
    const v = (x: number, y: number, z: number) => new Vector3(x, y, z)
    const tint = (factor: number) => color.clone().multiplyScalar(factor)
    this.quad([v(x1, y0, z1), v(x1, y0, z0), v(x1, y1, z0), v(x1, y1, z1)], v(1, 0, 0), tint(0.74))
    this.quad([v(x0, y0, z0), v(x0, y0, z1), v(x0, y1, z1), v(x0, y1, z0)], v(-1, 0, 0), tint(0.8))
    this.quad([v(x0, y1, z1), v(x1, y1, z1), v(x1, y1, z0), v(x0, y1, z0)], v(0, 1, 0), tint(1))
    this.quad([v(x0, y0, z0), v(x1, y0, z0), v(x1, y0, z1), v(x0, y0, z1)], v(0, -1, 0), tint(0.5))
    this.quad([v(x0, y0, z1), v(x1, y0, z1), v(x1, y1, z1), v(x0, y1, z1)], v(0, 0, 1), tint(0.88))
    this.quad([v(x1, y0, z0), v(x0, y0, z0), v(x0, y1, z0), v(x1, y1, z0)], v(0, 0, -1), tint(0.68))
  }

  quad(corners: Quad, normal: Vector3, color: Color) {
    // Whatever order the corners came in, the face must wind to its normal.
    const [a, b, c, d] = corners
    const facing = new Vector3().subVectors(b, a).cross(new Vector3().subVectors(c, a)).dot(normal)
    const ordered = facing >= 0 ? [a, b, c, d] : [a, d, c, b]
    const base = this.positions.length / 3
    for (const corner of ordered) {
      this.positions.push(corner.x, corner.y, corner.z)
      this.normals.push(normal.x, normal.y, normal.z)
      this.colors.push(color.r, color.g, color.b)
    }
    this.indices.push(base, base + 1, base + 2, base, base + 2, base + 3)
  }

  get empty() {
    return this.indices.length === 0
  }

  build(): BufferGeometry {
    const geometry = new BufferGeometry()
    geometry.setAttribute('position', new Float32BufferAttribute(this.positions, 3))
    geometry.setAttribute('normal', new Float32BufferAttribute(this.normals, 3))
    geometry.setAttribute('color', new Float32BufferAttribute(this.colors, 3))
    geometry.setIndex(this.indices)
    return geometry
  }
}

/**
 * Collects segments: a storey's whole line drawing is a single draw. Each
 * segment carries its own strength, so the slabs read first and the bay
 * divisions stay in the background, as line weights do on a drawing.
 */
export class LineBuilder {
  private positions: number[] = []
  private strengths: number[] = []

  segment(ax: number, ay: number, az: number, bx: number, by: number, bz: number, strength = 1) {
    this.positions.push(ax, ay, az, bx, by, bz)
    this.strengths.push(strength, strength, strength, strength, strength, strength)
  }

  box(cx: number, cy: number, cz: number, sx: number, sy: number, sz: number, strength = 1) {
    const x0 = cx - sx / 2
    const x1 = cx + sx / 2
    const y0 = cy - sy / 2
    const y1 = cy + sy / 2
    const z0 = cz - sz / 2
    const z1 = cz + sz / 2
    for (const y of [y0, y1]) {
      this.segment(x0, y, z0, x1, y, z0, strength)
      this.segment(x1, y, z0, x1, y, z1, strength)
      this.segment(x1, y, z1, x0, y, z1, strength)
      this.segment(x0, y, z1, x0, y, z0, strength)
    }
    for (const [x, z] of [[x0, z0], [x1, z0], [x1, z1], [x0, z1]]) this.segment(x, y0, z, x, y1, z, strength)
  }

  build(): BufferGeometry {
    const geometry = new BufferGeometry()
    geometry.setAttribute('position', new Float32BufferAttribute(this.positions, 3))
    geometry.setAttribute('color', new Float32BufferAttribute(this.strengths, 3))
    return geometry
  }
}

/** One more soft panel of light in the generated sky, for a scene that wants livelier reflections. */
export interface SkyPanel {
  colour: string
  strength: number
  at: [number, number, number]
  size: [number, number]
}

/**
 * The sky the glass reflects: a dawn gradient and three soft panels of light.
 * Rendered once into an environment map, then thrown away.
 */
export function buildEnvironment(renderer: WebGLRenderer, extra: SkyPanel[] = []): WebGLRenderTarget {
  const sky = new Scene()
  const dome = new SphereGeometry(60, 24, 16)
  const tint: number[] = []
  const low = new Color('#f4c48a')
  const mid = new Color('#6f9bd1')
  const high = new Color('#16305a')
  // What a façade seen from above mostly reflects: the ground's bounce.
  const ground = new Color('#35588c')
  const position = dome.getAttribute('position')
  const colour = new Color()
  for (let i = 0; i < position.count; i++) {
    const height = position.getY(i) / 60
    if (height < 0.08) colour.copy(ground).lerp(low, clamp01((height + 0.3) / 0.38))
    else colour.copy(low).lerp(mid, clamp01((height - 0.08) / 0.3)).lerp(high, clamp01((height - 0.38) / 0.6))
    tint.push(colour.r, colour.g, colour.b)
  }
  dome.setAttribute('color', new Float32BufferAttribute(tint, 3))
  const disposables: { dispose(): void }[] = [dome]
  const domeMaterial = new MeshBasicMaterial({ vertexColors: true, side: BackSide })
  disposables.push(domeMaterial)
  sky.add(new Mesh(dome, domeMaterial))

  const panel = (colour: string, strength: number, at: [number, number, number], size: [number, number]) => {
    const geometry = new PlaneGeometry(size[0], size[1])
    const material = new MeshBasicMaterial({ color: new Color(colour).multiplyScalar(strength), side: DoubleSide })
    const mesh = new Mesh(geometry, material)
    mesh.position.set(...at)
    mesh.lookAt(0, 4, 0)
    sky.add(mesh)
    disposables.push(geometry, material)
  }
  panel('#fff1dc', 7, [16, 18, 20], [18, 12])
  panel('#cfe0f5', 4, [-22, 12, 8], [16, 14])
  panel('#e8f0fb', 3, [4, 9, 34], [40, 10])
  panel('#ffb65c', 3, [6, 1.5, 30], [44, 4])
  extra.forEach(({ colour, strength, at, size }) => panel(colour, strength, at, size))

  const generator = new PMREMGenerator(renderer)
  const target = generator.fromScene(sky, 0.02)
  generator.dispose()
  disposables.forEach((item) => item.dispose())
  return target
}

/** A soft dark pool under the building, painted rather than computed. */
export function buildContactShadow(): CanvasTexture {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = 128
  const context = canvas.getContext('2d')
  if (context) {
    const gradient = context.createRadialGradient(64, 64, 6, 64, 64, 64)
    gradient.addColorStop(0, 'rgba(2, 8, 18, 0.85)')
    gradient.addColorStop(0.55, 'rgba(2, 8, 18, 0.35)')
    gradient.addColorStop(1, 'rgba(2, 8, 18, 0)')
    context.fillStyle = gradient
    context.fillRect(0, 0, 128, 128)
  }
  return new CanvasTexture(canvas)
}

