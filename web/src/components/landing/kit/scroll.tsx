import { Fragment } from 'react'

/**
 * A sentence read at the pace of the scroll: its words light up one after
 * the other while --p runs from `from` to `to`. Before its turn a word is
 * barely there; read aloud, it is the plain sentence.
 */
export function Lecture({ text, from, to }: { text: string; from: number; to: number }) {
  const words = text.split(' ')
  const step = (to - from) / words.length
  return (
    <>
      {words.map((word, index) => (
        <Fragment key={index}>
          <span style={{ opacity: `clamp(0.1, calc((var(--p, 0) - ${(from + index * step).toFixed(4)}) / ${(step * 2.5).toFixed(4)}), 1)` }}>{word}</span>
          {index < words.length - 1 ? ' ' : null}
        </Fragment>
      ))}
    </>
  )
}
