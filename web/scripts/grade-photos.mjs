// One look for the page's two illustration photos. Run by hand when a source
// changes: `node scripts/grade-photos.mjs`.
//
// A cinema grade (night-blue shadows, slightly warm highlights, contrast and
// saturation brought to one level, corners falling off) and a little grain,
// so the stock photos sit with the rest of the page. Nothing is done that
// changes what a picture shows. The graded copies go to public/photos/.
import { mkdir } from 'node:fs/promises'
import sharp from 'sharp'

/**
 * Two free stock photographs (Pexels licence, commercial use allowed), neither
 * of them a development of the course or of its instructor (constitution, I):
 * the residence behind S1 and its pencil drawing, the building site behind
 * S2. Each is labelled "Foto de ilustración" on the page and credited in
 * docs/credits-photos.md. Their sources sit in photos-sources/,
 * outside the served folder. The frames are chosen by hand, in the source's
 * pixels: `wide` for the landscape screen, `tall` for phones.
 */
const ILLUSTRATIONS = [
  {
    name: 'illustration-residence',
    source: 'photos-sources/pexels-19332514.jpg',
    wide: { left: 0, top: 1300, width: 3200, height: 1800 },
    tall: { left: 380, top: 400, width: 1840, height: 2760 },
  },
  {
    name: 'illustration-chantier',
    source: 'photos-sources/pexels-1402923.jpg',
    wide: { left: 0, top: 375, width: 3600, height: 2025 },
    tall: { left: 1340, top: 0, width: 1600, height: 2400 },
  },
]

// A small generator with a seed: the same grain on every run. (The copies in
// public/photos/ come from the Ebni run, which graded other pictures first:
// a new run gives the same look with a different grain.)
let seed = 20261014
const random = () => {
  seed = (seed + 0x6d2b79f5) | 0
  let mixed = Math.imul(seed ^ (seed >>> 15), 1 | seed)
  mixed = (mixed + Math.imul(mixed ^ (mixed >>> 7), 61 | mixed)) ^ mixed
  return ((mixed ^ (mixed >>> 14)) >>> 0) / 4294967296
}

const SHADOW = [0.035, 0.11, 0.24] // night blue
const LIGHT = [1, 0.9, 0.74] // warm
const clamp = (value) => Math.min(1, Math.max(0, value))

/** The grade, pixel by pixel: nothing moves, only tone and colour. */
function grade(pixels, { channels, width, height }) {
  for (let index = 0; index < pixels.length; index += channels) {
    let r = pixels[index] / 255
    let g = pixels[index + 1] / 255
    let b = pixels[index + 2] / 255
    const luma = 0.2126 * r + 0.7152 * g + 0.0722 * b
    // Saturation to one level, then an S-curve with lifted blacks and held highlights.
    const tone = (value) => {
      const muted = luma + (value - luma) * 0.76
      const curved = clamp((muted - 0.5) * 1.16 + 0.47)
      return 0.03 + 0.93 * (curved * curved * (3 - 2 * curved) * 0.55 + curved * 0.45)
    }
    r = tone(r)
    g = tone(g)
    b = tone(b)
    // Shadows lean to night blue, highlights to warm.
    const inShadow = Math.pow(1 - luma, 1.7) * 0.58
    const inLight = Math.pow(luma, 2.2) * 0.24
    r = r + (SHADOW[0] - r) * inShadow + (LIGHT[0] - r) * inLight
    g = g + (SHADOW[1] - g) * inShadow + (LIGHT[1] - g) * inLight
    b = b + (SHADOW[2] - b) * inShadow + (LIGHT[2] - b) * inLight
    // The corners fall off towards the night, as through a lens.
    const pixel = index / channels
    const dx = ((pixel % width) / width - 0.5) * 2
    const dy = (Math.floor(pixel / width) / height - 0.5) * 2
    const edge = clamp((dx * dx + dy * dy - 0.35) / 1.65) * 0.42
    r = r + (SHADOW[0] - r) * edge
    g = g + (SHADOW[1] - g) * edge
    b = b + (SHADOW[2] - b) * edge
    // Grain: the same on the three channels, stronger in the mid-tones.
    const grain = (random() + random() + random() - 1.5) * 0.05 * (1 - Math.abs(luma - 0.5))
    pixels[index] = Math.round(clamp(r + grain) * 255)
    pixels[index + 1] = Math.round(clamp(g + grain) * 255)
    pixels[index + 2] = Math.round(clamp(b + grain) * 255)
  }
}

async function render(source, box, width, height, to) {
  const { data, info } = await sharp(source).extract(box).resize(width, height, { fit: 'cover', position: sharp.strategy.attention }).removeAlpha().raw().toBuffer({ resolveWithObject: true })
  grade(data, info)
  await sharp(data, { raw: info }).webp({ quality: 70, effort: 6 }).toFile(to)
}

await mkdir('public/photos', { recursive: true })
for (const { name, source, wide, tall } of ILLUSTRATIONS) {
  await render(source, wide, 1920, 1080, `public/photos/${name}-plein.webp`)
  await render(source, tall, 1080, 1620, `public/photos/${name}-haut.webp`)
}
