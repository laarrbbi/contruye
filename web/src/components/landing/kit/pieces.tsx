import { Fragment, useRef, type PointerEvent, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, ArrowUpRight } from 'lucide-react'
import type { HeroCta } from '@/components/landing/kit/cta'
import { cn } from '@/lib/utils'
import { useVu, vars } from './tools'

/**
 * The pieces every section of the redesigned sales page is made of
 * (docs/redesign-plan.md, 16): the amber button, the editorial link, a title
 * that arrives word by word, the shade of a tree on the paper.
 */

/**
 * A title whose words rise one after the other from behind their line.
 * Read aloud it is the plain sentence: the spans carry no meaning.
 */
export function Mots({ text, delay = 0 }: { text: string; delay?: number }) {
  const words = text.split(' ')
  return (
    <>
      {words.map((word, index) => (
        <Fragment key={index}>
          <span className="mot" style={vars({ '--i': index, '--d': `${delay}ms` })}>
            <span>{word}</span>
          </span>
          {index < words.length - 1 ? ' ' : null}
        </Fragment>
      ))}
    </>
  )
}

/** How far the button leans towards a mouse, in pixels. */
const LEAN = { x: 7, y: 5 }

/**
 * The main button. On a mouse it leans towards the pointer and settles back
 * when the pointer leaves; the lean is a transform on its wrapper, so the
 * button's own press feedback is left alone. A touch screen gets the press only.
 */
export function BoutonOr({ cta, children, className }: { cta: Pick<HeroCta, 'to' | 'state'>; children: ReactNode; className?: string }) {
  const wrap = useRef<HTMLSpanElement>(null)
  const lean = (event: PointerEvent<HTMLSpanElement>) => {
    const node = wrap.current
    if (!node || event.pointerType !== 'mouse') return
    const box = node.getBoundingClientRect()
    const x = (event.clientX - (box.left + box.width / 2)) / (box.width / 2)
    const y = (event.clientY - (box.top + box.height / 2)) / (box.height / 2)
    node.style.transform = `translate3d(${(x * LEAN.x).toFixed(1)}px, ${(y * LEAN.y).toFixed(1)}px, 0)`
  }
  const settle = () => {
    if (wrap.current) wrap.current.style.transform = ''
  }
  return (
    <span
      ref={wrap}
      onPointerMove={lean}
      onPointerLeave={settle}
      className={cn('inline-block transition-transform duration-300 ease-out-strong motion-reduce:!transform-none', className)}
    >
      <Link to={cta.to} state={cta.state} className="btn-or w-full">
        <span className="min-w-0 truncate">{children}</span>
        <span aria-hidden className="btn-or__fleche">
          <ArrowRight className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
        </span>
      </Link>
    </span>
  )
}

/** The second voice: underlined, with an arrow that points out of the page. */
export function LienEdito({ href, children, className }: { href: string; children: ReactNode; className?: string }) {
  return (
    <a href={href} className={cn('lien-edito', className)}>
      {children}
      <ArrowUpRight aria-hidden className="h-[1em] w-[1em] translate-y-[0.12em] rtl:-scale-x-100" strokeWidth={1.5} />
    </a>
  )
}

/** A small generator with a seed: the same leaves on every visit. */
function seeded(seed: number) {
  let state = seed
  return () => {
    state = (state + 0x6d2b79f5) | 0
    let mixed = Math.imul(state ^ (state >>> 15), 1 | state)
    mixed = (mixed + Math.imul(mixed ^ (mixed >>> 7), 61 | mixed)) ^ mixed
    return ((mixed ^ (mixed >>> 14)) >>> 0) / 4294967296
  }
}

/** Three branches from a corner of the sheet, and their leaves. */
const LEAVES = (() => {
  const random = seeded(11)
  const leaves: { x: number; y: number; rx: number; ry: number; turn: number }[] = []
  const branches = [
    { angle: 14, length: 560 },
    { angle: 38, length: 640 },
    { angle: 63, length: 520 },
  ]
  for (const branch of branches) {
    const radians = (branch.angle * Math.PI) / 180
    for (let step = 3; step < 24; step++) {
      const along = (step / 24) * branch.length
      const bend = Math.sin((step / 24) * Math.PI) * 46
      const x = Math.cos(radians) * along - Math.sin(radians) * bend
      const y = Math.sin(radians) * along + Math.cos(radians) * bend
      for (const side of [-1, 1]) {
        if (random() < 0.22) continue
        const rx = 20 + random() * 22
        const turn = branch.angle + side * (32 + random() * 36)
        const lean = (turn * Math.PI) / 180
        leaves.push({ x: x + Math.cos(lean) * rx, y: y + Math.sin(lean) * rx, rx, ry: 6 + random() * 6, turn })
      }
    }
  }
  return leaves
})()

/**
 * The shade of a tree falling across the paper from a top corner. Decoration:
 * it sways slowly on a desktop and holds still on a phone (index.css).
 */
export function Feuillage({ className }: { className?: string }) {
  return (
    // The tree stands on the side the text starts from: mirrored in Arabic.
    <div aria-hidden className={cn('pointer-events-none absolute rtl:-scale-x-100', className)}>
      <svg viewBox="0 0 640 640" className="feuillage h-full w-full overflow-visible">
        <g fill="#1F3A5F">
          {LEAVES.map((leaf, index) => (
            <ellipse key={index} cx={leaf.x} cy={leaf.y} rx={leaf.rx} ry={leaf.ry} transform={`rotate(${leaf.turn.toFixed(1)} ${leaf.x.toFixed(1)} ${leaf.y.toFixed(1)})`} />
          ))}
        </g>
      </svg>
    </div>
  )
}

/**
 * Something that arrives when it comes on screen, on its own: for lists whose
 * items should be met one after the other as the page is read.
 */
export function Apparait({ as: Tag = 'div', className, children }: { as?: 'div' | 'li' | 'p'; className?: string; children: ReactNode }) {
  const ref = useVu<HTMLElement>(0.35)
  return (
    // The tag is chosen by the caller; all three take the same ref.
    <Tag ref={ref as never} className={cn('entree', className)}>
      {children}
    </Tag>
  )
}
