import type { CSSProperties } from 'react'

/**
 * Three line drawings in the hand of an architect's plan: the course's
 * lessons (an open binder of pages), the professional's financial model (a
 * spreadsheet) and its assessment (a checklist). They stand for what the
 * course holds, counted beside each one: generic and stylised, not a picture
 * of anything.
 *
 * Every stroke carries pathLength="1": `line` and `accent` say how each is
 * drawn (traced by the scroll on a pinned stage, or by a keyframe elsewhere).
 */
interface Props {
  line: CSSProperties | string
  className?: string
}
// The length is set on each shape: a group does not hand it down.
const paint = (line: CSSProperties | string) => (typeof line === 'string' ? { className: line, pathLength: 1 } : { style: line, pathLength: 1 })
const BASE = { fill: 'none', strokeWidth: 1.25, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const }

/** The lessons: an open binder, two pages of text, the spine between them. */
export function Lecciones({ line, className }: Props) {
  const p = paint(line)
  return (
    <svg viewBox="0 0 200 160" className={className} aria-hidden>
      <g stroke="#F6F2EA" {...BASE}>
        <path d="M8 150 H192" {...p} />
        <path d="M100 40 Q70 26 28 32 V132 Q70 126 100 140 Q130 126 172 132 V32 Q130 26 100 40 Z" {...p} />
        <path d="M100 40 V140" {...p} />
        <path d="M42 54 H84 M42 68 H86 M42 82 H80 M42 96 H86 M42 110 H74" {...p} />
        <path d="M116 68 H158 M116 82 H156 M116 96 H158 M116 110 H146" {...p} />
      </g>
      <path d="M116 50 H142" stroke="#E8A33D" {...BASE} {...p} />
    </svg>
  )
}

/** The financial model: a sheet of cells, a column of figures, a total. */
export function Hoja({ line, className }: Props) {
  const p = paint(line)
  return (
    <svg viewBox="0 0 200 160" className={className} aria-hidden>
      <g stroke="#F6F2EA" {...BASE}>
        <path d="M8 150 H192" {...p} />
        <path d="M30 20 H170 V140 H30 Z" {...p} />
        <path d="M30 40 H170 M30 60 H170 M30 80 H170 M30 100 H170 M30 120 H170" {...p} />
        <path d="M66 20 V140 M110 20 V140" {...p} />
        <path d="M120 48 H158 M128 68 H158 M116 88 H158 M124 108 H158" {...p} />
      </g>
      <path d="M116 129 H158 M116 133 H158" stroke="#E8A33D" {...BASE} {...p} />
    </svg>
  )
}

/** The assessment: a clipboard, its lines ticked one after the other. */
export function Cuestionario({ line, className }: Props) {
  const p = paint(line)
  return (
    <svg viewBox="0 0 200 160" className={className} aria-hidden>
      <g stroke="#F6F2EA" {...BASE}>
        <path d="M8 150 H192" {...p} />
        <path d="M50 24 H150 V142 H50 Z" {...p} />
        <path d="M84 16 H116 V32 H84 Z" {...p} />
        <path d="M64 52 H76 V64 H64 Z M64 80 H76 V92 H64 Z M64 108 H76 V120 H64 Z" {...p} />
        <path d="M88 58 H134 M88 86 H130 M88 114 H136" {...p} />
      </g>
      <path d="M66 58 L70 62 L78 50 M66 86 L70 90 L78 78" stroke="#E8A33D" {...BASE} {...p} />
    </svg>
  )
}
