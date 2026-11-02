// The closing scene's assets (S9), made from their sources in modeles-sources/:
// `node scripts/modeles-3d.mjs`. Output in public/modeles/ and public/draco/.
//
//   - « Door Key » by GUIBEL and « Embellished front door 3 » by anDDDres,
//     both from Sketchfab under CC BY 4.0 (credited in the page's footer and
//     in docs/credits-photos.md), as downloaded (the two .zip files).
//   - « Black Walnut Veneer 02 » (Jenelle van Heerden) and the « Lebombo »
//     HDRI (Greg Zaal), from Poly Haven, CC0.
//
// The key is steel in its source: its colour map is turned to brass, its
// relief and its roughness kept. The door's paint gives way to walnut: its
// panels have no texture coordinates, so they are projected here, one metre
// of veneer to one metre of door. Its outline strokes go. The leaf, its handle
// and its lock are gathered under one node, « battant », so that the page can
// swing it on its hinges. Geometry in Draco, textures in WebP (1 024 px at
// most), the HDRI brought down to 512 px wide.
import { execFileSync } from 'node:child_process'
import { mkdir, mkdtemp, readFile, rm, stat, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join as joinPath } from 'node:path'
import { NodeIO, Primitive } from '@gltf-transform/core'
import { ALL_EXTENSIONS } from '@gltf-transform/extensions'
import { dedup, draco, normals, prune, textureCompress, weld } from '@gltf-transform/functions'
import draco3d from 'draco3dgltf'
import sharp from 'sharp'

const SOURCES = 'modeles-sources'
const OUT = 'public/modeles'
const INCH = 1 / 39.37 // the door is drawn in inches; the veneer covers one metre

const io = new NodeIO()
  .registerExtensions(ALL_EXTENSIONS)
  .registerDependencies({ 'draco3d.encoder': await draco3d.createEncoderModule(), 'draco3d.decoder': await draco3d.createDecoderModule() })

/** A model's .gltf, out of its .zip, in a folder of its own. */
async function unpack(name) {
  const dir = await mkdtemp(joinPath(tmpdir(), `${name}-`))
  execFileSync('unzip', ['-q', '-o', `${SOURCES}/${name}.zip`, '-d', dir])
  return { dir, file: joinPath(dir, 'scene.gltf') }
}

/** The pipeline both models end with: shared data merged, nothing unused, Draco, WebP. */
async function finish(doc, to) {
  await doc.transform(
    dedup(),
    prune(),
    weld(),
    textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [1024, 1024], quality: 90, slots: /normal/ }),
    textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [1024, 1024], quality: 80, slots: /^(?!normal)/ }),
    draco({ method: 'edgebreaker', quantizePosition: 14, quantizeNormal: 10, quantizeTexcoord: 12 }),
  )
  await io.write(to, doc)
  return (await stat(to)).size
}

// ── The key ────────────────────────────────────────────────────────────────
async function cle() {
  const { dir, file } = await unpack('door_key')
  const doc = await io.read(file)
  const [material] = doc.getRoot().listMaterials()
  material.setName('laiton').setMetallicFactor(1).setRoughnessFactor(1).setBaseColorFactor([1, 1, 1, 1])
  // Steel to brass: the lightness of each pixel picked along a brass ramp, so
  // that scratches and engraving stay where they are.
  const texture = material.getBaseColorTexture()
  const { data, info } = await sharp(Buffer.from(texture.getImage())).removeAlpha().raw().toBuffer({ resolveWithObject: true })
  const dark = [0.3, 0.17, 0.05]
  const light = [1, 0.84, 0.52]
  for (let i = 0; i < data.length; i += info.channels) {
    const l = Math.pow((0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2]) / 255, 0.9)
    for (let c = 0; c < 3; c++) data[i + c] = Math.round(255 * (dark[c] + (light[c] - dark[c]) * l))
  }
  texture.setImage(await sharp(data, { raw: info }).webp({ quality: 80, effort: 6 }).toBuffer()).setMimeType('image/webp')
  const size = await finish(doc, `${OUT}/cle.glb`)
  await rm(dir, { recursive: true })
  return size
}

// ── The door ───────────────────────────────────────────────────────────────
const LEAF = ['instance_3', 'instance_5', 'instance_6', 'instance_16', 'instance_17', 'instance_18', 'group_1']

/** Texture coordinates for a panel that has none: each face projected along the axis it faces. */
function project(prim, turn) {
  const position = prim.getAttribute('POSITION')
  const normal = prim.getAttribute('NORMAL')
  const uv = new Float32Array(position.getCount() * 2)
  const p = [0, 0, 0]
  const n = [0, 0, 0]
  for (let i = 0; i < position.getCount(); i++) {
    position.getElement(i, p)
    normal.getElement(i, n)
    const [ax, ay, az] = n.map(Math.abs)
    // Drawn Z-up: x across the door, y through it, z up it.
    let [u, v] = ay >= ax && ay >= az ? [p[0], p[2]] : ax >= az ? [p[1], p[2]] : [p[0], p[1]]
    if (turn) [u, v] = [v, u]
    uv[i * 2] = u * INCH
    uv[i * 2 + 1] = v * INCH
  }
  return uv
}

async function porte() {
  const { dir, file } = await unpack('embellished_front_door_3')
  const doc = await io.read(file)
  const root = doc.getRoot()
  const buffer = root.listBuffers()[0]

  // The outline strokes, and the white faces that double the glass, go.
  for (const mesh of root.listMeshes()) {
    for (const prim of mesh.listPrimitives()) {
      if (prim.getMode() !== Primitive.Mode.TRIANGLES || prim.getMaterial()?.getName() === 'edge_color000255') prim.dispose()
    }
  }

  // The door is drawn in mirrored halves whose normals do not agree with their
  // faces: they are worked out again from the faces themselves.
  await doc.transform(normals({ overwrite: true }))

  // Materials renamed for the page, which gives them their final look.
  const byName = Object.fromEntries(root.listMaterials().map((m) => [m.getName(), m]))
  const wood = byName.Color_C08.setName('noyer').setBaseColorFactor([1, 1, 1, 1]).setMetallicFactor(0).setRoughnessFactor(1)
  byName['0049_Beige'].setName('cadre')
  byName['0131_Silver'].setName('poignee')
  byName.Translucent_Glass_Blue.setName('vitrail')

  // The walnut veneer: colour, relief, and roughness (green channel of the glTF map).
  const veneer = (suffix) => readFile(`${SOURCES}/polyhaven/black_walnut_veneer_02_${suffix}_1k.jpg`)
  const rough = await sharp(await veneer('rough')).greyscale().raw().toBuffer({ resolveWithObject: true })
  const mr = Buffer.alloc(rough.info.width * rough.info.height * 3)
  for (let i = 0; i < rough.info.width * rough.info.height; i++) mr[i * 3 + 1] = rough.data[i]
  const texture = (name, image) => doc.createTexture(name).setImage(image).setMimeType('image/jpeg')
  wood
    .setBaseColorTexture(texture('noyer-couleur', await veneer('diff')))
    .setNormalTexture(texture('noyer-relief', await veneer('nor_gl')))
    .setMetallicRoughnessTexture(texture('noyer-rugosite', await sharp(mr, { raw: { width: rough.info.width, height: rough.info.height, channels: 3 } }).jpeg({ quality: 92 }).toBuffer()))

  for (const mesh of root.listMeshes()) {
    for (const prim of mesh.listPrimitives()) {
      if (prim.getMaterial() !== wood || !prim.getAttribute('NORMAL')) continue
      const uv = project(prim, true) // the grain runs up the door
      prim.setAttribute('TEXCOORD_0', doc.createAccessor().setType('VEC2').setArray(uv).setBuffer(buffer))
    }
  }

  // The leaf, its handle and its lock under one node, which the page swings.
  const nodes = root.listNodes()
  const parent = nodes.find((node) => node.getName() === 'group_0')
  const leaf = doc.createNode('battant')
  parent.addChild(leaf)
  for (const name of LEAF) {
    const found = nodes.filter((node) => node.getName() === name)
    if (found.length !== 1) throw new Error(`${name} : ${found.length} nœuds`)
    leaf.addChild(found[0]) // its own transform is kept: the new node has none
  }

  const size = await finish(doc, `${OUT}/porte.glb`)
  await rm(dir, { recursive: true })
  return size
}

// ── The HDRI, 1 024 → 512 px wide ──────────────────────────────────────────
function readHdr(bytes) {
  let at = 0
  const line = () => {
    let end = bytes.indexOf(0x0a, at)
    const text = bytes.subarray(at, end).toString('latin1')
    at = end + 1
    return text
  }
  while (line() !== '');
  const [, height, , width] = line().split(' ').map((v) => (/^\d+$/.test(v) ? Number(v) : v))
  const pixels = new Float32Array(width * height * 3)
  const scan = new Uint8Array(width * 4)
  for (let y = 0; y < height; y++) {
    at += 4 // the scanline header: 2, 2, width
    for (let c = 0; c < 4; c++) {
      for (let x = 0; x < width; ) {
        let count = bytes[at++]
        if (count > 128) {
          count -= 128
          const value = bytes[at++]
          while (count--) scan[(x++) * 4 + c] = value
        } else while (count--) scan[(x++) * 4 + c] = bytes[at++]
      }
    }
    for (let x = 0; x < width; x++) {
      const e = scan[x * 4 + 3]
      const f = e ? Math.pow(2, e - 136) : 0
      for (let c = 0; c < 3; c++) pixels[(y * width + x) * 3 + c] = scan[x * 4 + c] * f
    }
  }
  return { width, height, pixels }
}

function writeHdr({ width, height, pixels }) {
  const out = [Buffer.from(`#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n-Y ${height} +X ${width}\n`, 'latin1')]
  for (let y = 0; y < height; y++) {
    const rgbe = new Uint8Array(width * 4)
    for (let x = 0; x < width; x++) {
      const i = (y * width + x) * 3
      const m = Math.max(pixels[i], pixels[i + 1], pixels[i + 2])
      if (m < 1e-32) continue
      const e = Math.ceil(Math.log2(m) + 1e-9)
      const scale = 256 / Math.pow(2, e)
      rgbe[x * 4] = Math.min(255, Math.floor(pixels[i] * scale))
      rgbe[x * 4 + 1] = Math.min(255, Math.floor(pixels[i + 1] * scale))
      rgbe[x * 4 + 2] = Math.min(255, Math.floor(pixels[i + 2] * scale))
      rgbe[x * 4 + 3] = e + 128
    }
    const line = [2, 2, width >> 8, width & 255]
    for (let c = 0; c < 4; c++) {
      // Runs of equal bytes, and literal stretches, 127 at most each.
      let x = 0
      while (x < width) {
        let run = 1
        while (x + run < width && run < 127 && rgbe[(x + run) * 4 + c] === rgbe[x * 4 + c]) run++
        if (run >= 4) {
          line.push(128 + run, rgbe[x * 4 + c])
          x += run
          continue
        }
        let end = x
        while (end < width && end - x < 128) {
          let ahead = 1
          while (end + ahead < width && ahead < 4 && rgbe[(end + ahead) * 4 + c] === rgbe[end * 4 + c]) ahead++
          if (ahead >= 4) break
          end++
        }
        line.push(end - x)
        for (let k = x; k < end; k++) line.push(rgbe[k * 4 + c])
        x = end
      }
    }
    out.push(Buffer.from(line))
  }
  return Buffer.concat(out)
}

async function hdri() {
  const source = readHdr(await readFile(`${SOURCES}/polyhaven/lebombo_1k.hdr`))
  const width = 512
  const height = 256
  const pixels = new Float32Array(width * height * 3)
  const fx = source.width / width
  const fy = source.height / height
  for (let y = 0; y < height; y++)
    for (let x = 0; x < width; x++)
      for (let c = 0; c < 3; c++) {
        let sum = 0
        for (let dy = 0; dy < fy; dy++) for (let dx = 0; dx < fx; dx++) sum += source.pixels[((y * fy + dy) * source.width + x * fx + dx) * 3 + c]
        pixels[(y * width + x) * 3 + c] = sum / (fx * fy)
      }
  const to = `${OUT}/lebombo-512.hdr`
  await writeFile(to, writeHdr({ width, height, pixels }))
  return (await stat(to)).size
}

/**
 * The Draco decoder as one worker file served by this site. Three's loader
 * would build it as a blob: URL, and run the WebAssembly build: the site's
 * Content-Security-Policy allows neither, so the page gets the plain JS
 * decoder and the same worker code, from here (porteScene points the loader
 * at it).
 */
async function decodeur() {
  const library = await readFile('node_modules/three/examples/jsm/libs/draco/gltf/draco_decoder.js', 'utf8')
  const loader = await readFile('node_modules/three/examples/jsm/loaders/DRACOLoader.js', 'utf8')
  const start = loader.indexOf('function DRACOWorker() {')
  let depth = 0
  let end = loader.indexOf('{', start)
  for (let i = end; i < loader.length; i++) {
    if (loader[i] === '{') depth++
    else if (loader[i] === '}' && --depth === 0) {
      end = i
      break
    }
  }
  const body = loader.slice(loader.indexOf('{', start) + 1, end)
  const to = 'public/draco/draco_worker.js'
  await writeFile(to, ['/* draco decoder (three/examples/jsm/libs/draco/gltf) */', library, '', '/* worker (DRACOWorker, three/examples/jsm/loaders/DRACOLoader.js) */', body].join('\n'))
  return (await stat(to)).size
}

await mkdir(OUT, { recursive: true })
await rm('public/draco', { recursive: true, force: true })
await mkdir('public/draco', { recursive: true })
const sizes = { 'cle.glb': await cle(), 'porte.glb': await porte(), 'lebombo-512.hdr': await hdri(), 'draco/draco_worker.js': await decodeur() }
console.table(Object.fromEntries(Object.entries(sizes).map(([k, v]) => [k, `${(v / 1024).toFixed(0)} KB`])))
console.log(`total ${(Object.values(sizes).reduce((a, b) => a + b, 0) / 1024 / 1024).toFixed(2)} MB`)
