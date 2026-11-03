// Draws the film for one language, frame by frame, and joins it to its sound.
//
//   node herramientas/render.mjs <locale>                 the whole film → salida/<locale>/modulo-1-<locale>.mp4
//   node herramientas/render.mjs <locale> --still 3 41.5  stills at those seconds → salida/<locale>/fotos/
//
// Needs salida/<locale>/linea-de-tiempo.json and mezcla.wav (voz.py, then mezcla.py).
import { spawn } from 'node:child_process'
import { mkdir, readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const { chromium } = createRequire(import.meta.url)('playwright')
const here = dirname(fileURLToPath(import.meta.url))
const root = join(here, '..')
const [locale, mode, ...rest] = process.argv.slice(2)
const FPS = 30
const out = join(root, 'salida', locale)
const script = JSON.parse(await readFile(join(root, 'guion', `${locale}.json`), 'utf8'))
const timeline = JSON.parse(await readFile(join(out, 'linea-de-tiempo.json'), 'utf8'))

const browser = await chromium.launch({ args: ['--force-color-profile=srgb', '--disable-lcd-text', '--allow-file-access-from-files'] })
const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 })
page.on('pageerror', (e) => console.error('page error:', e.message))
page.on('console', (m) => { if (m.type() === 'error') console.error('console:', m.text()) })
await page.goto(pathToFileURL(join(root, 'composicion', 'index.html')).href)
const { duration } = await page.evaluate((args) => window.boot(args), { script, timeline })
const cdp = await page.context().newCDPSession(page)
const shot = async (format = 'png') => Buffer.from((await cdp.send('Page.captureScreenshot', { format, quality: format === 'jpeg' ? 95 : undefined, optimizeForSpeed: true })).data, 'base64')

if (mode === '--still') {
  await mkdir(join(out, 'fotos'), { recursive: true })
  const { writeFile } = await import('node:fs/promises')
  for (const s of rest.map(Number)) {
    await page.evaluate((t) => window.frame(t), s)
    await writeFile(join(out, 'fotos', `${String(s.toFixed(2)).padStart(7, '0')}.png`), await shot())
  }
  console.log('stills', rest.join(' '))
} else {
  const frames = Math.ceil(duration * FPS)
  const file = join(out, `modulo-1-${locale}.mp4`)
  const ff = spawn('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(FPS), '-c:v', 'png', '-i', '-', '-i', join(out, 'mezcla.wav'),
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-tune', 'animation', '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-movflags', '+faststart',
    '-c:a', 'aac', '-b:a', '192k', '-shortest', file], { stdio: ['pipe', 'inherit', 'inherit'] })
  const t0 = Date.now()
  for (let f = 0; f < frames; f++) {
    await page.evaluate((t) => window.frame(t), f / FPS)
    const buf = await shot()
    if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r))
    if (f % 300 === 0) console.log(`${locale} ${f}/${frames} ${((Date.now() - t0) / 1000).toFixed(0)}s`)
  }
  ff.stdin.end()
  await new Promise((r) => ff.on('close', r))
  console.log(file)
}
await browser.close()
