// The motion of the Module 1 video. Everything is a function of time: frame(t)
// sets every element for the instant t, so a frame can be drawn in any order and
// always looks the same. Scenes take their beats from the voice: cue.l[i] is when
// line i of the scene starts being spoken, cue.e[i] when it ends.

const W = 1920
const H = 1080
const SEP = { es: '.', en: ',', fr: ' ', ar: ',', ru: ' ', uk: ' ' }
const C = {
  paper: '#F6F2EA', paper2: '#EFE9DE', card: '#FBF8F2', ink: '#141A22', night: '#070F1C', night2: '#0B1A2E', night3: '#12253F', night4: '#1F3A5F',
  amber: '#E8A33D', amberDark: '#8F5A0E', amberLight: '#FBD9A0', graphite: '#5B6470', brick: '#B4472E', line: '#8FA3BF',
}

// ── Time ────────────────────────────────────────────────────────────────────
const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x))
const lerp = (a, b, t) => a + (b - a) * t
const P = (t, at, dur) => (dur <= 0 ? (t >= at ? 1 : 0) : clamp((t - at) / dur))
function bezier(x1, y1, x2, y2) {
  const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx
  const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by
  const sx = (t) => ((ax * t + bx) * t + cx) * t
  const sy = (t) => ((ay * t + by) * t + cy) * t
  const dx = (t) => (3 * ax * t + 2 * bx) * t + cx
  return (x) => {
    if (x <= 0) return 0
    if (x >= 1) return 1
    let t = x
    for (let i = 0; i < 8; i++) {
      const e = sx(t) - x
      if (Math.abs(e) < 1e-6) break
      const d = dx(t)
      if (Math.abs(d) < 1e-6) break
      t -= e / d
    }
    return sy(clamp(t))
  }
}
const E = {
  linear: (t) => t,
  strong: bezier(0.16, 1, 0.3, 1), // Hani's ease-out-strong
  inOut: bezier(0.65, 0, 0.35, 1),
  out: bezier(0.22, 1, 0.36, 1),
  in: bezier(0.55, 0, 1, 0.45),
  draw: bezier(0.45, 0, 0.2, 1),
  back: (t) => { const c1 = 1.4, c3 = c1 + 1; return t <= 0 ? 0 : t >= 1 ? 1 : 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2) },
}
/** A settled spring: 0 → 1 with a little overshoot. */
const spring = (t, zeta = 0.55, omega = 15) => {
  if (t <= 0) return 0
  const wd = omega * Math.sqrt(1 - zeta * zeta)
  return 1 - Math.exp(-zeta * omega * t) * (Math.cos(wd * t) + ((zeta * omega) / wd) * Math.sin(wd * t))
}

// ── DOM ─────────────────────────────────────────────────────────────────────
let RTL = false
let LOCALE = 'es'
const X = (x, w = 0) => (RTL ? W - x - w : x)
function el(tag, cls, parent, style) {
  const n = document.createElement(tag)
  if (cls) n.className = cls
  if (style) Object.assign(n.style, style)
  if (parent) parent.appendChild(n)
  return n
}
const NS = 'http://www.w3.org/2000/svg'
function sv(tag, attrs, parent) {
  const n = document.createElementNS(NS, tag)
  for (const [k, v] of Object.entries(attrs || {})) n.setAttribute(k, v)
  if (parent) parent.appendChild(n)
  return n
}
/** A block of text placed at x (from the start edge), y, width w. */
function block(parent, text, cls, x, y, w, style = {}) {
  const n = el('div', 'abs ' + cls, parent, { left: X(x, w) + 'px', top: y + 'px', width: w + 'px', ...style })
  if (text != null) n.textContent = text
  return n
}
/** Words that come up from under their line, one after another. */
function words(parent, text, cls, x, y, w, style = {}) {
  const box = block(parent, null, cls, x, y, w, style)
  const items = []
  for (const part of String(text).split(/(\s+)/)) {
    if (!part) continue
    if (/^\s+$/.test(part)) { box.appendChild(document.createTextNode(' ')); continue }
    const wrap = el('span', 'w', box)
    const inner = el('span', 'wi', wrap)
    inner.textContent = part
    items.push(inner)
  }
  return {
    box,
    show(t, at, stagger = 0.045, dur = 0.95) {
      items.forEach((n, i) => {
        const p = E.strong(P(t, at + i * stagger, dur))
        n.style.transform = `translate3d(0, ${(1 - p) * 115}%, 0)`
      })
    },
  }
}
/** Fade and rise: returns the eased progress. */
function rise(node, t, at, dur = 0.8, dist = 26, extra = '') {
  const p = E.strong(P(t, at, dur))
  node.style.opacity = p
  node.style.transform = `translate3d(0, ${(1 - p) * dist}px, 0) ${extra}`
  return p
}
function fade(node, t, at, dur = 0.6) {
  const p = E.out(P(t, at, dur))
  node.style.opacity = p
  return p
}
/** A stroke that draws itself (pathLength 1). */
function line(parent, d, attrs = {}) {
  return sv('path', { d, pathLength: 1, class: 'stroke', 'stroke-dasharray': '1 2', 'stroke-dashoffset': 1, ...attrs }, parent)
}
function drawn(path, t, at, dur, ease = E.draw) {
  const p = ease(P(t, at, dur))
  path.setAttribute('stroke-dashoffset', 1 - p)
  path.style.opacity = p > 0 ? 1 : 0
  return p
}
const pts = (list) => list.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ')
const pathOf = (list, close = true) => 'M' + list.map(([x, y]) => `${x.toFixed(1)} ${y.toFixed(1)}`).join(' L') + (close ? ' Z' : '')
function fmt(n) {
  const s = String(Math.round(n))
  return s.replace(/\B(?=(\d{3})+(?!\d))/g, SEP[LOCALE] || '.')
}
/** Isometric projection, in metres, around an origin on screen. */
const iso = (o) => (x, y, z = 0) => [o.x + (x - y) * 0.866 * o.s, o.y + (x + y) * 0.5 * o.s - z * o.s]
function boxFaces(pr, x0, y0, z0, x1, y1, z1) {
  return {
    top: [pr(x0, y0, z1), pr(x1, y0, z1), pr(x1, y1, z1), pr(x0, y1, z1)],
    left: [pr(x0, y1, z0), pr(x1, y1, z0), pr(x1, y1, z1), pr(x0, y1, z1)],
    right: [pr(x1, y0, z0), pr(x1, y1, z0), pr(x1, y1, z1), pr(x1, y0, z1)],
  }
}
/** A full-stage drawing, mirrored for right-to-left so it reads from the start edge. */
function stageSvg(parent) {
  const s = sv('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'abs', style: 'left:0;top:0' }, parent)
  const g = sv('g', RTL ? { transform: `translate(${W},0) scale(-1,1)` } : {}, s)
  return g
}
/** An HTML label pinned to a point of a stage drawing. */
function pin(parent, text, cls, x, y, style = {}, anchor = 'start') {
  const n = el('div', 'abs ' + cls, parent, { top: y + 'px', whiteSpace: 'nowrap', ...style })
  n.textContent = text
  const sx = RTL ? W - x : x
  if ((anchor === 'start') !== RTL) { n.style.left = sx + 'px' } else { n.style.right = W - sx + 'px' }
  if (anchor === 'center') { n.style.left = sx + 'px'; n.style.right = ''; n.style.transform = 'translateX(-50%)' }
  return n
}
const check = (parent, x, y, s = 1, color = C.amber) => line(parent, `M${x - 9 * s} ${y + 1 * s} L${x - 2 * s} ${y + 8 * s} L${x + 11 * s} ${y - 8 * s}`, { stroke: color, 'stroke-width': 3.2 * s })

// ── Scenes ──────────────────────────────────────────────────────────────────
const S = {}

S.open = (root, ui, cue) => {
  const halo1 = el('div', 'halo', root, { width: '1300px', height: '1300px', left: X(980, 1300) + 'px', top: '-420px', background: 'radial-gradient(circle, rgb(59 111 216 / 0.26), transparent 62%)' })
  const halo2 = el('div', 'halo', root, { width: '900px', height: '900px', left: X(1180, 900) + 'px', top: '520px', background: 'radial-gradient(circle, rgb(232 163 61 / 0.18), transparent 62%)' })
  const g = stageSvg(root)
  // A site plan: the street, the neighbours, the plot.
  const o = { x: 1010, y: 210 }
  const T = ([x, y]) => [o.x + x, o.y + y]
  const lot = [[190, 150], [700, 118], [742, 556], [158, 596]].map(T)
  const neigh = [
    [[10, 160], [190, 150], [158, 596], [-24, 606]],
    [[700, 118], [880, 108], [904, 548], [742, 556]],
    [[30, -60], [860, -84], [880, 108], [700, 118], [190, 150], [10, 160]],
  ].map((p) => p.map(T))
  const nLines = neigh.map((p) => line(g, pathOf(p), { stroke: C.line, 'stroke-width': 1.3, opacity: 0.5 }))
  const street = [
    line(g, pathOf([T([-120, 650]), T([980, 610])], false), { stroke: C.line, 'stroke-width': 1.6 }),
    line(g, pathOf([T([-120, 770]), T([980, 730])], false), { stroke: C.line, 'stroke-width': 1.6 }),
    line(g, pathOf([T([-120, 710]), T([980, 670])], false), { stroke: C.line, 'stroke-width': 1.4, 'stroke-dasharray': '0.012 0.012', opacity: 0 }),
  ]
  const clip = sv('clipPath', { id: 'lotclip' }, g)
  sv('polygon', { points: pts(lot) }, clip)
  const hatch = sv('g', { 'clip-path': 'url(#lotclip)', opacity: 0 }, g)
  for (let i = -20; i < 40; i++) sv('line', { x1: o.x + i * 28, y1: o.y + 80, x2: o.x + i * 28 + 520, y2: o.y + 680, stroke: C.amber, 'stroke-width': 1.1, opacity: 0.55 }, hatch)
  const lotFill = sv('polygon', { points: pts(lot), fill: 'rgb(232 163 61 / 0.08)', opacity: 0 }, g)
  const lotLine = line(g, pathOf(lot), { stroke: C.amber, 'stroke-width': 3 })
  // Dimension lines with their ticks.
  const dimA = line(g, pathOf([T([190, 96]), T([700, 64])], false), { stroke: C.amberLight, 'stroke-width': 1.2 })
  const dimB = line(g, pathOf([T([136, 152]), T([104, 598])], false), { stroke: C.amberLight, 'stroke-width': 1.2 })
  const ticks = [[190, 96], [700, 64], [136, 152], [104, 598]].map(([x, y]) => sv('circle', { cx: o.x + x, cy: o.y + y, r: 4, fill: C.amberLight, opacity: 0 }, g))
  const area = pin(root, ui.plotArea || '1.000 m²', 'h2 num', o.x + 450, o.y + 330, { fontSize: '44px', color: C.amberLight, opacity: 0 }, 'center')
  const north = sv('g', { opacity: 0 }, g)
  sv('circle', { cx: o.x + 820, cy: o.y + 330, r: 34, fill: 'none', stroke: C.line, 'stroke-width': 1.3 }, north)
  sv('path', { d: `M${o.x + 820} ${o.y + 292} L${o.x + 832} ${o.y + 340} L${o.x + 820} ${o.y + 332} L${o.x + 808} ${o.y + 340} Z`, fill: C.paper }, north)

  const eb = block(root, ui.eyebrow, 'eyebrow', 140, 318, 700)
  const title = words(root, ui.title, 'h1', 140, 360, 820, { fontSize: RTL ? '190px' : '212px' })
  const sub = block(root, ui.sub, 'body', 144, 610, 760, { fontSize: '29px', color: 'rgb(246 242 234 / 0.72)' })
  const gl = block(root, ui.goalLabel, 'label', 144, 728, 700, { color: C.amber })
  const goal = words(root, ui.goal, 'body', 144, 770, 780, { fontSize: '40px', fontWeight: 500, lineHeight: 1.3 })

  return (t) => {
    halo1.style.transform = `translate(${Math.sin(t * 0.25) * 40}px, ${Math.cos(t * 0.2) * 30}px)`
    halo2.style.transform = `translate(${Math.cos(t * 0.22) * 50}px, ${Math.sin(t * 0.3) * 30}px)`
    nLines.forEach((n, i) => drawn(n, t, 0.2 + i * 0.25, 1.8))
    street.forEach((n, i) => drawn(n, t, 0.1 + i * 0.2, 1.6))
    street[2].style.opacity = fade(street[2], t, 1.0, 0.8) * 0.7
    lotFill.style.opacity = E.out(P(t, 1.6, 1.2))
    drawn(lotLine, t, 0.9, 1.7)
    hatch.style.opacity = E.out(P(t, 2.1, 1.4)) * 0.55
    drawn(dimA, t, 1.9, 0.9)
    drawn(dimB, t, 2.1, 0.9)
    ticks.forEach((n, i) => n.setAttribute('opacity', E.out(P(t, 2.0 + i * 0.12, 0.4))))
    rise(area, t, 2.6, 0.9, 14)
    north.setAttribute('opacity', E.out(P(t, 2.4, 0.8)))
    rise(eb, t, 0.35, 0.9)
    title.show(t, 0.55, 0.09, 1.1)
    rise(sub, t, 1.25, 1.0)
    // The goal takes the floor: the subtitle steps back.
    const g2 = E.inOut(P(t, cue.l[1] - 0.5, 0.9))
    sub.style.opacity = lerp(1, 0.45, g2) * sub.style.opacity
    rise(gl, t, cue.l[1] - 0.3, 0.8, 14)
    goal.show(t, cue.l[1] - 0.15, 0.04, 0.9)
  }
}

S.route = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 1200, { fontSize: '88px' })
  const g = stageSvg(root)
  const xs = [220, 640, 1060, 1480]
  const y = 560
  const base = line(g, `M150 ${y} L1770 ${y}`, { stroke: 'rgb(20 26 34 / 0.25)', 'stroke-width': 2 })
  const prog = sv('path', { d: `M${xs[0]} ${y} L${xs[3]} ${y}`, stroke: C.amber, 'stroke-width': 4, class: 'stroke', pathLength: 1, 'stroke-dasharray': '1 2', 'stroke-dashoffset': 1 }, g)
  const rings = xs.map((x) => sv('circle', { cx: x, cy: y, r: 16, fill: 'none', stroke: C.amber, 'stroke-width': 2, opacity: 0 }, g))
  const dots = xs.map((x) => sv('circle', { cx: x, cy: y, r: 15, fill: C.paper, stroke: C.ink, 'stroke-width': 2 }, g))
  const nums = xs.map((x, i) => pin(root, `1.${i + 1}`, 'label num', x - 4, y + 44, { color: C.amberDark, opacity: 0 }))
  const steps = ui.steps.map((s, i) => words(root, s, 'h2', (RTL ? W - xs[i] : xs[i]) - 4, y + 84, 400, { fontSize: '36px', fontWeight: 600 }))
  // The steps are placed from the start edge: undo the mirroring of `words` for these points.
  steps.forEach((s, i) => { s.box.style.left = (RTL ? W - xs[i] - 400 + 4 : xs[i] - 4) + 'px' })
  const card = el('div', 'abs card', root, { left: X(140, 1480) + 'px', top: '836px', width: '1480px', height: '124px', opacity: 0 })
  const cg = sv('svg', { width: 60, height: 60, class: 'abs', style: `${RTL ? 'right' : 'left'}:34px;top:29px` }, card)
  sv('circle', { cx: 30, cy: 30, r: 26, fill: C.amber }, cg)
  const ck = check(cg, 30, 30, 1.1, C.ink)
  block(card, ui.resultLabel, 'label', 120, 24, 900, { color: 'rgb(20 26 34 / 0.6)', left: RTL ? '' : '120px', right: RTL ? '120px' : '' })
  const res = block(card, ui.result, 'body', 120, 52, 1320, { fontSize: '32px', fontWeight: 500, left: RTL ? '' : '120px', right: RTL ? '120px' : '' })
  return (t) => {
    title.show(t, cue.l[0] - 0.55, 0.07)
    drawn(base, t, cue.l[0] - 0.4, 1.4)
    xs.forEach((x, i) => {
      const at = cue.l[i + 1] - 0.15
      const p = spring(t - at, 0.6, 16)
      dots[i].setAttribute('fill', t >= at ? C.amber : C.paper)
      dots[i].setAttribute('stroke', t >= at ? C.amber : C.ink)
      dots[i].setAttribute('r', t >= at ? 15 * (0.6 + 0.4 * p) + 3 : 15)
      const q = P(t, at, 1.1)
      rings[i].setAttribute('r', 16 + q * 34)
      rings[i].setAttribute('opacity', q > 0 && q < 1 ? (1 - q) * 0.9 : 0)
      rise(nums[i], t, at - 0.1, 0.6, 10)
      steps[i].show(t, at, 0.05)
    })
    const reach = [0, 1, 2, 3].reduce((s, i) => s + E.inOut(P(t, cue.l[i + 1] - 0.4, 0.6)) * (i === 0 ? 0 : 1), 0) / 3
    prog.setAttribute('stroke-dashoffset', 1 - reach)
    const pc = rise(card, t, cue.l[5] - 0.3, 0.9, 30)
    drawn(ck, t, cue.l[5] + 0.2, 0.5)
    res.style.opacity = pc
  }
}

S.product = (root, ui, cue) => {
  const eb = block(root, ui.lesson, 'eyebrow', 140, 300, 600)
  const title = words(root, ui.title, 'h2', 140, 344, 720, { fontSize: '92px' })
  const note = words(root, ui.note, 'body', 144, 640, 660, { fontSize: '36px', fontWeight: 500 })
  const und = el('div', 'abs', root, { left: X(144, 300) + 'px', top: '760px', width: '300px', height: '4px', background: C.amber, transformOrigin: RTL ? 'right' : 'left', transform: 'scaleX(0)', borderRadius: '2px' })
  const card = el('div', 'abs card', root, { left: X(1000, 780) + 'px', top: '170px', width: '780px', height: '740px' })
  const head = el('div', 'abs label', card, { top: '46px', left: '56px', right: '56px', color: 'rgb(20 26 34 / 0.55)', display: 'flex', justifyContent: 'space-between' })
  el('span', '', head).textContent = ui.title
  el('span', 'num', head).textContent = '1/1'
  el('div', 'abs', card, { top: '92px', left: '56px', right: '56px', height: '2px', background: C.ink, opacity: 0.85 })
  const rows = ui.fields.map((f, i) => {
    const r = el('div', 'abs', card, { top: 126 + i * 98 + 'px', left: '56px', right: '56px', height: '86px' })
    const lab = el('div', 'abs', r, { top: '4px', left: 0, right: 0, fontSize: '27px', fontWeight: 500, opacity: 0 })
    lab.textContent = f
    const s = sv('svg', { width: 668, height: 40, class: 'abs', style: 'left:0;top:46px' }, r)
    const g = sv('g', RTL ? { transform: 'translate(668,0) scale(-1,1)' } : {}, s)
    const w = 360 + ((i * 97) % 220)
    const pencil = line(g, `M2 26 C ${w * 0.25} 18, ${w * 0.55} 30, ${w} 22`, { stroke: C.graphite, 'stroke-width': 2.4 })
    const dots = line(g, `M${w + 20} 30 L 660 30`, { stroke: 'rgb(20 26 34 / 0.18)', 'stroke-width': 1.5 })
    const tick = check(g, 640, 14, 0.9)
    return { lab, pencil, dots, tick }
  })
  return (t) => {
    rise(eb, t, cue.l[0] - 0.6, 0.8)
    title.show(t, cue.l[0] - 0.45, 0.07)
    const pc = E.strong(P(t, cue.l[0] - 0.6, 1.3))
    card.style.opacity = pc
    card.style.transform = `translate3d(0, ${(1 - pc) * 90}px, 0) rotate(${lerp(5, -1.6, pc) * (RTL ? -1 : 1)}deg)`
    const span = cue.e[1] - cue.l[1]
    rows.forEach((r, i) => {
      const at = i < 5 ? cue.l[1] + span * (i / 5) * 0.92 : cue.e[1] - 0.2
      fade(r.dots, t, cue.l[0], 0.6)
      r.dots.setAttribute('stroke-dashoffset', 0)
      rise(r.lab, t, at - 0.25, 0.6, 10)
      drawn(r.pencil, t, at, 0.75)
      drawn(r.tick, t, at + 0.6, 0.35)
    })
    note.show(t, cue.l[2] - 0.1, 0.035)
    und.style.transform = `scaleX(${E.strong(P(t, cue.l[2] + 1.2, 1.0))})`
    const lift = E.inOut(P(t, cue.l[2], 1.2))
    card.style.boxShadow = `0 1px 0 rgb(255 255 255 / 0.8) inset, 0 ${30 + lift * 30}px ${60 + lift * 50}px -30px rgb(20 26 34 / ${0.35 + lift * 0.1}), 0 0 0 1px rgb(20 26 34 / 0.06)`
  }
}

S.land = (root, ui, cue) => {
  const pan = el('div', 'abs', root, { inset: 0 })
  const g = stageSvg(pan)
  const pr = iso({ x: 1210, y: 250, s: 6.6 })
  const lots = []
  for (let i = 0; i < 3; i++) for (let j = 0; j < 2; j++) lots.push([i * 25, j * 40, i * 25 + 25, j * 40 + 40, i === 1 && j === 1])
  const lotLines = lots.map(([x0, y0, x1, y1, target]) => line(g, pathOf([pr(x0, y0), pr(x1, y0), pr(x1, y1), pr(x0, y1)]), { stroke: target ? C.amber : C.line, 'stroke-width': target ? 3 : 1.2, opacity: target ? 1 : 0.6 }))
  const targetFill = sv('polygon', { points: pts([pr(25, 40), pr(50, 40), pr(50, 80), pr(25, 80)]), fill: 'rgb(232 163 61 / 0.16)', opacity: 0 }, g)
  // The consolidated town around it: built volumes on the other plots.
  const vols = [[2, 4, 21, 34, 15], [52, 6, 73, 30, 21], [3, 45, 22, 76, 12], [53, 44, 72, 76, 18], [28, 3, 47, 31, 24]].map(([x0, y0, x1, y1, h]) => {
    const f = boxFaces(pr, x0, y0, 0, x1, y1, h)
    const grp = sv('g', { opacity: 0 }, g)
    sv('polygon', { points: pts(f.left), fill: 'rgb(31 58 95 / 0.55)', stroke: C.line, 'stroke-width': 1, 'stroke-opacity': 0.5 }, grp)
    sv('polygon', { points: pts(f.right), fill: 'rgb(18 37 63 / 0.75)', stroke: C.line, 'stroke-width': 1, 'stroke-opacity': 0.5 }, grp)
    sv('polygon', { points: pts(f.top), fill: 'rgb(143 163 191 / 0.22)', stroke: C.line, 'stroke-width': 1, 'stroke-opacity': 0.6 }, grp)
    return { grp, h }
  })
  // The street in front, and the four services that run under it to the plot.
  const curbA = line(g, pathOf([pr(-12, 82), pr(92, 82)], false), { stroke: C.line, 'stroke-width': 1.6 })
  const curbB = line(g, pathOf([pr(-12, 96), pr(92, 96)], false), { stroke: C.line, 'stroke-width': 1.6 })
  const road = sv('polygon', { points: pts([pr(-12, 82), pr(92, 82), pr(92, 96), pr(-12, 96)]), fill: 'rgb(143 163 191 / 0.12)', opacity: 0 }, g)
  const svcY = [85, 88, 91, 94]
  const svcC = [C.paper, '#7FB2E5', '#B9A58C', C.amber]
  const svc = svcY.map((yy, i) => line(g, pathOf([pr(-12, yy), pr(37.5, yy), pr(37.5, 80)], false), { stroke: svcC[i], 'stroke-width': i === 0 ? 2.6 : 2.2, 'stroke-dasharray': i === 3 ? '0.01 0.006' : '1 2' }))
  const align = line(g, pathOf([pr(-12, 80), pr(92, 80)], false), { stroke: C.amberLight, 'stroke-width': 1.6, 'stroke-dasharray': '0.012 0.008' })
  const svcList = el('div', 'abs', root, { left: X(140, 620) + 'px', top: '470px', width: '620px', display: 'flex', flexWrap: 'wrap', gap: '14px' })
  const svcLabels = ui.services.map((s, i) => {
    const c = el('div', 'chip', svcList, { fontSize: '25px', border: `1.5px solid ${svcC[i]}`, color: C.paper, display: 'flex', alignItems: 'center', gap: '12px', opacity: 0 })
    el('span', '', c, { width: '12px', height: '12px', borderRadius: '50%', background: svcC[i], flex: 'none' })
    el('span', '', c).textContent = s
    return c
  })
  const [ax, ay] = pr(92, 80)
  const alignLabel = pin(pan, ui.align, 'label', ax + 24, ay - 12, { color: C.amberLight, opacity: 0 })
  // The land that is not ready: outside the town, no streets, no services.
  const fieldPr = iso({ x: 1210 + 900, y: 250, s: 6.6 })
  const field = line(g, pathOf([fieldPr(0, 0), fieldPr(60, -6), fieldPr(70, 60), fieldPr(4, 70)]), { stroke: C.line, 'stroke-width': 1.6, 'stroke-dasharray': '0.01 0.012' })
  const dots = sv('g', { opacity: 0 }, g)
  for (let i = 0; i < 70; i++) { const [x, y] = fieldPr(6 + ((i * 37) % 58), 6 + ((i * 53) % 58)); sv('circle', { cx: x, cy: y, r: 1.6, fill: C.line }, dots) }
  const [fx, fy] = fieldPr(35, 32)
  const noBox = el('div', 'abs', pan, { top: fy - 70 + 'px', width: '560px', left: (RTL ? W - fx - 280 : fx - 280) + 'px', textAlign: 'center', opacity: 0 })
  const noT = el('div', 'h2', noBox, { fontSize: '54px', color: C.paper, position: 'relative', display: 'inline-block' })
  noT.textContent = ui.no
  const strike = el('div', 'abs', noT, { left: '-8px', right: '-8px', top: '54%', height: '4px', background: C.brick, transformOrigin: RTL ? 'right' : 'left', transform: 'scaleX(0)' })
  const noN = el('div', 'body', noBox, { fontSize: '26px', color: 'rgb(246 242 234 / 0.72)', marginTop: '14px' })
  noN.textContent = ui.noNote

  const title = words(root, ui.title, 'h2', 140, 196, 640, { fontSize: '84px' })
  const yes = words(root, ui.yes, 'h2', 140, 318, 600, { fontSize: '54px', color: C.amberLight })
  const yesNote = el('div', 'abs', root, { left: X(140, 620) + 'px', top: '800px', width: '620px', display: 'flex', gap: '20px', alignItems: 'flex-start', opacity: 0 })
  const ys = sv('svg', { width: 44, height: 44, style: 'flex:none;margin-top:4px' }, yesNote)
  sv('circle', { cx: 22, cy: 22, r: 20, fill: 'none', stroke: C.amber, 'stroke-width': 2 }, ys)
  const yck = check(ys, 22, 22, 0.8)
  el('div', 'body', yesNote, { fontSize: '31px', fontWeight: 500 }).textContent = ui.yesNote

  return (t) => {
    lotLines.forEach((n, i) => drawn(n, t, -0.5 + i * 0.12, 1.2))
    vols.forEach(({ grp }, i) => { const p = E.strong(P(t, 0.0 + i * 0.15, 1.0)); grp.setAttribute('opacity', p); grp.setAttribute('transform', `translate(0 ${(1 - p) * 30})`) })
    drawn(curbA, t, -0.3, 1.2)
    drawn(curbB, t, -0.2, 1.2)
    road.setAttribute('opacity', E.out(P(t, 0.4, 0.8)))
    targetFill.setAttribute('opacity', E.out(P(t, cue.l[0], 0.8)) * (0.75 + 0.25 * Math.sin(t * 3)))
    yes.show(t, cue.l[0], 0.06)
    title.show(t, -0.4, 0.07)
    const span = cue.e[1] - cue.l[1]
    svc.forEach((n, i) => {
      const at = cue.l[1] + span * (0.05 + i * 0.13)
      drawn(n, t, at, 0.9)
      rise(svcLabels[i], t, at + 0.2, 0.6, 10)
    })
    drawn(align, t, cue.l[1] + span * 0.72, 1.0)
    rise(alignLabel, t, cue.l[1] + span * 0.78, 0.6, 10)
    rise(yesNote, t, cue.l[2] - 0.2, 0.8, 16)
    drawn(yck, t, cue.l[2] + 0.3, 0.4)
    // The camera goes out of town to the unready land, and comes back to nothing.
    const out = E.inOut(P(t, cue.l[3] - 0.4, 1.6))
    pan.style.transform = `translate3d(${(RTL ? 1 : -1) * out * 820}px, 0, 0)`
    drawn(field, t, cue.l[3] + 0.4, 1.4)
    dots.setAttribute('opacity', E.out(P(t, cue.l[3] + 0.9, 0.8)))
    rise(noBox, t, cue.l[3] + 0.8, 0.8, 16)
    strike.style.transform = `scaleX(${E.strong(P(t, cue.e[3] - 1.4, 0.7))})`
    const dim = 1 - out
    yesNote.style.opacity = Math.min(yesNote.style.opacity, dim)
    yes.box.style.opacity = dim
    svcList.style.opacity = dim
  }
}

S.channels = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 560, { fontSize: '84px' })
  const cap = block(root, ui.caption, 'label', 144, 420, 440, { color: C.amberDark })
  const g = stageSvg(root)
  const defs = sv('defs', {}, g)
  const grad = sv('linearGradient', { id: 'rent', x1: 0, y1: 0, x2: 0, y2: 1 }, defs)
  sv('stop', { offset: 0, 'stop-color': C.amber }, grad)
  sv('stop', { offset: 1, 'stop-color': 'rgb(20 26 34 / 0.12)' }, grad)
  const bar = sv('rect', { x: 640, y: 270, width: 6, height: 0, rx: 3, fill: 'url(#rent)' }, g)
  const sizes = [58, 50, 44, 40, 37, 34, 32, 30]
  let y = 262
  const rows = ui.items.map((s, i) => {
    const r = el('div', 'abs', root, { left: X(690, 1100) + 'px', top: y + 'px', width: '1100px', display: 'flex', alignItems: 'baseline', gap: '28px', opacity: 0 })
    const n = el('div', 'label num', r, { width: '46px', flex: 'none', color: 'rgb(20 26 34 / 0.45)' })
    n.textContent = String(i + 1).padStart(2, '0')
    const tx = el('div', 'h2', r, { fontSize: sizes[i] + 'px', fontWeight: i < 2 ? 600 : 500 })
    tx.textContent = s
    y += sizes[i] * 1.32 + 14
    return { r, n, tx }
  })
  return (t) => {
    title.show(t, -0.4, 0.07)
    rise(cap, t, 0.3, 0.8, 10)
    bar.setAttribute('height', E.inOut(P(t, 0.1, 1.6)) * 680)
    const span = cue.e[2] - cue.l[2]
    rows.forEach(({ r, n, tx }, i) => {
      const at = i === 0 ? cue.l[0] + 0.6 : i === 1 ? cue.l[1] + 0.2 : cue.l[2] + span * (0.08 + (i - 2) * 0.14)
      rise(r, t, at, 0.8, 18)
      const on = i < 2 ? E.out(P(t, at + 0.2, 0.6)) : 0
      n.style.color = on ? C.amberDark : 'rgb(20 26 34 / 0.45)'
      tx.style.color = i < 2 ? C.ink : `rgb(20 26 34 / ${0.82 - i * 0.05})`
    })
  }
}

S.filter = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 250, 196, 1300, { fontSize: '84px' })
  // A ten-minute dial beside the title, running for the whole scene.
  const dial = sv('svg', { width: 90, height: 90, class: 'abs', style: `${RTL ? 'right' : 'left'}:140px;top:204px` }, root)
  sv('circle', { cx: 45, cy: 45, r: 38, fill: 'none', stroke: 'rgb(20 26 34 / 0.15)', 'stroke-width': 6 }, dial)
  const arc = sv('circle', { cx: 45, cy: 45, r: 38, fill: 'none', stroke: C.amber, 'stroke-width': 6, 'stroke-linecap': 'round', pathLength: 1, 'stroke-dasharray': '1 2', 'stroke-dashoffset': 1, transform: 'rotate(-90 45 45)' }, dial)
  const hand = sv('line', { x1: 45, y1: 45, x2: 45, y2: 17, stroke: C.ink, 'stroke-width': 4, 'stroke-linecap': 'round' }, dial)
  sv('rect', { x: 39, y: -6, width: 12, height: 8, rx: 2, fill: C.ink }, dial)

  const row = el('div', 'abs', root, { left: X(140, 1640) + 'px', top: '330px', width: '1640px', display: 'flex', alignItems: 'center', gap: '28px' })
  const chipA = el('div', 'chip', row, { background: C.ink, color: C.paper, fontSize: '32px', opacity: 0 })
  chipA.textContent = ui.buildable
  const times = el('div', 'h2', row, { fontSize: '56px', opacity: 0, color: C.amberDark })
  times.textContent = '×'
  const chipB = el('div', 'chip', row, { background: C.ink, color: C.paper, fontSize: '32px', opacity: 0 })
  chipB.textContent = ui.price
  const eq = el('div', 'h2', row, { fontSize: '56px', opacity: 0, color: C.amberDark })
  eq.textContent = '='
  const rev = el('div', 'h2', row, { fontSize: '44px', opacity: 0 })
  rev.textContent = ui.revenue

  const barX = 140, barW = 1640, barY = 590, barH = 100
  const bar = el('div', 'abs', root, { left: X(barX, barW) + 'px', top: barY + 'px', width: barW + 'px', height: barH + 'px', borderRadius: '16px', border: `2px solid ${C.ink}`, overflow: 'hidden', opacity: 0 })
  const revFill = el('div', 'abs', bar, { inset: 0, background: 'repeating-linear-gradient(135deg, rgb(20 26 34 / 0.06) 0 2px, transparent 2px 14px)', transformOrigin: RTL ? 'right' : 'left', transform: 'scaleX(0)' })
  const ask = el('div', 'abs', bar, { top: 0, bottom: 0, [RTL ? 'right' : 'left']: 0, width: '0px', background: C.ink })
  const askLabel = el('div', 'abs label', root, { top: barY + barH + 22 + 'px', left: X(barX, 600) + 'px', width: '600px', opacity: 0 })
  askLabel.textContent = ui.asking
  const mark = el('div', 'abs', root, { top: barY - 34 + 'px', left: (RTL ? W - barX - barW * 0.3 : barX + barW * 0.3) - 1.5 + 'px', width: '3px', height: barH + 80 + 'px', background: C.amber, transformOrigin: 'top', transform: 'scaleY(0)' })
  const markLabel = el('div', 'abs h2 num', root, { top: barY - 100 + 'px', left: (RTL ? W - barX - barW * 0.3 : barX + barW * 0.3) - 100 + 'px', width: '200px', textAlign: 'center', fontSize: '48px', color: C.amberDark, opacity: 0 })
  markLabel.textContent = '30 %'
  const stamp = el('div', 'abs', root, { top: '790px', left: X(1120, 660) + 'px', width: '660px', textAlign: 'center', opacity: 0 })
  const st = el('div', 'h2', stamp, { display: 'inline-block', fontSize: '64px', color: C.brick, border: `4px solid ${C.brick}`, borderRadius: '14px', padding: '6px 30px 10px' })
  st.textContent = ui.verdict
  const exc = el('div', 'body', stamp, { fontSize: '30px', marginTop: '14px', color: C.graphite })
  exc.textContent = ui.exception
  return (t) => {
    title.show(t, -0.4, 0.07)
    const run = P(t, 0, cue.d)
    arc.setAttribute('stroke-dashoffset', 1 - run)
    hand.setAttribute('transform', `rotate(${run * 360} 45 45)`)
    fade(dial, t, -0.2, 0.6)
    const span = cue.e[1] - cue.l[1]
    rise(chipA, t, cue.l[1] - 0.1, 0.6, 14)
    rise(times, t, cue.l[1] + span * 0.12, 0.5, 14)
    rise(chipB, t, cue.l[1] + span * 0.18, 0.6, 14)
    rise(eq, t, cue.l[1] + span * 0.36, 0.5, 14)
    rise(rev, t, cue.l[1] + span * 0.42, 0.6, 14)
    fade(bar, t, cue.l[1] + span * 0.3, 0.5)
    revFill.style.transform = `scaleX(${E.inOut(P(t, cue.l[1] + span * 0.36, 1.0))})`
    const s1 = E.inOut(P(t, cue.l[1] + span * 0.7, 0.9)) * 0.22
    const span2 = cue.e[2] - cue.l[2]
    const s2 = E.inOut(P(t, cue.l[2] + span2 * 0.25, 1.1)) * 0.19
    ask.style.width = (s1 + s2) * barW + 'px'
    const over = P(t, cue.l[2] + span2 * 0.42, 0.3)
    ask.style.background = over > 0 ? C.brick : C.ink
    rise(askLabel, t, cue.l[1] + span * 0.72, 0.5, 10)
    mark.style.transform = `scaleY(${E.strong(P(t, cue.l[2] - 0.1, 0.8))})`
    rise(markLabel, t, cue.l[2] + 0.1, 0.6, 10)
    const sp = spring(t - (cue.l[2] + span2 * 0.55), 0.5, 13)
    stamp.style.opacity = clamp(sp * 3)
    st.style.transform = `scale(${lerp(1.6, 1, clamp(sp))}) rotate(${-6 * (RTL ? -1 : 1)}deg)`
    rise(exc, t, cue.l[2] + span2 * 0.8, 0.6, 8)
  }
}

S.urban = (root, ui, cue) => {
  const eb = block(root, ui.lesson, 'eyebrow', 140, 200, 600)
  const title = words(root, ui.title, 'h2', 140, 240, 660, { fontSize: '84px' })
  const rules = el('div', 'abs card', root, { left: X(140, 640) + 'px', top: '470px', width: '640px', padding: '34px 40px', opacity: 0 })
  const rr = [0, 2].map((k) => {
    const r = el('div', '', rules, { padding: k ? '26px 0 0' : '0 0 26px', borderBottom: k ? 'none' : '1px solid rgb(246 242 234 / 0.14)', opacity: 0 })
    el('div', 'label', r, { color: C.amber }).textContent = ui.rules[k]
    el('div', 'h2', r, { fontSize: '36px', marginTop: '10px' }).textContent = ui.rules[k + 1]
    return r
  })
  const g = stageSvg(root)
  const pr = iso({ x: 1270, y: 410, s: 10.5 })
  const plot = [pr(0, 0), pr(25, 0), pr(25, 40), pr(0, 40)]
  const slabF = boxFaces(pr, 0, 0, -1.2, 25, 40, 0)
  const slab = sv('g', { opacity: 0 }, g)
  sv('polygon', { points: pts(slabF.left), fill: '#1F3A5F' }, slab)
  sv('polygon', { points: pts(slabF.right), fill: '#12253F' }, slab)
  sv('polygon', { points: pts(plot), fill: 'rgb(232 163 61 / 0.12)' }, slab)
  const plotLine = line(g, pathOf(plot), { stroke: C.amber, 'stroke-width': 2.6 })
  const plotLabel = pin(root, ui.plot, 'h2 num', ...pr(12.5, 50), { fontSize: '30px', color: C.amberLight, opacity: 0 }, 'center')
  const coef = pin(root, ui.coef, 'chip num', ...pr(30, 34), { fontSize: '30px', background: C.amber, color: C.ink, opacity: 0 })
  // Five floors (PB+4), 400 m² each: 2,000 m² of floor area on 1,000 m² of land.
  const floors = [0, 1, 2, 3, 4].map((k) => {
    const f = boxFaces(pr, 4.5, 7.5, k * 3.1, 20.5, 32.5, k * 3.1 + 2.9)
    const grp = sv('g', { opacity: 0 }, g)
    sv('polygon', { points: pts(f.left), fill: '#E9E4DA', stroke: '#C9C2B4', 'stroke-width': 1 }, grp)
    sv('polygon', { points: pts(f.right), fill: '#CFC8BB', stroke: '#B9B1A2', 'stroke-width': 1 }, grp)
    sv('polygon', { points: pts(f.top), fill: '#F6F2EA', stroke: '#D9D2C4', 'stroke-width': 1 }, grp)
    // Window bands.
    for (let w = 0; w < 6; w++) {
      const a = 5.6 + w * 2.55
      sv('polygon', { points: pts([pr(a, 32.5, k * 3.1 + 0.9), pr(a + 1.6, 32.5, k * 3.1 + 0.9), pr(a + 1.6, 32.5, k * 3.1 + 2.3), pr(a, 32.5, k * 3.1 + 2.3)]), fill: k === 0 ? '#E8A33D' : '#1F3A5F', opacity: k === 0 ? 0.85 : 0.7 }, grp)
    }
    for (let w = 0; w < 9; w++) {
      const a = 8.4 + w * 2.6
      sv('polygon', { points: pts([pr(20.5, a, k * 3.1 + 0.9), pr(20.5, a + 1.5, k * 3.1 + 0.9), pr(20.5, a + 1.5, k * 3.1 + 2.3), pr(20.5, a, k * 3.1 + 2.3)]), fill: '#12253F', opacity: 0.6 }, grp)
    }
    return grp
  })
  const area = pin(root, '0', 'h2 num', ...pr(2, 6, 26), { fontSize: '64px', color: C.paper, opacity: 0 }, 'end')
  const areaU = pin(root, ui.result, 'label', ...pr(2, 6, 19.4), { color: C.amberLight, opacity: 0 }, 'end')
  // The parameters, drawn on the building.
  const hgt = line(g, pathOf([pr(26.5, 32.5, 0), pr(26.5, 32.5, 15.4)], false), { stroke: C.amberLight, 'stroke-width': 2 })
  const hgtT = [pr(25.5, 32.5, 0), pr(25.5, 32.5, 15.4)].map(([x, y]) => sv('line', { x1: x - 8, y1: y, x2: x + 24, y2: y, stroke: C.amberLight, 'stroke-width': 2, opacity: 0 }, g))
  const occ = sv('polygon', { points: pts([pr(4.5, 7.5), pr(20.5, 7.5), pr(20.5, 32.5), pr(4.5, 32.5)]), fill: 'none', stroke: C.amber, 'stroke-width': 3, 'stroke-dasharray': '10 8', opacity: 0 }, g)
  const setA = line(g, pathOf([pr(12.5, 40), pr(12.5, 32.5)], false), { stroke: C.amberLight, 'stroke-width': 2 })
  const setB = line(g, pathOf([pr(25, 20), pr(20.5, 20)], false), { stroke: C.amberLight, 'stroke-width': 2 })
  const park = pin(root, 'P', 'h2', ...pr(-3, 30, -4), { fontSize: '34px', width: '54px', height: '54px', lineHeight: '54px', textAlign: 'center', borderRadius: '12px', background: '#7FB2E5', color: C.night, opacity: 0 })
  const params = el('div', 'abs', root, { left: X(140, 680) + 'px', top: '736px', width: '680px', display: 'flex', flexWrap: 'wrap', gap: '14px' })
  const chips = ui.params.map((p, i) => {
    const c = el('div', 'chip', params, { fontSize: '25px', border: `1.5px solid ${C.amberLight}`, color: C.paper, opacity: 0 })
    c.textContent = p + (i === 0 ? ` · ${ui.heightTag || 'PB+4'}` : '')
    return c
  })
  return (t) => {
    rise(eb, t, -0.4, 0.8)
    title.show(t, -0.3, 0.07)
    rise(rules, t, cue.l[1] - 0.3, 0.8, 20)
    const s1 = cue.e[1] - cue.l[1]
    rise(rr[0], t, cue.l[1], 0.7, 12)
    rise(rr[1], t, cue.l[1] + s1 * 0.5, 0.7, 12)
    const s2 = cue.e[2] - cue.l[2]
    drawn(plotLine, t, cue.l[0], 1.3)
    slab.setAttribute('opacity', E.out(P(t, cue.l[0] + 0.6, 0.8)))
    rise(plotLabel, t, cue.l[2] + s2 * 0.25, 0.7, 12)
    const cp = spring(t - (cue.l[2] + s2 * 0.45), 0.5, 14)
    coef.style.opacity = clamp(cp * 2)
    coef.style.transform = `scale(${lerp(0.6, 1, clamp(cp))})`
    let built = 0
    floors.forEach((grp, k) => {
      const at = cue.l[2] + s2 * 0.58 + k * 0.32
      const p = E.back(P(t, at, 0.55))
      grp.setAttribute('opacity', clamp(P(t, at, 0.2)))
      grp.setAttribute('transform', `translate(0 ${(1 - p) * -60})`)
      built += E.out(P(t, at, 0.4))
    })
    rise(area, t, cue.l[2] + s2 * 0.56, 0.6, 10)
    rise(areaU, t, cue.l[2] + s2 * 0.6, 0.6, 10)
    area.textContent = fmt(Math.round(built * 400 / 10) * 10)
    const s3 = cue.e[3] - cue.l[3]
    const ats = [0.02, 0.3, 0.5, 0.72].map((f) => cue.l[3] + s3 * f)
    drawn(hgt, t, ats[0], 0.7)
    hgtT.forEach((n) => n.setAttribute('opacity', E.out(P(t, ats[0] + 0.3, 0.3))))
    occ.setAttribute('opacity', E.out(P(t, ats[1], 0.4)) * (0.65 + 0.35 * Math.sin(t * 4)))
    drawn(setA, t, ats[2], 0.6)
    drawn(setB, t, ats[2] + 0.2, 0.6)
    const pp = spring(t - ats[3], 0.5, 14)
    park.style.opacity = clamp(pp * 2)
    park.style.transform = `scale(${lerp(0.5, 1, clamp(pp))})`
    chips.forEach((n, i) => {
      rise(n, t, ats[i], 0.6, 10)
      const on = P(t, ats[i], 0.3) * (1 - P(t, (ats[i + 1] ?? cue.e[3] + 0.4), 0.4))
      n.style.background = `rgb(232 163 61 / ${on * 0.9})`
      n.style.color = on > 0.5 ? C.ink : C.paper
    })
  }
}

S.practice = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 1200, { fontSize: '84px' })
  const xs = [140, 700, 1260]
  const cards = ui.cards.map(([head, desc], i) => {
    const c = el('div', 'abs card', root, { left: X(xs[i], 520) + 'px', top: '340px', width: '520px', height: '440px', opacity: 0 })
    const s = sv('svg', { width: 200, height: 180, class: 'abs', style: `${RTL ? 'right' : 'left'}:44px;top:40px` }, c)
    const st = { stroke: C.ink, 'stroke-width': 2.4 }
    const strokes = []
    if (i === 0) {
      strokes.push(line(s, 'M10 20 L170 10 L190 150 L20 166 Z', st))
      strokes.push(line(s, 'M20 70 C 70 60, 110 90, 182 80', { ...st, 'stroke-width': 1.6 }))
      strokes.push(line(s, 'M90 15 C 96 70, 84 110, 100 160', { ...st, 'stroke-width': 1.6 }))
      strokes.push(line(s, 'M104 86 L178 80 L184 148 L108 156 Z', { stroke: C.amber, 'stroke-width': 3 }))
    } else if (i === 1) {
      const p = iso({ x: 100, y: 50, s: 5.2 })
      const f = boxFaces(p, 0, 0, 0, 12, 12, 16)
      strokes.push(line(s, pathOf(f.left), st), line(s, pathOf(f.right), st), line(s, pathOf(f.top), { stroke: C.amber, 'stroke-width': 3 }))
      strokes.push(line(s, pathOf([p(14, 12, 0), p(14, 12, 16)], false), { ...st, 'stroke-width': 1.4 }))
    } else {
      strokes.push(line(s, 'M30 10 L130 10 L160 40 L160 170 L30 170 Z', st))
      for (let k = 0; k < 4; k++) strokes.push(line(s, `M52 ${54 + k * 22} L ${136 - (k % 2) * 30} ${54 + k * 22}`, { ...st, 'stroke-width': 1.6 }))
      strokes.push(line(s, 'M150 128 m -28 0 a 28 28 0 1 0 56 0 a 28 28 0 1 0 -56 0', { stroke: C.amber, 'stroke-width': 3 }))
    }
    el('div', 'abs h2', c, { top: '250px', left: '44px', right: '44px', fontSize: '38px' }).textContent = head
    el('div', 'abs body', c, { top: '312px', left: '44px', right: '44px', fontSize: '27px', color: C.graphite }).textContent = desc
    return { c, strokes }
  })
  const warn = el('div', 'abs', root, { left: X(140, 1640) + 'px', top: '840px', width: '1640px', height: '100px', border: `2px solid ${C.amber}`, borderRadius: '18px', display: 'flex', alignItems: 'center', gap: '26px', padding: '0 34px', background: 'rgb(232 163 61 / 0.08)', opacity: 0 })
  const ws = sv('svg', { width: 52, height: 48, style: 'flex:none' }, warn)
  const tri = line(ws, 'M26 4 L50 44 L2 44 Z', { stroke: C.amberDark, 'stroke-width': 3 })
  sv('rect', { x: 24, y: 17, width: 4, height: 14, rx: 2, fill: C.amberDark }, ws)
  sv('circle', { cx: 26, cy: 37, r: 2.6, fill: C.amberDark }, ws)
  el('div', 'h2', warn, { fontSize: '33px', fontWeight: 500 }).textContent = ui.warn
  return (t) => {
    title.show(t, -0.4, 0.07)
    const span = cue.e[0] - cue.l[0]
    cards.forEach(({ c, strokes }, i) => {
      const at = cue.l[0] + span * [0.05, 0.4, 0.7][i]
      const p = rise(c, t, at - 0.3, 0.9, 40)
      strokes.forEach((s, k) => drawn(s, t, at + k * 0.14, 0.9))
      c.style.transform += ` rotate(${(1 - p) * (i - 1) * 2}deg)`
    })
    rise(warn, t, cue.l[1] - 0.2, 0.8, 18)
    drawn(tri, t, cue.l[1] + 0.1, 0.6)
  }
}

S.dd = (root, ui, cue) => {
  const eb = block(root, ui.lesson, 'eyebrow', 140, 250, 600)
  const title = words(root, ui.title, 'h2', 140, 294, 700, { fontSize: '100px' })
  const docs = ui.docs.map((d, i) => {
    const r = el('div', 'abs', root, { left: X(140, 720) + 'px', top: 470 + i * 96 + 'px', width: '720px', display: 'flex', alignItems: 'center', gap: '24px', opacity: 0 })
    const s = sv('svg', { width: 44, height: 54, style: 'flex:none' }, r)
    sv('path', { d: 'M4 2 L30 2 L42 14 L42 52 L4 52 Z', fill: C.card, stroke: C.ink, 'stroke-width': 2.2, 'stroke-linejoin': 'round' }, s)
    for (let k = 0; k < 3; k++) sv('line', { x1: 11, y1: 22 + k * 9, x2: 34, y2: 22 + k * 9, stroke: C.graphite, 'stroke-width': 1.8 }, s)
    el('div', 'h2', r, { fontSize: '34px', fontWeight: 500 }).textContent = d
    return r
  })
  const fix = block(root, ui.fix, 'body', 144, 700, 680, { fontSize: '28px', color: C.graphite, opacity: 0 })
  // Three plans of the same plot: what the Registry says, what the Cadastre says, what is on the ground.
  const stack = el('div', 'abs', root, { left: X(1010, 760) + 'px', top: '150px', width: '760px', height: '780px', perspective: '1800px' })
  const base = [[90, 70], [560, 40], [610, 500], [70, 540]]
  const off = [[[0, -30], [40, 10], [-20, 30], [10, 0]], [[20, 10], [-10, -20], [30, 0], [-30, 20]], [[0, 0], [0, 0], [0, 0], [0, 0]]]
  const colors = [C.ink, '#3F6FB5', C.amber]
  const legend = el('div', 'abs', root, { left: X(1060, 700) + 'px', top: '830px', width: '700px', display: 'flex', gap: '40px', justifyContent: 'center' })
  const layers = ui.layers.map((name, i) => {
    const d = el('div', 'abs', stack, { left: '40px', top: '120px', width: '680px', height: '600px', transformStyle: 'preserve-3d' })
    const s = sv('svg', { width: 680, height: 600, class: 'abs', style: 'left:0;top:0' }, d)
    const poly = sv('polygon', { points: pts(base), fill: i === 2 ? 'rgb(232 163 61 / 0.14)' : 'rgb(246 242 234 / 0.72)', stroke: colors[i], 'stroke-width': i === 2 ? 4 : 3, 'stroke-dasharray': i === 0 ? '14 10' : 'none' }, s)
    const lab = el('div', 'label', legend, { color: colors[i], display: 'flex', alignItems: 'center', gap: '12px', opacity: 0 })
    el('span', '', lab, { width: '34px', height: '0', borderTop: `3px ${i === 0 ? 'dashed' : 'solid'} ${colors[i]}`, display: 'inline-block' })
    el('span', '', lab).textContent = name
    return { d, poly, lab }
  })
  const badge = el('div', 'abs', root, { left: X(1180, 420) + 'px', top: '820px', width: '420px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '18px', opacity: 0 })
  const bs = sv('svg', { width: 56, height: 56 }, badge)
  sv('circle', { cx: 28, cy: 28, r: 26, fill: C.amber }, bs)
  const bck = check(bs, 28, 28, 1.05, C.ink)
  el('div', 'h2', badge, { fontSize: '44px' }).textContent = ui.match
  return (t) => {
    rise(eb, t, -0.4, 0.8)
    title.show(t, cue.l[0] - 0.4, 0.08)
    const s1 = cue.e[1] - cue.l[1]
    docs.forEach((r, i) => rise(r, t, cue.l[1] + s1 * [0.05, 0.42][i], 0.7, 14))
    const s2 = cue.e[2] - cue.l[2]
    const join = E.inOut(P(t, cue.l[2] + s2 * 0.1, 1.8))
    const enter = E.strong(P(t, cue.l[0] - 0.2, 1.4))
    layers.forEach(({ d, poly, lab }, i) => {
      const lift = (2 - i) * 130 * (1 - join)
      const spread = (1 - enter) * 220
      d.style.transform = `rotateX(58deg) rotateZ(${-32 + (RTL ? 64 : 0)}deg) translateZ(${lift + spread * (2 - i) * 0.5}px)`
      d.style.opacity = clamp(P(t, cue.l[0] - 0.2 + i * 0.25, 0.6))
      const shape = base.map(([x, y], k) => [x + off[i][k][0] * (1 - join), y + off[i][k][1] * (1 - join)])
      poly.setAttribute('points', pts(shape))
      lab.style.opacity = clamp(P(t, cue.l[0] + 0.3 + i * 0.25, 0.6)) * (1 - join)
      if (i < 2) poly.style.opacity = 1 - E.out(P(t, cue.l[2] + s2 * 0.1 + 1.6, 0.5)) * 0.85
    })
    const bp = spring(t - (cue.l[2] + s2 * 0.1 + 1.7), 0.55, 13)
    badge.style.opacity = clamp(bp * 2)
    badge.style.transform = `scale(${lerp(0.7, 1, clamp(bp))})`
    drawn(bck, t, cue.l[2] + s2 * 0.1 + 1.9, 0.4)
    rise(fix, t, cue.l[2] + s2 * 0.58, 0.8, 12)
  }
}

S.checks = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 1200, { fontSize: '84px' })
  const xs = [140, 700, 1260]
  const icons = [
    (s) => [line(s, 'M8 40 L24 8 L40 40 Z', { stroke: C.amber, 'stroke-width': 2.6 }), line(s, 'M18 28 L30 28 M24 20 L24 34', { stroke: C.paper, 'stroke-width': 2.2 })],
    (s) => [line(s, 'M14 18 m-8 0 a8 8 0 1 0 16 0 a8 8 0 1 0 -16 0 M2 44 C 4 32, 24 32, 26 44', { stroke: C.paper, 'stroke-width': 2.4 }), line(s, 'M30 20 m-6 0 a6 6 0 1 0 12 0 a6 6 0 1 0 -12 0 M28 44 C 30 34, 44 34, 46 44', { stroke: C.paper, 'stroke-width': 2 })],
    (s) => [line(s, 'M2 14 L46 14 M2 26 L46 26 M2 38 L46 38', { stroke: C.paper, 'stroke-width': 2.2 }), line(s, 'M8 14 L14 26 L20 14 L26 26 L32 14 L38 26', { stroke: C.amber, 'stroke-width': 2 })],
    (s) => [line(s, 'M6 44 L6 16 L24 4 L42 16 L42 44 Z', { stroke: C.paper, 'stroke-width': 2.4 }), line(s, 'M14 26 L34 36 M34 26 L14 36', { stroke: C.amber, 'stroke-width': 2.4 })],
    (s) => [line(s, 'M2 40 C 14 24, 34 24, 46 40', { stroke: C.paper, 'stroke-width': 2.4 }), line(s, 'M2 10 L46 10', { stroke: C.amber, 'stroke-width': 2.4, 'stroke-dasharray': '0.06 0.05' })],
    (s) => [line(s, 'M24 2 L10 26 L24 26 L18 46 L38 18 L24 18 L30 2 Z', { stroke: C.amber, 'stroke-width': 2.6 })],
  ]
  const chips = ui.chips.map((txt, i) => {
    const c = el('div', 'abs card', root, { left: X(xs[i % 3], 520) + 'px', top: (i < 3 ? 360 : 560) + 'px', width: '520px', height: '160px', display: 'flex', alignItems: 'center', gap: '24px', padding: '0 34px', opacity: 0 })
    const s = sv('svg', { width: 48, height: 48, style: 'flex:none' }, c)
    const strokes = icons[i](s)
    el('div', 'h2', c, { fontSize: '29px', fontWeight: 500, lineHeight: 1.25 }).textContent = txt
    return { c, strokes }
  })
  const cost = block(root, ui.cost, 'h2', 1260, 760, 560, { fontSize: '32px', color: C.amberLight, opacity: 0 })
  const lead = el('div', 'abs', root, { left: X(1290, 2) + 'px', top: '722px', width: '2px', height: '30px', background: C.amber, transformOrigin: 'top', transform: 'scaleY(0)' })
  return (t) => {
    title.show(t, -0.4, 0.07)
    const s0 = cue.e[0] - cue.l[0]
    const s1 = cue.e[1] - cue.l[1]
    const ats = [cue.l[0] + 0.1, cue.l[0] + s0 * 0.5, cue.l[0] + s0 * 0.68, cue.l[1] - 0.3, cue.l[1] - 0.15, cue.l[1] + s1 * 0.08]
    chips.forEach(({ c, strokes }, i) => {
      const p = spring(t - ats[i], 0.62, 14)
      c.style.opacity = clamp(p * 1.6)
      c.style.transform = `translate3d(0, ${(1 - clamp(p)) * 30}px, 0) scale(${lerp(0.94, 1, clamp(p))})`
      strokes.forEach((s, k) => drawn(s, t, ats[i] + 0.1 + k * 0.15, 0.7))
      if (i === 5) c.style.boxShadow = `0 0 0 ${2 * E.out(P(t, ats[i] + 0.4, 0.5))}px ${C.amber}, 0 40px 80px -40px rgb(0 0 0 / 0.6)`
    })
    lead.style.transform = `scaleY(${E.strong(P(t, cue.l[1] + s1 * 0.35, 0.5))})`
    rise(cost, t, cue.l[1] + s1 * 0.42, 0.8, 12)
  }
}

S.forms = (root, ui, cue) => {
  const eb = block(root, ui.lesson, 'eyebrow', 140, 180, 600)
  const title = words(root, ui.title, 'h2', 140, 222, 1200, { fontSize: '84px' })
  const cx = [390, 690, 990, 1290, 1590]
  const rot = [-7, -3.5, 0, 3.5, 7]
  const cards = ui.forms.map((f, i) => {
    const c = el('div', 'abs card', root, { left: (RTL ? W - cx[i] : cx[i]) - 150 + 'px', top: '470px', width: '300px', height: '400px', opacity: 0, transformOrigin: '50% 120%' })
    el('div', 'abs label num', c, { top: '34px', left: '32px', right: '32px', color: C.amberDark }).textContent = String(i + 1).padStart(2, '0')
    const tx = el('div', 'abs h2', c, { bottom: '34px', left: '32px', right: '32px', fontSize: '32px', lineHeight: 1.12 })
    tx.textContent = f
    let notes = null
    if (i === 1) {
      notes = el('div', 'abs', c, { top: '84px', left: '32px', right: '32px', opacity: 0 })
      ui.optionNote.forEach((n) => { el('div', 'body', notes, { fontSize: '22px', fontWeight: 500, padding: '9px 0', borderBottom: '1px solid rgb(20 26 34 / 0.12)' }).textContent = n })
    }
    return { c, notes }
  })
  return (t) => {
    rise(eb, t, -0.4, 0.8)
    title.show(t, cue.l[0] - 0.35, 0.07)
    const s = cue.e[1] - cue.l[1]
    const ats = [0.0, 0.13, 0.66, 0.76, 0.86].map((f) => cue.l[1] + s * f - 0.25)
    cards.forEach(({ c, notes }, i) => {
      const p = spring(t - ats[i], 0.62, 12)
      const lift = i === 1 ? E.inOut(P(t, ats[1] + 0.5, 0.8)) : 0
      const settle = i === 1 ? 0 : E.inOut(P(t, ats[1] + 0.5, 0.8)) * 0.4
      c.style.opacity = clamp(p * 1.8) * (1 - settle * 0.6)
      const r = (RTL ? -1 : 1) * rot[i] * (1 - lift)
      c.style.transform = `translate3d(0, ${(1 - clamp(p)) * 120 - lift * 56}px, 0) rotate(${r + (1 - clamp(p)) * 8}deg) scale(${1 + lift * 0.14})`
      c.style.zIndex = i === 1 ? 5 : 1
      if (i === 1) {
        c.style.boxShadow = `0 0 0 ${lift * 3}px ${C.amber}, 0 ${30 + lift * 40}px ${60 + lift * 60}px -30px rgb(20 26 34 / ${0.35 + lift * 0.15})`
        c.style.height = 400 + lift * 60 + 'px'
        notes.style.opacity = E.out(P(t, ats[1] + 0.9, 0.6))
      }
    })
  }
}

S.tax = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 1200, { fontSize: '84px' })
  const colX = [140, 960, 1440]
  const colW = [780, 440, 340]
  const heads = ui.head.map((h, i) => block(root, h, 'label', colX[i], 380, colW[i], { color: 'rgb(20 26 34 / 0.55)', opacity: 0 }))
  const g = stageSvg(root)
  const rules = [430, 590, 750].map((y) => line(g, `M140 ${y} L1780 ${y}`, { stroke: 'rgb(20 26 34 / 0.22)', 'stroke-width': 1.6 }))
  const rows = ui.rows.map((r, k) => {
    const y = 470 + k * 160
    const who = block(root, r[0], 'h2', colX[0], y, colW[0], { fontSize: '38px', fontWeight: 500, opacity: 0 })
    const tx = el('div', 'abs', root, { left: X(colX[1], colW[1]) + 'px', top: y - 6 + 'px', width: colW[1] + 'px', opacity: 0 })
    el('span', 'chip num', tx, { background: k === 0 ? C.amber : C.ink, color: k === 0 ? C.ink : C.paper, fontSize: '30px', display: 'inline-block' }).textContent = r[1]
    const rec = el('div', 'abs', root, { left: X(colX[2], colW[2]) + 'px', top: y - 4 + 'px', width: colW[2] + 'px', display: 'flex', alignItems: 'center', gap: '16px', opacity: 0 })
    const s = sv('svg', { width: 50, height: 50, style: 'flex:none' }, rec)
    sv('circle', { cx: 25, cy: 25, r: 23, fill: 'none', stroke: k === 0 ? C.amberDark : C.brick, 'stroke-width': 2.4 }, s)
    const mark = k === 0 ? check(s, 25, 25, 0.9, C.amberDark) : line(s, 'M17 17 L33 33 M33 17 L17 33', { stroke: C.brick, 'stroke-width': 3 })
    el('div', 'h2', rec, { fontSize: '34px', color: k === 0 ? C.amberDark : C.brick }).textContent = r[2]
    return { who, tx, rec, mark }
  })
  return (t) => {
    title.show(t, cue.l[0] - 0.4, 0.07)
    heads.forEach((h, i) => rise(h, t, cue.l[0] + 0.1 + i * 0.1, 0.6, 8))
    drawn(rules[0], t, cue.l[0] + 0.1, 1.0)
    rows.forEach((r, k) => {
      const at = cue.l[k + 1] - 0.15
      const span = cue.e[k + 1] - cue.l[k + 1]
      rise(r.who, t, at, 0.7, 14)
      const p = spring(t - (at + span * 0.3), 0.6, 13)
      r.tx.style.opacity = clamp(p * 2)
      r.tx.style.transform = `scale(${lerp(0.8, 1, clamp(p))})`
      rise(r.rec, t, at + span * 0.55, 0.6, 10)
      drawn(r.mark, t, at + span * 0.6, 0.45)
      drawn(rules[k + 1], t, at + 0.2, 1.0)
    })
  }
}

S.spe = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 520, { fontSize: '96px' })
  const tag = el('div', 'abs chip', root, { left: X(140, 600) + 'px', top: '318px', border: `2px solid ${C.amber}`, color: C.amberLight, fontSize: '28px', opacity: 0 })
  tag.textContent = ui.tag
  const g = stageSvg(root)
  const xs = [260, 540, 820, 1100, 1380, 1660]
  const yAt = (x) => 540 + Math.sin((x - 260) / 1400 * Math.PI * 2) * 34
  const d = 'M' + Array.from({ length: 57 }, (_, i) => { const x = 260 + i * 25; return `${x} ${yAt(x).toFixed(1)}` }).join(' L')
  const path = line(g, d, { stroke: 'rgb(246 242 234 / 0.25)', 'stroke-width': 2 })
  const prog = sv('path', { d, stroke: C.amber, 'stroke-width': 4, class: 'stroke', pathLength: 1, 'stroke-dasharray': '1 2', 'stroke-dashoffset': 1 }, g)
  const nodes = xs.map((x) => sv('circle', { cx: x, cy: yAt(x), r: 12, fill: C.night2, stroke: C.paper, 'stroke-width': 2 }, g))
  const labels = ui.steps.map((s, i) => pin(root, s, 'body', xs[i], yAt(xs[i]) + 38, { fontSize: '25px', fontWeight: 500, whiteSpace: 'normal', width: '240px', textAlign: 'center', opacity: 0 }, 'center'))
  const capL = block(root, ui.capitalLabel, 'label', 144, 700, 500, { color: C.amber, opacity: 0 })
  const cap = block(root, ui.capital, 'h1 num', 140, 736, 500, { fontSize: '170px', color: C.amber, opacity: 0 })
  const bank = el('div', 'abs', root, { left: X(720, 1060) + 'px', top: '800px', width: '1060px', opacity: 0 })
  el('div', 'h2', bank, { fontSize: '44px', fontWeight: 500 }).textContent = ui.bank
  const und = el('div', '', bank, { height: '4px', width: '220px', background: C.brick, marginTop: '18px', transformOrigin: RTL ? 'right' : 'left', transform: 'scaleX(0)', borderRadius: '2px' })
  return (t) => {
    title.show(t, -0.4, 0.08)
    rise(tag, t, cue.l[0] + 0.4, 0.7, 10)
    drawn(path, t, cue.l[0] - 0.2, 1.4)
    const s0 = cue.e[0] - cue.l[0]
    let reach = 0
    nodes.forEach((n, i) => {
      const at = cue.l[0] + s0 * (0.3 + i * 0.12)
      const p = spring(t - at, 0.55, 15)
      const on = t >= at
      n.setAttribute('r', on ? 10 + 6 * clamp(p) : 12)
      n.setAttribute('fill', on ? C.amber : C.night2)
      n.setAttribute('stroke', on ? C.amber : C.paper)
      rise(labels[i], t, at - 0.1, 0.6, 10)
      reach = Math.max(reach, on ? i / 5 : reach)
    })
    const sm = E.inOut(P(t, cue.l[0] + s0 * 0.3, s0 * 0.62))
    prog.setAttribute('stroke-dashoffset', 1 - sm)
    const s1 = cue.e[1] - cue.l[1]
    rise(capL, t, cue.l[1] - 0.1, 0.6, 10)
    const cp = spring(t - (cue.l[1] + s1 * 0.12), 0.5, 12)
    cap.style.opacity = clamp(cp * 2)
    cap.style.transform = `scale(${lerp(0.7, 1, clamp(cp))})`
    cap.style.transformOrigin = RTL ? 'right center' : 'left center'
    rise(bank, t, cue.l[1] + s1 * 0.45, 0.8, 16)
    und.style.transform = `scaleX(${E.strong(P(t, cue.l[1] + s1 * 0.7, 0.8))})`
  }
}

S.mistakes = (root, ui, cue) => {
  const title = words(root, ui.title, 'h2', 140, 196, 1200, { fontSize: '96px' })
  const rows = ui.items.map((txt, i) => {
    const r = el('div', 'abs', root, { left: X(140, 1640) + 'px', top: 380 + i * 180 + 'px', width: '1640px', display: 'flex', alignItems: 'center', gap: '36px' })
    const s = sv('svg', { width: 74, height: 68, style: 'flex:none' }, r)
    const tri = line(s, 'M37 4 L71 64 L3 64 Z', { stroke: C.brick, 'stroke-width': 3.4 })
    const bar = sv('rect', { x: 34.5, y: 24, width: 5, height: 22, rx: 2.5, fill: C.brick, opacity: 0 }, s)
    const dot = sv('circle', { cx: 37, cy: 54, r: 3.4, fill: C.brick, opacity: 0 }, s)
    const n = el('div', 'label num', r, { color: 'rgb(20 26 34 / 0.45)', width: '40px', flex: 'none' })
    n.textContent = String(i + 1).padStart(2, '0')
    const w = words(r, txt, 'h2', 0, 0, 1400, { position: 'relative', left: 'auto', top: 'auto', fontSize: '46px', fontWeight: 500 })
    w.box.className = 'h2'
    return { tri, bar, dot, w, n }
  })
  const rule = el('div', 'abs', root, { left: X(140, 1640) + 'px', top: '330px', width: '1640px', height: '2px', background: 'rgb(20 26 34 / 0.18)', transformOrigin: RTL ? 'right' : 'left', transform: 'scaleX(0)' })
  return (t) => {
    title.show(t, cue.l[0] - 0.35, 0.08)
    rule.style.transform = `scaleX(${E.strong(P(t, cue.l[0], 1.2))})`
    rows.forEach((r, i) => {
      const at = cue.l[i + 1] - 0.2
      drawn(r.tri, t, at, 0.6)
      r.bar.setAttribute('opacity', E.out(P(t, at + 0.4, 0.3)))
      r.dot.setAttribute('opacity', E.out(P(t, at + 0.5, 0.3)))
      fade(r.n, t, at, 0.5)
      r.w.show(t, at + 0.1, 0.04)
    })
  }
}

S.close = (root, ui, cue) => {
  const list = el('div', 'abs', root, { inset: 0 })
  const title = words(list, ui.title, 'h2', 140, 180, 1400, { fontSize: '72px' })
  const items = ui.items.map((txt, i) => {
    const col = i < 7 ? 0 : 1
    const row = i < 7 ? i : i - 7
    const r = el('div', 'abs', list, { left: X(140 + col * 840, 800) + 'px', top: 316 + row * 70 + 'px', width: '800px', display: 'flex', alignItems: 'center', gap: '22px', opacity: 0 })
    const s = sv('svg', { width: 36, height: 36, style: 'flex:none' }, r)
    const box = sv('rect', { x: 2, y: 2, width: 32, height: 32, rx: 8, fill: 'none', stroke: 'rgb(246 242 234 / 0.5)', 'stroke-width': 2 }, s)
    const ck = check(s, 18, 18, 0.7, C.night)
    el('div', 'body', r, { fontSize: '27px', fontWeight: 500 }).textContent = txt
    return { r, box, ck }
  })
  const partners = block(list, ui.partners, 'h2', 144, 836, 1400, { fontSize: '32px', color: C.amberLight, opacity: 0 })
  const end = el('div', 'abs', root, { inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', opacity: 0 })
  const markSvg = MARK.replace('<svg ', '<svg width="150" height="152" ')
  const m = el('div', '', end, { color: C.paper })
  m.innerHTML = markSvg
  const nl = el('div', 'label', end, { marginTop: '54px', color: C.amber })
  nl.textContent = ui.nextLabel
  const nx = el('div', 'h2', end, { marginTop: '18px', fontSize: '76px', textAlign: 'center' })
  nx.textContent = ui.next
  return (t) => {
    title.show(t, cue.l[0] - 0.4, 0.06)
    const s0 = cue.e[0] - cue.l[0]
    items.forEach(({ r, box, ck }, i) => {
      const at = cue.l[0] + 0.1 + i * Math.min(0.26, (s0 * 0.6) / 13)
      rise(r, t, at - 0.15, 0.5, 10)
      const on = P(t, at + 0.15, 0.25)
      box.setAttribute('fill', on > 0 ? `rgb(232 163 61 / ${on})` : 'none')
      box.setAttribute('stroke', on > 0 ? C.amber : 'rgb(246 242 234 / 0.5)')
      drawn(ck, t, at + 0.2, 0.3)
    })
    rise(partners, t, cue.l[0] + s0 * 0.66, 0.8, 12)
    const away = E.inOut(P(t, cue.l[1] - 0.7, 0.9))
    list.style.opacity = 1 - away
    list.style.transform = `translate3d(0, ${-away * 40}px, 0) scale(${1 - away * 0.03})`
    const e = E.strong(P(t, cue.l[1] - 0.3, 1.2))
    end.style.opacity = e
    m.style.transform = `translate3d(0, ${(1 - e) * 30}px, 0) scale(${lerp(0.8, 1, e)})`
    nx.style.transform = `translate3d(0, ${(1 - E.strong(P(t, cue.l[1], 1.2))) * 40}px, 0)`
    nx.style.opacity = E.strong(P(t, cue.l[1], 1.0))
  }
}

// ── The film ────────────────────────────────────────────────────────────────
const MARK = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 411 415" ><g transform="translate(0,415) scale(0.1,-0.1)" fill="currentColor" stroke="none"><path d="M1970 4063 c-19 -8 -176 -126 -350 -261 -173 -135 -592 -461 -930 -725 -338 -263 -619 -488 -623 -500 -14 -39 188 -204 353 -287 125 -63 255 -75 352 -33 39 18 258 186 938 718 287 225 291 227 388 205 56 -12 28 9 941 -704 274 -214 312 -237 412 -244 110 -8 214 32 385 146 164 111 225 178 196 217 -9 13 -170 140 -822 645 -272 211 -565 438 -650 505 -439 343 -427 336 -507 334 -26 0 -64 -7 -83 -16z"></path><path d="M36 1873 c-3 -15 -4 -138 -3 -273 3 -233 4 -248 26 -293 34 -70 87 -110 204 -153 227 -83 853 -337 1012 -411 394 -182 624 -371 744 -610 17 -35 33 -63 36 -63 3 0 19 28 36 63 125 246 355 433 771 623 126 58 640 266 985 398 143 55 191 97 217 188 17 58 21 544 5 554 -16 9 -682 -264 -1019 -419 -566 -260 -818 -450 -954 -722 -43 -84 -43 -84 -65 -30 -80 193 -290 395 -576 555 -228 127 -628 307 -1160 523 -271 109 -252 104 -259 70z"></path></g></svg>`

/** Background, way in, and which lesson of the module each scene belongs to. */
const PLAN = {
  open: { bg: 'night', enter: 'none', lesson: 0 },
  route: { bg: 'paper', enter: 'iris', lesson: 0 },
  product: { bg: 'paper', enter: 'wipe', lesson: 1 },
  land: { bg: 'night', enter: 'wipe', lesson: 1 },
  channels: { bg: 'paper', enter: 'wipe', lesson: 1 },
  filter: { bg: 'paper', enter: 'push', lesson: 1 },
  urban: { bg: 'night', enter: 'wipe', lesson: 2 },
  practice: { bg: 'paper', enter: 'wipe', lesson: 2 },
  dd: { bg: 'paper', enter: 'push', lesson: 3 },
  checks: { bg: 'night', enter: 'iris', lesson: 3 },
  forms: { bg: 'paper', enter: 'wipe', lesson: 4 },
  tax: { bg: 'paper', enter: 'push', lesson: 4 },
  spe: { bg: 'night', enter: 'wipe', lesson: 4 },
  mistakes: { bg: 'paper', enter: 'wipe', lesson: 5 },
  close: { bg: 'night', enter: 'iris', lesson: 5 },
}
const IN = 0.75
let SCENES = []
let bug, rail, grain, wipeLine
const noise = []

function rng(seed) {
  let s = seed >>> 0
  return () => { s = (s + 0x6d2b79f5) | 0; let m = Math.imul(s ^ (s >>> 15), 1 | s); m = (m + Math.imul(m ^ (m >>> 7), 61 | m)) ^ m; return ((m ^ (m >>> 14)) >>> 0) / 4294967296 }
}

window.boot = async ({ script, timeline }) => {
  LOCALE = script.locale
  RTL = LOCALE === 'ar'
  document.documentElement.dir = RTL ? 'rtl' : 'ltr'
  document.documentElement.lang = LOCALE
  const stage = document.getElementById('stage')
  const byId = Object.fromEntries(script.scenes.map((s) => [s.id, s]))
  SCENES = timeline.scenes.map((tl, k) => {
    const plan = PLAN[tl.id]
    const node = el('div', `scene ${plan.bg}`, stage, { zIndex: k + 1 })
    el('div', 'grid', node)
    const cam = el('div', 'cam', node)
    const cue = { d: tl.end - tl.start, l: tl.lines.map((l) => l.start - tl.start), e: tl.lines.map((l) => l.end - tl.start) }
    const update = S[tl.id](cam, byId[tl.id].ui, cue)
    return { ...tl, ...plan, node, cam, update, cue }
  })
  // The bug: the brand and where we are; the rail: the four lessons.
  bug = el('div', '', document.body)
  bug.id = 'bug'
  const bm = el('span', '', bug)
  bm.innerHTML = MARK.replace('<svg ', '<svg width="30" height="30" ')
  el('span', '', bug).textContent = script.bug
  rail = el('div', '', document.body)
  rail.id = 'rail'
  rail.segs = [1, 2, 3, 4].map((n) => {
    const s = el('div', 'seg', rail)
    const b = el('div', 'bar', s)
    const f = el('div', 'fill', b)
    el('div', 'tag num', s).textContent = `1.${n}`
    return { s, f }
  })
  wipeLine = document.getElementById('wipe')
  // Film grain: a few frames of noise, drawn once, shown in turn.
  grain = document.getElementById('grain')
  const r = rng(7)
  for (let k = 0; k < 6; k++) {
    const c = document.createElement('canvas')
    c.width = 960; c.height = 540
    const ctx = c.getContext('2d')
    const img = ctx.createImageData(960, 540)
    for (let i = 0; i < img.data.length; i += 4) { const v = 110 + r() * 50; img.data[i] = img.data[i + 1] = img.data[i + 2] = v; img.data[i + 3] = 255 }
    ctx.putImageData(img, 0, 0)
    noise.push(c)
  }
  await document.fonts.ready
  return { duration: timeline.duration }
}

window.frame = (t) => {
  let top = null
  SCENES.forEach((s, k) => {
    const lt = t - s.start
    const on = lt >= 0 && lt <= s.end - s.start
    s.node.style.visibility = on ? 'visible' : 'hidden'
    if (!on) return
    top = s
    s.update(lt)
    const d = s.end - s.start
    // Life: the camera drifts in slowly; on the way out it steps back and dims.
    const drift = 1 + 0.028 * (lt / d)
    const out = E.inOut(P(lt, d - IN, IN))
    s.cam.style.transform = `scale(${drift - out * 0.035})`
    s.cam.style.opacity = 1 - out * 0.35
    // The way in.
    const p = k === 0 ? 1 : E.inOut(P(lt, 0, IN))
    s.node.style.clipPath = ''
    s.node.style.transform = ''
    if (p < 1) {
      if (s.enter === 'wipe') s.node.style.clipPath = RTL ? `inset(0 0 0 ${(1 - p) * 100}%)` : `inset(0 ${(1 - p) * 100}% 0 0)`
      if (s.enter === 'iris') s.node.style.clipPath = `circle(${p * 120}% at 50% 52%)`
      if (s.enter === 'push') s.node.style.transform = `translate3d(${(RTL ? -1 : 1) * (1 - p) * W}px, 0, 0)`
    }
    s.wipe = s.enter === 'wipe' && p > 0 && p < 1 ? p : null
  })
  const w = SCENES.find((s) => s.wipe != null && t >= s.start && t <= s.end)
  wipeLine.style.opacity = w ? 1 : 0
  if (w) wipeLine.style.left = (RTL ? W - w.wipe * W : w.wipe * W) - 1 + 'px'
  // Overlays follow the scene on top.
  const night = top && top.bg === 'night'
  const ink = night ? C.paper : C.ink
  bug.style.color = ink
  bug.querySelector('span').style.color = night ? C.paper : '#006FFE'
  bug.style.opacity = top ? E.out(P(t, 1.2, 1.0)) * (1 - E.out(P(t, SCENES.at(-1).end - 3.2, 0.8))) : 0
  rail.style.color = ink
  const lesson = top ? top.lesson : 0
  rail.style.opacity = clamp(E.out(P(t, SCENES.find((s) => s.id === 'product').start + 0.3, 0.8)) - E.out(P(t, SCENES.find((s) => s.id === 'mistakes').start, 0.8)))
  rail.segs.forEach(({ f }, i) => {
    const n = i + 1
    let v = 0
    if (lesson > n) v = 1
    else if (lesson === n) {
      const mine = SCENES.filter((s) => s.lesson === n)
      v = clamp((t - mine[0].start) / (mine.at(-1).end - mine[0].start))
    }
    f.style.transform = `scaleX(${v})`
  })
  const g = grain.getContext('2d')
  const f = Math.floor(t * 30)
  g.drawImage(noise[f % noise.length], -((f * 137) % 200), -((f * 71) % 120), 1160, 660)
}
