// The pencil drawing of the hero (S1), made from the stock photo it turns into
// (public/photos/residencia-ilustracion.webp, an illustration credited in
// docs/credits-photos.md and labelled as such on the page): no drawing of a
// building that is presented as the instructor's (constitution, I). Run by
// hand when the source photo changes: `node scripts/make-sketches.mjs`.
//
// The classic darkroom recipe: the photo in grey, dodged by a blurred negative
// of itself, leaves only the edges. They are saved as graphite on a
// transparent sheet.
import sharp from 'sharp'

const SOURCES = [{ from: 'public/photos/residencia-ilustracion.webp', to: 'public/croquis/residencia.webp' }]

// The hero's frame is 1200 × 652: the Pexels residence (1920 × 1080), cropped
// to that shape around its two towers.
await sharp('public/photos/illustration-residence-plein.webp')
  .extract({ left: 0, top: 18, width: 1920, height: 1043 })
  .resize(1200, 652)
  .webp({ quality: 78 })
  .toFile('public/photos/residencia-ilustracion.webp')

for (const { from, to } of SOURCES) {
  const grey = sharp(from).greyscale()
  const { width, height } = await grey.metadata()
  const base = await grey.clone().png().toBuffer()
  const negative = await grey.clone().negate().blur(5).png().toBuffer()
  const lines = await sharp(base)
    .composite([{ input: negative, blend: 'colour-dodge' }])
    .png()
    .toBuffer()
  // A second, finer pass keeps the window grid; multiplied, the two read as
  // a hard pencil over a soft one.
  const fine = await sharp(base)
    .composite([{ input: await grey.clone().negate().blur(1.4).png().toBuffer(), blend: 'colour-dodge' }])
    .png()
    .toBuffer()
  // White becomes transparent and the lines become graphite: the drawing can
  // then sit on the page's paper, or on the photo it turns into, with no box.
  const ink = await sharp(lines)
    .composite([{ input: fine, blend: 'multiply' }])
    .greyscale()
    .linear(1.25, -52)
    .gamma(1.6)
    .negate()
    .extractChannel(0)
    .raw()
    .toBuffer()
  await sharp({ create: { width, height, channels: 3, background: '#2C3440' } })
    .joinChannel(ink, { raw: { width, height, channels: 1 } })
    .webp({ quality: 40, alphaQuality: 46, effort: 6 })
    .toFile(to)
  console.log(`${to} (${width}x${height})`)
}
