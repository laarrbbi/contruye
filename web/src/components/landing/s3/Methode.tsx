import { useRef, useState, type PointerEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { BuildingStage, type BuildingHandle } from '@/components/landing/hero/BuildingStage'
import { STEPS_END, activeStep } from '@/components/landing/hero/timeline'
import { useScrollProgress, useVu, vars } from '@/components/landing/kit/tools'
import { H3, TEXTE } from '@/components/landing/kit/type'
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion'
import { cn } from '@/lib/utils'

const STEPS = ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10', 's11', 's12'] as const
/** A storey's name on a Spanish drawing: PB is the ground floor (planta baja), then P1, P2… One phase of the course is one storey. */
const level = (index: number) => (index === 0 ? 'PB' : `P${index}`)

/**
 * S3, the method. Night. The building goes up one storey per phase while the
 * twelve phases are read beside it like the level marks of a section drawing.
 *
 * The stage is pinned and the scroll is the clock: p runs from 0 to 1 over
 * the section and is handed as it is to the building (hero/timeline.ts).
 * Only one step is shown at a time, large, while its storey lands and takes
 * its materials; the one before leaves, the next comes. A small scale on the
 * side says which storey one is at. On a phone the building is on top and
 * the step under it.
 * Without motion nothing is pinned: the finished building, then the list.
 */
export function Methode() {
  const { t } = useTranslation()
  const still = usePrefersReducedMotion()
  const building = useRef<BuildingHandle>(null)
  const [step, setStep] = useState(-1)
  const [built, setBuilt] = useState(false)
  const head = useVu<HTMLDivElement>()

  const track = useScrollProgress<HTMLDivElement>((p) => {
    building.current?.setProgress(p)
    setStep(activeStep(p))
    setBuilt(p >= STEPS_END)
  }, !still)

  const lean = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType !== 'mouse') return
    building.current?.setPointer((event.clientX / window.innerWidth) * 2 - 1, (event.clientY / window.innerHeight) * 2 - 1)
  }
  const halos = (
    <div aria-hidden className="pointer-events-none absolute inset-0">
      <div className="halo absolute end-[2%] top-[8%] h-[62vmax] w-[62vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.26),transparent_60%)]" />
      <div className="halo absolute bottom-[-18%] end-[18%] h-[52vmax] w-[52vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.2),transparent_60%)] [animation-delay:-6s]" />
    </div>
  )

  if (still) {
    return (
      <section id="methode" data-tone="night" aria-labelledby="s3-titre" className="relative overflow-hidden bg-sombre-950 text-creme-100">
        {halos}
        <div className="container relative grid gap-12 py-24 lg:grid-cols-12 lg:py-40">
          <div className="lg:col-span-6">
            <h2 id="s3-titre" className={H3}>
              {t('landing.method.title')}
            </h2>
            <ol className="mt-10">
              {STEPS.map((key, index) => (
                <li key={key} className="flex gap-5 border-t border-creme-100/15 py-4">
                  <span className="etiquette w-12 shrink-0 pt-1 text-or" dir="ltr">
                    {level(index)}
                  </span>
                  <span className="text-creme-100/85">{t(`landing.method.${key}`)}</span>
                </li>
              ))}
            </ol>
            <p className={cn(TEXTE, 'mt-8 font-medium')}>{t('landing.method.close')}</p>
          </div>
          <div className="h-[60svh] lg:col-span-6 lg:h-[80svh]">
            <BuildingStage reduced still="/immeuble-termine.webp" />
          </div>
        </div>
      </section>
    )
  }

  // One step at a time, large: the one before leaves upwards, the next comes from below.
  const slide = (index: number) =>
    cn(
      'absolute inset-x-0 top-0 transition-[opacity,transform] duration-700 ease-out-strong',
      index === shown ? 'opacity-100' : index < shown ? 'pointer-events-none -translate-y-10 opacity-0' : 'pointer-events-none translate-y-10 opacity-0',
    )
  // -1 is the sentence that opens the method, 12 the one that closes it.
  const shown = built ? STEPS.length : step

  return (
    <section id="methode" data-tone="night" aria-labelledby="s3-titre" className="relative bg-sombre-950 text-creme-100" style={vars({ '--focus': '#F6F2EA' })}>
      <div ref={track} className="h-[640svh] lg:h-[720svh]">
        <div onPointerMove={lean} className="sticky top-0 h-[100svh] overflow-hidden">
          {halos}
          <div className="container relative flex h-full flex-col pb-[5.5rem] pt-20 lg:grid lg:grid-cols-12 lg:items-center lg:gap-x-8 lg:pb-10">
            {/* Wide screens: where one is in the building, from the ground floor to the eleventh. */}
            <ol aria-hidden className="hidden flex-col-reverse gap-1.5 lg:col-span-1 lg:flex">
              {STEPS.map((key, index) => (
                <li key={key} className={cn('etiquette flex items-center gap-2 transition-colors duration-500', index === step ? 'text-or' : index < shown ? 'text-creme-100/55' : 'text-creme-100/25')} dir="ltr">
                  <span className={cn('h-px bg-current transition-[width] duration-500 ease-out-strong', index === step ? 'w-6' : 'w-3')} />
                  {level(index)}
                </li>
              ))}
            </ol>

            <div ref={head} className="order-2 lg:order-none lg:col-span-5">
              <h2 id="s3-titre" className="hidden max-w-[26rem] text-base font-medium leading-relaxed text-creme-100/70 lg:block">
                {t('landing.method.title')}
              </h2>
              <div className="relative mt-4 h-[11.5rem] sm:h-[13rem] lg:mt-8 lg:h-[24rem]" aria-live="polite">
                <div className={slide(-1)} aria-hidden={shown !== -1}>
                  <p className="titre text-balance text-[clamp(1.6rem,1.1rem+2.4vw,3.25rem)]">{t('landing.method.intro')}</p>
                </div>
                {STEPS.map((key, index) => (
                  <div key={key} className={slide(index)} aria-hidden={shown !== index}>
                    <p className="titre flex items-baseline gap-4 text-[clamp(1.25rem,1rem+1.2vw,2rem)] text-or" dir="ltr">
                      {level(index)}
                      <span className="etiquette text-creme-100/55 lg:hidden">
                        {index + 1}/{STEPS.length}
                      </span>
                    </p>
                    <p className="titre mt-3 text-balance text-[clamp(1.4rem,1rem+1.9vw,2.6rem)] lg:mt-5">{t(`landing.method.${key}`)}</p>
                  </div>
                ))}
                <div className={slide(STEPS.length)} aria-hidden={shown !== STEPS.length}>
                  <p className="titre text-balance text-[clamp(1.4rem,1.05rem+1.7vw,2.5rem)] text-or">{t('landing.method.close')}</p>
                </div>
              </div>
            </div>

            <div className="relative order-1 min-h-0 flex-1 lg:order-none lg:col-span-6 lg:-mb-10 lg:h-[calc(100svh-5rem)] lg:flex-none">
              <BuildingStage ref={building} reduced={false} />
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
