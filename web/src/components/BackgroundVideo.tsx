import { useEffect, useRef, useState } from 'react'
import { useMediaQuery } from '@/hooks/useMediaQuery'
import { useReducedMotion } from '@/hooks/useReducedMotion'
import { cn } from '@/lib/utils'

// The building site, step by step: five real clips (Pexels, Pixabay) in one
// 13 s loop, 1 080 px square, no audio track, index first so playback starts
// on the first bytes (web/scripts/video-chantier.sh, credits in
// docs/credits-photos.md). The poster is its first frame.
const CHANTIER = { webm: '/video/chantier.webm', mp4: '/video/chantier.mp4', poster: '/video/chantier-poster.webp' }

/**
 * Decorative full-bleed background video.
 *
 * The poster is always there; the footage plays over it only where it costs
 * nothing that matters: not for a visitor who asked for reduced motion, not on
 * a slow connection or with data saver on. Those keep the still.
 *
 * Two things browsers make awkward, handled here once:
 * - Autoplay is only permitted for muted video, and React setting `muted` as a
 *   prop does not reliably land on the element before the browser decides.
 *   We set `muted` on the node itself and call play() explicitly.
 * - play() rejects on some platforms (low-power mode). That is not an error
 *   worth surfacing: the poster stands in for the video.
 */
export function BackgroundVideo({
  media,
  className,
  overlayClassName,
  /** Darkens the footage so overlaid text keeps its contrast. */
  brightness = 1,
}: {
  /**
   * Mount only while this media query matches. `display: none` does not stop
   * a video from downloading, so a panel hidden on phones must say so here.
   */
  media?: string
  className?: string
  overlayClassName?: string
  brightness?: number
}) {
  const ref = useRef<HTMLVideoElement>(null)
  const reduced = useReducedMotion()
  const visible = useMediaQuery(media ?? 'all')
  // Decorative footage must not compete with the price for a buyer on a
  // metered mobile plan. Only fetch it when the connection can
  // afford it — and never when the browser says data saver is on.
  const affordable = (() => {
    const c = (navigator as { connection?: { effectiveType?: string; saveData?: boolean } }).connection
    if (!c) return true
    if (c.saveData) return false
    return c.effectiveType === '4g' || c.effectiveType === undefined
  })()
  const allowMotion = reduced === false && visible === true && affordable
  // The first frame arrives whenever the network says so; cutting to it is
  // jarring against the scrim it replaces. Fade it in instead.
  const [ready, setReady] = useState(false)


  useEffect(() => {
    if (!allowMotion) return
    const video = ref.current
    if (!video) return

    // Must be a property, not just an attribute, for autoplay to be allowed.
    video.muted = true
    video.defaultMuted = true

    // Reveal as soon as there is a frame to show, whether or not playback is
    // allowed: when autoplay is refused the still first frame is the fallback,
    // and it should fade in rather than being hidden outright.
    if (video.readyState >= 2) {
      setReady(true)
    }

    const attempt = video.play()
    if (attempt && typeof attempt.catch === 'function') {
      attempt.catch(() => {
        /* Autoplay refused — the still frame under the scrim stands in. */
      })
    }
  }, [allowMotion])

  // Unknown until mounted: wait, rather than fetch a poster a hidden panel never shows.
  if (visible !== true) return null

  const look = cn('pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover', className)
  return (
    <>
      <img src={CHANTIER.poster} alt="" aria-hidden="true" decoding="async" className={look} style={{ filter: brightness === 1 ? undefined : `brightness(${brightness})` }} />
      {allowMotion && (
        <video
          ref={ref}
          className={cn(look, 'transition-opacity duration-700 ease-out-strong', ready ? 'opacity-100' : 'opacity-0')}
          onLoadedData={() => setReady(true)}
          poster={CHANTIER.poster}
          autoPlay
          loop
          muted
          playsInline
          // When autoplay is refused (low-power mode), fetch no more than the
          // first frame needs.
          preload="metadata"
          aria-hidden="true"
          tabIndex={-1}
          style={{ filter: brightness === 1 ? undefined : `brightness(${brightness})` }}
        >
          <source src={CHANTIER.webm} type="video/webm" />
          <source src={CHANTIER.mp4} type="video/mp4" />
        </video>
      )}
      <div
        className={cn('pointer-events-none absolute inset-0 -z-10', overlayClassName)}
        aria-hidden="true"
      />
    </>
  )
}
