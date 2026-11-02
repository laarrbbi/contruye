import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { CourseDetail } from '@/api/courses'
import type { HeroCta } from '@/components/landing/kit/cta'
import { cn, formatPrice } from '@/lib/utils'

/**
 * On a phone, the price and the button stay within reach at the bottom of
 * the screen. The bar comes up once the page's own button has left the
 * screen, and steps aside again wherever a section shows a button of its own
 * (`data-achat` on that button's block).
 */
export function BarreAchat({ course, cta, held }: { course: CourseDetail | undefined; cta: HeroCta; held: boolean }) {
  const { t, i18n } = useTranslation()
  const [inView, setInView] = useState(1)

  useEffect(() => {
    const seen = new Set<Element>()
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => (entry.isIntersecting ? seen.add(entry.target) : seen.delete(entry.target)))
      setInView(seen.size)
    })
    const watch = () => document.querySelectorAll('#s1-achat, [data-achat]').forEach((node) => observer.observe(node))
    watch()
    const root = document.getElementById('sales-page')
    const mutations = new MutationObserver(watch)
    if (root) mutations.observe(root, { childList: true, subtree: true })
    return () => {
      mutations.disconnect()
      observer.disconnect()
    }
  }, [])

  if (!course) return null
  const shown = !held && inView === 0
  return (
    <div
      data-bottom-bar="lg"
      {...(shown ? {} : { inert: '' })}
      className={cn(
        'fixed inset-x-0 bottom-0 z-40 border-t border-creme-100/10 bg-sombre-950/85 text-creme-100 backdrop-blur-lg transition-transform duration-500 ease-out-strong lg:hidden',
        'pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 shadow-[0_-18px_40px_-12px_rgb(7_15_28/0.6)]',
        shown ? 'translate-y-0' : 'translate-y-[110%]',
      )}
      style={{ ['--focus' as string]: '#F6F2EA' }}
    >
      <div className="container flex items-center justify-between gap-3">
        <p className="titre shrink-0 text-[1.25rem] leading-none tabular-nums">{formatPrice(course.price_eur, 'EUR', i18n.language)}</p>
        <Link to={cta.to} state={cta.state} className="btn-or h-12 min-w-0 flex-1 !gap-2 !ps-4 text-[0.875rem] sm:flex-none">
          <span className="min-w-0 truncate">{t(cta.label)}</span>
          <span aria-hidden className="btn-or__fleche !h-9 !w-9">
            <ArrowRight className="h-4 w-4 rtl:-scale-x-100" strokeWidth={1.75} />
          </span>
        </Link>
      </div>
    </div>
  )
}
