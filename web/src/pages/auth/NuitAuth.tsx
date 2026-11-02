import { useEffect, type PointerEventHandler, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { BackgroundVideo } from '@/components/BackgroundVideo'
import { useMediaNow } from '@/hooks/useMediaNow'
import { setHeaderTone } from '@/lib/headerTone'

/**
 * The account screens' night: sign in, sign up, the forgotten password and
 * the password reset.
 *
 * On a wide screen the building-site video fills the half of the screen on
 * the reading side's start (the left, the right in Arabic), edge to edge from
 * under the header to the bottom, and fades out into the night towards the
 * form, with no edge;
 * one short sentence sits low on it, over a night veil. The other half is the
 * night itself (two lights that wander, an architect's grid, the grain) with
 * the form in it. On a phone the video is a narrow full-width band on top and
 * the form comes right under it.
 *
 * The video is real footage (docs/credits-photos.md); without motion, on a
 * slow connection or with data saver on, its first frame stands still
 * (BackgroundVideo).
 */
export function NuitAuth({
  children,
  onPointerMove,
  onPointerLeave,
}: {
  children: ReactNode
  onPointerMove?: PointerEventHandler<HTMLDivElement>
  onPointerLeave?: PointerEventHandler<HTMLDivElement>
}) {
  const { t } = useTranslation()
  const wide = useMediaNow('(min-width: 1024px)')

  useEffect(() => {
    setHeaderTone('night')
    return () => setHeaderTone('default')
  }, [])

  // The header is 4rem and its 1px border: the night fills exactly what is left, so nothing scrolls.
  return (
    <div
      data-plein
      onPointerMove={onPointerMove}
      onPointerLeave={onPointerLeave}
      className="relative isolate min-h-[calc(100svh-4rem-1px)] overflow-x-clip bg-sombre-950 text-creme-100"
    >
      <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div className="halo absolute end-[10%] top-[-20%] h-[56vmax] w-[56vmax] rounded-full bg-[radial-gradient(circle,rgb(59_111_216/0.22),transparent_60%)]" />
        <div className="halo absolute bottom-[-30%] end-[-6%] h-[58vmax] w-[58vmax] rounded-full bg-[radial-gradient(circle,rgb(232_163_61/0.18),transparent_60%)] [animation-delay:-7s]" />
        <div className="plan-grille absolute inset-0" />
      </div>
      <div aria-hidden className="grain" />

      {/* A wide screen: the video, edge to edge, on the reading side's start.
          It does not stop at an edge: it fades out towards the form (a mask,
          not a colour laid over it), so the night, its grid and its lights
          run on underneath without a seam. */}
      {wide && (
        <div className="absolute inset-y-0 start-0 w-[58%]">
          <div className="fondu-nuit absolute inset-0 isolate overflow-hidden">
            <BackgroundVideo overlayClassName="bg-[linear-gradient(to_top,rgb(7_15_28/0.92),rgb(7_15_28/0.2)_42%,rgb(7_15_28/0.35))]" />
          </div>
          <p className="titre absolute bottom-14 start-10 max-w-[26rem] text-balance text-[clamp(1.5rem,0.9rem+1.3vw,2.25rem)] text-creme-100 xl:start-16">
            {t('auth.tagline')}
          </p>
        </div>
      )}

      <div className="container relative flex min-h-[calc(100svh-4rem-1px)] flex-col lg:grid lg:grid-cols-2 lg:items-center lg:py-10">
        {/* A phone: a narrow full-width band of the video, then the form at once. */}
        {!wide && (
          <div aria-hidden className="relative isolate -mx-4 mb-1 h-[7.5rem] overflow-hidden sm:-mx-6 sm:h-[9rem]">
            <BackgroundVideo className="object-[50%_60%]" overlayClassName="bg-[linear-gradient(to_bottom,rgb(7_15_28/0.2),rgb(7_15_28/0.4)_55%,#070F1C)]" />
          </div>
        )}
        <div className="relative z-10 mx-auto w-full max-w-[29rem] pb-10 lg:col-start-2 lg:pb-0">{children}</div>
      </div>
    </div>
  )
}
