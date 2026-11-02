// The brand's raster files, drawn from the mark in favicon.svg and the site's
// own fonts and colours (Hani's palette): the logo for structured data, the
// mark, the home-screen icon and the share image. Run from web/ when the mark
// or the title changes: `node scripts/brand-images.mjs`.
import { readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'

// Playwright is not a dependency of the site: the one installed with the browser is used.
const { chromium } = createRequire(import.meta.url)('playwright')

const mark = (await readFile('public/favicon.svg', 'utf8')).replace('<svg ', '<svg class="mark" ')
// Inlined: a page set from a string may not load files from disk.
const inline = async (path, type) => `data:${type};base64,${(await readFile(path)).toString('base64')}`
const fonts = await Promise.all(
  [400, 500, 600, 700].map(async (weight) => `@font-face { font-family: Poppins; font-weight: ${weight}; src: url(${await inline(`node_modules/@fontsource/poppins/files/poppins-latin-${weight}-normal.woff2`, 'font/woff2')}); }`),
)
const base = `${fonts.join('\n')}
  * { margin: 0; box-sizing: border-box; }
  body { font-family: Poppins, sans-serif; }`
const croquis = await inline('public/croquis/residencia.webp', 'image/webp')

const pages = [
  {
    file: 'public/construye-logo.png',
    size: { width: 600, height: 600 },
    transparent: true,
    html: `<style>${base}
      body { width: 600px; height: 600px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
      .mark { width: 300px; height: 303px; }
      .name { margin-top: 44px; font-weight: 600; font-size: 54px; letter-spacing: 0.22em; color: #001B4C; padding-left: 0.22em; line-height: 1; }
      .academy { margin-top: 14px; font-weight: 500; font-size: 20px; letter-spacing: 0.62em; color: #006FFE; padding-left: 0.62em; line-height: 1; }
    </style>${mark}<div class="name">CONSTRUYE</div><div class="academy">ACADEMY</div>`,
  },
  {
    file: 'public/construye-mark.png',
    size: { width: 512, height: 512 },
    transparent: true,
    html: `<style>${base} body { width: 512px; height: 512px; display: grid; place-items: center; } .mark { width: 470px; height: 475px; }</style>${mark}`,
  },
  {
    file: 'public/apple-touch-icon.png',
    size: { width: 180, height: 180 },
    html: `<style>${base} body { width: 180px; height: 180px; display: grid; place-items: center; background: #F6F2EA; } .mark { width: 116px; height: 117px; }</style>${mark}`,
  },
  {
    // The first screen of the sales page, as a card: paper, the title, the drawing.
    file: 'public/og-image.jpg',
    size: { width: 1200, height: 630 },
    html: `<style>${base}
      body { width: 1200px; height: 630px; background: #F6F2EA; color: #141A22; position: relative; overflow: hidden; }
      .drawing { position: absolute; right: -40px; bottom: 40px; width: 560px; height: 420px; background: url(${croquis}) right bottom/contain no-repeat; mix-blend-mode: multiply; -webkit-mask-image: linear-gradient(to right, transparent, #000 22%); }
      .logo { position: absolute; left: 72px; top: 60px; display: flex; align-items: center; gap: 14px; }
      .logo .mark { width: 44px; height: 44px; }
      .logo .name { font-weight: 600; font-size: 21px; letter-spacing: 0.22em; color: #001B4C; line-height: 1; }
      .logo .academy { margin-top: 5px; font-weight: 500; font-size: 8.5px; letter-spacing: 0.42em; color: #006FFE; line-height: 1; }
      .eyebrow { position: absolute; left: 72px; top: 170px; font-weight: 500; font-size: 15px; letter-spacing: 0.2em; text-transform: uppercase; color: rgb(20 26 34 / 0.7); display: flex; align-items: center; gap: 16px; }
      .eyebrow::before { content: ''; width: 34px; height: 1px; background: #E8A33D; }
      h1 { position: absolute; left: 72px; top: 215px; width: 560px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.02; }
      h1 .lead { display: block; font-size: 38px; color: rgb(20 26 34 / 0.75); }
      h1 .promise { display: block; margin-top: 10px; font-size: 76px; }
      .rule { position: absolute; left: 72px; bottom: 64px; width: 210px; height: 4px; border-radius: 2px; background: #E8A33D; }
    </style>
    <div class="drawing"></div>
    <div class="logo">${mark}<div><div class="name">CONSTRUYE</div><div class="academy">ACADEMY</div></div></div>
    <div class="eyebrow">El curso para promover vivienda en España</div>
    <h1><span class="lead">Curso de promoción inmobiliaria en España:</span><span class="promise">de la idea a la entrega de llaves</span></h1>
    <div class="rule"></div>`,
  },
]

const browser = await chromium.launch()
for (const { file, size, html, transparent } of pages) {
  const page = await browser.newPage({ viewport: size })
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8"></head><body>${html}</body></html>`, { waitUntil: 'load' })
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: file, omitBackground: !!transparent, type: file.endsWith('.jpg') ? 'jpeg' : 'png', quality: file.endsWith('.jpg') ? 88 : undefined })
  await page.close()
  console.log(file)
}
await browser.close()
