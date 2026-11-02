import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { CourseDetail } from '@/api/courses'
import type { HeroCta } from '@/components/landing/kit/cta'
import { venue } from '@/components/landing/kit/moments'
import { BoutonOr, Mots } from '@/components/landing/kit/pieces'
import { Lecture } from '@/components/landing/kit/scroll'
import { useScrollProgress, useVu, vars } from '@/components/landing/kit/tools'
import { useMediaNow } from '@/hooks/useMediaNow'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { phaseModules } from '@/lib/modules'
import { cn, formatPrice } from '@/lib/utils'
import type { PorteScene } from './porteScene'

/** The light that comes through the opened door: warm at its heart, amber at its edge, night in the corners. */
const LIGHT = 'bg-[radial-gradient(120%_95%_at_50%_50%,#FFF1D6_0%,#FBD9A0_32%,#E8A33D_62%,#7A4A12_86%,#070F1C_100%)]'

/** A phone that would struggle with the scene: few cores, little memory, or data saved. */
function weakPhone() {
  const nav = navigator as Navigator & { deviceMemory?: number; connection?: { saveData?: boolean } }
  return (nav.hardwareConcurrency ?? 8) < 6 || (nav.deviceMemory ?? 8) < 4 || nav.connection?.saveData === true
}

/**
 * S9, the handover: this is the end of the page, and it looks like one.
 *
 * Night. A brass key comes to the lock of a walnut front door, goes in and
 * turns; the door opens, a warm light comes through it and through its side
 * lights, then floods the screen; in that light the last words come, line by
 * line, then the button and the price. The stage is pinned and the scene is
 * real 3D (porteScene), fetched only as the section comes near. On a phone
 * that would struggle, or without motion, a still taken from that same scene
 * (the door open, the light) stands behind the words and the button.
 */
export function Cloture({ course, cta }: { course: CourseDetail; cta: HeroCta }) {
  const { t, i18n } = useTranslation()
  const lang = i18n.language
  const still = usePrefersReducedMotion()
  const wide = useMediaNow('(min-width: 1024px)')
  const [weak] = useState(weakPhone)
  const live = !still && (wide || !weak)
  const stage = useRef<HTMLDivElement>(null)
  const canvas = useRef<HTMLCanvasElement>(null)
  const scene = useRef<PorteScene | null>(null)
  const words = useVu<HTMLDivElement>(0.4)
  const modules = t('landing.counts.modules', { count: course.modules.length })
  const title = t('landing.finalCta.title', { modules })
  // One sentence to a line: the title is said in three breaths.
  const lines = title.match(/[^.!?؟]+[.!?؟]*/g)?.map((line) => line.trim()).filter(Boolean) ?? [title]
  const price = formatPrice(course.price_eur, 'EUR', lang)
  const euro = t('course.pricing.vatIncluded')
  const closing = t('landing.finalCta.p1', { phases: phaseModules(course.modules).length })

  // Once the light has flooded the screen the header stands on light, not on night.
  const [tone, setTone] = useState<'night' | 'paper'>('night')
  const section = useRef<HTMLElement>(null)
  const track = useScrollProgress<HTMLDivElement>((p) => {
    stage.current?.style.setProperty('--p', p.toFixed(4))
    scene.current?.setProgress(p)
    setTone(p > 0.84 ? 'paper' : 'night')
  }, live)
  useEffect(() => {
    section.current?.dispatchEvent(new CustomEvent('tonechange', { bubbles: true }))
  }, [tone])

  // The scene and its models are fetched when the section is near, and drawn only while it is on screen.
  useEffect(() => {
    const box = stage.current
    const surface = canvas.current
    if (!live || !box || !surface) return
    let cancelled = false
    let onScreen = false
    const presence = new IntersectionObserver(([entry]) => {
      onScreen = entry.isIntersecting
      scene.current?.setRunning(onScreen)
    })
    const sizes = new ResizeObserver(() => scene.current?.resize())
    const approach = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting || scene.current) return
        approach.disconnect()
        void import('./porteScene')
          .then(({ createPorteScene }) => createPorteScene(surface))
          .then((made) => {
            if (cancelled) return made.dispose()
            scene.current = made
            made.setProgress(Number(box.style.getPropertyValue('--p')) || 0)
            made.setRunning(onScreen)
          })
          .catch(() => {
            /* Without the scene the light and the words still come. */
          })
      },
      { rootMargin: '150% 0px' },
    )
    approach.observe(box)
    presence.observe(box)
    sizes.observe(surface)
    return () => {
      cancelled = true
      approach.disconnect()
      presence.disconnect()
      sizes.disconnect()
      scene.current?.dispose()
      scene.current = null
    }
  }, [live])

  const buy = (
    <div data-achat className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-6">
      <BoutonOr cta={cta} className="w-full sm:w-auto [&_.btn-or]:shadow-[inset_0_1px_0_rgb(255_255_255/0.55),0_0_0_1px_rgb(20_26_34/0.9),0_18px_36px_-10px_rgb(20_26_34/0.55)]">
        {t(cta.label)}
      </BoutonOr>
      <p className="text-center sm:text-start">
        <span className="titre block text-[2rem] leading-none tabular-nums lg:text-[2.5rem]">{price}</span>
        {euro && <span className="mt-1.5 block text-sm text-plume/80">{euro}</span>}
      </p>
    </div>
  )

  if (live) {
    return (
      <section ref={section} id="fin" data-tone={tone} aria-labelledby="s9-titre" className="relative bg-sombre-950" style={vars({ '--focus': '#141A22' })}>
        <div ref={track} className="h-[440svh] lg:h-[520svh]">
          <div ref={stage} className="sticky top-0 h-[100svh] overflow-hidden bg-[radial-gradient(110%_80%_at_50%_30%,#12253F,#0B1A2E_50%,#070F1C)]" style={vars({ '--p': 0 })}>
            <canvas ref={canvas} aria-hidden className="absolute inset-0 h-full w-full" />
            {/* The light floods the screen once the door is open. */}
            <div aria-hidden className={cn('absolute inset-0', LIGHT)} style={{ opacity: 'clamp(0, calc((var(--p, 0) - 0.74) / 0.12), 1)' }} />
            <div className="container relative flex h-full flex-col items-center justify-center text-center text-plume">
              {/* The words belong to the light: nothing of them shows before it. */}
              <h2 id="s9-titre" className="titre text-[clamp(2.25rem,1.2rem+3.6vw,4.5rem)]" style={venue(0.84, 0.86, 0)}>
                {lines.map((line, index) => (
                  <span key={line} className="block">
                    <Lecture text={line} from={0.86 + (index * 0.09) / lines.length} to={0.86 + ((index + 1) * 0.09) / lines.length} />{' '}
                  </span>
                ))}
              </h2>
              <p className="mx-auto mt-6 max-w-[34rem] text-base leading-relaxed text-plume/85 lg:text-lg" style={venue(0.93, 0.96)}>
                {closing}
              </p>
              <div className="mt-8" style={venue(0.95, 0.99)}>
                {buy}
              </div>
            </div>
          </div>
        </div>
      </section>
    )
  }

  // A still from the same scene, the door open on its light; the words and the button over it.
  return (
    <section id="fin" data-tone="paper" aria-labelledby="s9-titre" className="relative overflow-hidden bg-sombre-950" style={vars({ '--focus': '#141A22' })}>
      <picture>
        <source media="(min-width: 768px)" srcSet="/fin/porte-ouverte-large.webp" />
        <img src="/fin/porte-ouverte-haut.webp" alt="" loading="lazy" decoding="async" className="absolute inset-0 h-full w-full object-cover" />
      </picture>
      <div aria-hidden className="absolute inset-0 bg-[radial-gradient(70%_55%_at_50%_50%,rgb(255_241_214/0.9),rgb(251_217_160/0.62)_45%,rgb(232_163_61/0.15)_80%,transparent)]" />
      <div ref={words} className="relative flex min-h-[100svh] flex-col items-center justify-center px-4 py-24 text-center text-plume">
        <h2 id="s9-titre" className="titre mx-auto text-balance text-[clamp(2.1rem,1.2rem+4vw,4rem)]">
          {lines.map((line, index) => (
            <span key={line} className="block">
              {still ? line : <Mots text={line} delay={150 + index * 320} />}{' '}
            </span>
          ))}
        </h2>
        <p className={cn('mx-auto mt-6 max-w-[34rem] text-base leading-relaxed text-plume/85 lg:text-lg', !still && 'entree')} style={still ? undefined : vars({ '--d': '800ms' })}>
          {closing}
        </p>
        <div className={cn('mt-8', !still && 'entree')} style={still ? undefined : vars({ '--d': '900ms' })}>
          {buy}
        </div>
      </div>
    </section>
  )
}
