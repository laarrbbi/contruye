import { CanvasTexture, ClampToEdgeWrapping, RepeatWrapping, SRGBColorSpace } from 'three'
import { MARK_FOUNDATIONS, MARK_ROOF } from '@/components/brand/markPaths'
import { PALETTE } from './kit'

/**
 * The world's printed matter, painted on canvases: CONSTRUYE's hoarding round
 * the site (its traced mark and wordmark, the same as the header's), the
 * institutions' signs (in Spanish, as the student will find them in Spain),
 * the site's notices. Nothing is downloaded.
 */

const FONT = '"Inter Variable", Inter, -apple-system, "Segoe UI", sans-serif'

function canvas(w: number, h: number): [HTMLCanvasElement, CanvasRenderingContext2D] {
  const c = document.createElement('canvas')
  c.width = w
  c.height = h
  const ctx = c.getContext('2d')
  if (!ctx) throw new Error('2d context')
  return [c, ctx]
}

function texture(c: HTMLCanvasElement, repeat = false): CanvasTexture {
  const t = new CanvasTexture(c)
  t.colorSpace = SRGBColorSpace
  t.anisotropy = 4
  t.wrapS = repeat ? RepeatWrapping : ClampToEdgeWrapping
  t.wrapT = ClampToEdgeWrapping
  return t
}

/** The mark, `size` px tall, its top-left at (x, y). */
function drawMark(ctx: CanvasRenderingContext2D, x: number, y: number, size: number, color: string) {
  const roof = new Path2D(MARK_ROOF)
  const foundations = new Path2D(MARK_FOUNDATIONS)
  ctx.save()
  ctx.translate(x, y)
  const k = size / 415
  ctx.scale(k, k)
  // The SVG's own transform: translate(0,415) scale(0.1,-0.1).
  ctx.translate(0, 415)
  ctx.scale(0.1, -0.1)
  ctx.fillStyle = color
  ctx.fill(roof)
  ctx.fill(foundations)
  ctx.restore()
}

/** The wordmark: CONSTRUYE above a spaced ACADEMY, as the header sets it. */
function drawWordmark(ctx: CanvasRenderingContext2D, x: number, y: number, height: number, word: string, sub: string) {
  ctx.save()
  ctx.textBaseline = 'alphabetic'
  ctx.fillStyle = word
  ctx.font = `800 ${Math.round(height * 0.62)}px ${FONT}`
  ctx.letterSpacing = `${Math.round(height * 0.16)}px`
  ctx.fillText('CONSTRUYE', x, y + height * 0.62)
  ctx.fillStyle = sub
  ctx.font = `700 ${Math.round(height * 0.24)}px ${FONT}`
  ctx.letterSpacing = `${Math.round(height * 0.18)}px`
  ctx.fillText('ACADEMY', x + 2, y + height * 0.98)
  ctx.restore()
}

/**
 * The site hoarding: night blue, the mark in brand blue, the wordmark in
 * white and a gold rule, repeated along the fence.
 */
export function hoardingTexture(): CanvasTexture {
  const [c, ctx] = canvas(1024, 192)
  ctx.fillStyle = PALETTE.navy
  ctx.fillRect(0, 0, c.width, c.height)
  ctx.fillStyle = PALETTE.gold
  ctx.fillRect(0, c.height - 14, c.width, 6)
  drawMark(ctx, 60, 38, 104, PALETTE.brand)
  drawWordmark(ctx, 190, 44, 104, '#FFFFFF', '#7FB2FF')
  ctx.fillStyle = 'rgba(255,255,255,0.10)'
  ctx.fillRect(c.width - 2, 0, 2, c.height)
  const t = texture(c, true)
  return t
}

/** A plain sign: lines of text centred on a coloured board. */
export function signTexture(lines: string[], options: { bg?: string; fg?: string; width?: number; height?: number; weight?: number; mark?: boolean } = {}): CanvasTexture {
  const width = options.width ?? 1024
  const height = options.height ?? 256
  const [c, ctx] = canvas(width, height)
  ctx.fillStyle = options.bg ?? PALETTE.navy
  ctx.fillRect(0, 0, width, height)
  ctx.fillStyle = options.fg ?? '#FFFFFF'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  const markSpace = options.mark ? height * 0.9 : 0
  if (options.mark) drawMark(ctx, height * 0.12, height * 0.1, height * 0.8, PALETTE.brand)
  const size = Math.min(height / (lines.length + 0.6), (width - markSpace) / Math.max(...lines.map((l) => l.length)) / 0.62)
  ctx.font = `${options.weight ?? 800} ${Math.round(size)}px ${FONT}`
  ctx.letterSpacing = `${Math.round(size * 0.06)}px`
  lines.forEach((line, i) => {
    const y = height / 2 + (i - (lines.length - 1) / 2) * size * 1.15
    ctx.fillText(line, markSpace / 2 + width / 2, y)
  })
  return texture(c)
}

/** A sheet of paper: a title, ruled lines, a stamp. Floats out of the institution that issues it. */
export function paperTexture(stamp: string): CanvasTexture {
  const [c, ctx] = canvas(256, 340)
  ctx.fillStyle = '#FFFFFF'
  ctx.fillRect(0, 0, c.width, c.height)
  ctx.fillStyle = PALETTE.navy
  ctx.fillRect(24, 28, 150, 14)
  ctx.fillStyle = '#C9CED6'
  for (let y = 70; y < 270; y += 18) ctx.fillRect(24, y, y % 36 === 16 ? 150 : 208, 6)
  ctx.strokeStyle = stamp
  ctx.lineWidth = 7
  ctx.beginPath()
  ctx.arc(190, 282, 38, 0, Math.PI * 2)
  ctx.stroke()
  ctx.lineWidth = 3
  ctx.beginPath()
  ctx.arc(190, 282, 28, 0, Math.PI * 2)
  ctx.stroke()
  return texture(c)
}
