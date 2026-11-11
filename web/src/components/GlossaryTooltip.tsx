import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'

interface Tip {
  name: string
  text: string
  x: number
  y: number
  below: boolean
}

/**
 * The explanation of a tax or an institution (the «i» the server puts after
 * each one in the course text, GlossaryTermRenderer). The text travels inside
 * the term; this one card shows it above the term (below it near the top of
 * the screen) on hover, keyboard focus or a tap, outside any scrolling table
 * that would clip it. Mounted once for the whole app.
 */
export function GlossaryTooltip() {
  const [tip, setTip] = useState<Tip | null>(null)

  useEffect(() => {
    const termOf = (target: EventTarget | null) => (target instanceof Element ? target.closest('.term') : null)

    const show = (event: Event) => {
      const term = termOf(event.target)
      if (!term) return
      const name = term.querySelector('.term-tip strong')?.textContent ?? ''
      const text = term.querySelector('.term-tip span')?.textContent ?? ''
      const box = term.getBoundingClientRect()
      const half = Math.min(152, window.innerWidth / 2 - 8)
      setTip({
        name,
        text,
        x: Math.min(Math.max(box.left + box.width / 2, half + 8), window.innerWidth - half - 8),
        y: box.top < 140 ? box.bottom + 8 : box.top - 8,
        below: box.top < 140,
      })
    }
    const hide = (event: Event) => {
      const term = termOf(event.target)
      const next = (event as MouseEvent | FocusEvent).relatedTarget
      if (term && next instanceof Node && term.contains(next)) return
      if (term) setTip(null)
    }
    const clear = () => setTip(null)

    document.addEventListener('mouseover', show)
    document.addEventListener('focusin', show)
    document.addEventListener('mouseout', hide)
    document.addEventListener('focusout', hide)
    window.addEventListener('scroll', clear, true)
    return () => {
      document.removeEventListener('mouseover', show)
      document.removeEventListener('focusin', show)
      document.removeEventListener('mouseout', hide)
      document.removeEventListener('focusout', hide)
      window.removeEventListener('scroll', clear, true)
    }
  }, [])

  if (!tip) return null

  return createPortal(
    <div
      aria-hidden
      className="pointer-events-none fixed z-[60] w-max max-w-[min(19rem,calc(100vw-1rem))] rounded-xl bg-white px-3.5 py-2.5 text-[0.8125rem] leading-snug text-[#1d1d1f] shadow-[0_12px_32px_-8px_rgb(0_0_0/0.28),0_0_0_1px_rgb(0_0_0/0.06)]"
      style={{ left: tip.x, top: tip.y, transform: tip.below ? 'translateX(-50%)' : 'translate(-50%, -100%)' }}
      dir="auto"
    >
      <strong className="block font-semibold">{tip.name}</strong>
      <span className="mt-0.5 block text-[#424245]">{tip.text}</span>
    </div>,
    document.body,
  )
}
