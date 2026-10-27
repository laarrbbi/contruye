import { useEffect, useRef, useState } from 'react'
import { useMediaQuery } from '@/hooks/useMediaQuery'
import { useReducedMotion } from '@/hooks/useReducedMotion'
import { cn } from '@/lib/utils'

// The hero footage, encoded twice (H.264, no audio track, index first so
// playback starts on the first bytes). A phone gets a third of the bytes.
// The 5 Mbit/s original was 3.1 MB and was downloaded on every page,
// including on phones where its panel is not even displayed.
const HERO_FOOTAGE = { small: '/hero-360.mp4', large: '/hero-720.mp4' }

/**
 * Decorative full-bleed background video.
 *
 * Two things browsers make awkward, handled here once:
 * - Autoplay is only permitted for muted video, and React setting `muted` as a
 *   prop does not reliably land on the element before the browser decides.
 *   We set `muted` on the node itself and call play() explicitly.
 * - play() rejects on some platforms (low-power mode, data saver). That is not
 *   an error worth surfacing — the scrim colour stands in for the video.
 *
 * Nothing is mounted at all when the visitor asked for reduced motion.
 */
export function BackgroundVideo({
  sources = HERO_FOOTAGE,
  media,
  className,
  overlayClassName,
  /** Darkens the footage so overlaid text keeps its contrast. */
  brightness = 0.55,
  cropCorner,
}: {
  /** `small` below 768 px, `large` from there, chosen once at mount. */
  sources?: { small: string; large: string }
  /**
   * Mount only while this media query matches. `display: none` does not stop
   * a video from downloading, so a panel hidden on phones must say so here.
   */
  media?: string
  className?: string
  overlayClassName?: string
  brightness?: number
  /**
   * Zoom past a generator watermark burned into a corner of the footage.
   * Anchors the opposite corner so the watermark is pushed out of frame.
   */
  cropCorner?: 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left'
}) {
  const ref = useRef<HTMLVideoElement>(null)
  const reduced = useReducedMotion()
  const visible = useMediaQuery(media ?? 'all')
  // Once, not on every resize: turning a phone sideways must not fetch the
  // other file.
  const [src] = useState(() =>
    window.matchMedia('(min-width: 768px)').matches ? sources.large : sources.small,
  )
  // Decorative footage must not compete with the price for a buyer on a
  // metered Algerian mobile plan. Only fetch it when the connection can
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
  }, [allowMotion, src])

  return (
    <>
      {allowMotion && (
        <video
          ref={ref}
          className={cn(
            'pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover',
            'transition-opacity duration-700 ease-out-strong',
            ready ? 'opacity-100' : 'opacity-0',
            className,
          )}
          onLoadedData={() => setReady(true)}
          src={src}
          autoPlay
          loop
          muted
          playsInline
          // When autoplay is refused (low-power mode), fetch no more than the
          // first frame needs. When it runs, the browser takes the whole file
          // whatever this says, which is why the encodes are small.
          preload="metadata"
          aria-hidden="true"
          tabIndex={-1}
          style={{
            filter: `brightness(${brightness})`,
            ...(cropCorner
              ? {
                  transform: 'scale(1.35)',
                  transformOrigin: {
                    'top-right': 'left bottom',
                    'top-left': 'right bottom',
                    'bottom-right': 'left top',
                    'bottom-left': 'right top',
                  }[cropCorner],
                }
              : {}),
          }}
        />
      )}
      <div
        className={cn('pointer-events-none absolute inset-0 -z-10', overlayClassName)}
        aria-hidden="true"
      />
    </>
  )
}
