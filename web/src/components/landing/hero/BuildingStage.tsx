import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react'
import { cn } from '@/lib/utils'
import { BuildingSvg, type BuildingSvgHandle } from './BuildingSvg'
import type { BuildingSceneHandle } from './sceneKit'

/**
 * The building, in the best form this device can afford.
 *
 * Everyone starts with the drawing (BuildingSvg), or with a still of the
 * finished scene where `still` is given: neither needs WebGL. A device that passes the gate below then
 * fetches the WebGL scene, after the content, and the drawing fades into it.
 * If the scene loses its graphics context or runs too slowly, the drawing
 * comes back for the rest of the visit.
 */
export interface BuildingHandle {
  setProgress(p: number): void
  setPointer(x: number, y: number): void
}

type Navigator3D = Navigator & {
  connection?: { saveData?: boolean; effectiveType?: string }
  deviceMemory?: number
}

/** `?b=svg` or `?b=3d` in the address forces one form, to compare them. */
const FORMS = ['svg', '3d'] as const
type Forced = (typeof FORMS)[number]
function forcedForm(): Forced | null {
  const value = new URLSearchParams(window.location.search).get('b')
  return FORMS.find((form) => form === value) ?? null
}

/**
 * Who gets the scene: not a visitor saving data or on a slow connection, not
 * a phone with little memory or few cores, and only where WebGL 2 exists.
 * Browsers that say nothing about memory or connection (Safari) are let in;
 * the frame-rate watchdog in the scene is the second line.
 */
function canAfford3D(): boolean {
  const nav = navigator as Navigator3D
  if (nav.connection?.saveData) return false
  if (nav.connection?.effectiveType && nav.connection.effectiveType !== '4g') return false
  if (nav.deviceMemory !== undefined && nav.deviceMemory < 4) return false
  if (nav.hardwareConcurrency && nav.hardwareConcurrency < 4) return false
  try {
    return document.createElement('canvas').getContext('webgl2') !== null
  } catch {
    return false
  }
}

export const BuildingStage = forwardRef<BuildingHandle, { reduced: boolean; className?: string; still?: string }>(
  function BuildingStage({ reduced, className, still }, ref) {
    const box = useRef<HTMLDivElement>(null)
    const canvas = useRef<HTMLCanvasElement>(null)
    const drawing = useRef<BuildingSvgHandle>(null)
    const scene = useRef<BuildingSceneHandle | null>(null)
    const progress = useRef(reduced ? 1 : 0)
    const [live, setLive] = useState(false)
    // Whether this device is getting the scene. While it loads, the drawing is
    // held back: it still shows the first study of the building, and fading
    // from one building into another would read as a glitch. It comes back if
    // the scene fails.
    const [awaited, setAwaited] = useState(() => {
      const forced = forcedForm()
      return !reduced && forced !== 'svg' && (forced !== null || canAfford3D())
    })

    useImperativeHandle(
      ref,
      () => ({
        setProgress(p) {
          progress.current = p
          // The drawing is kept in step even while hidden: if the scene fails,
          // it comes back exactly where the scroll is.
          drawing.current?.setProgress(p)
          scene.current?.setProgress(p)
        },
        setPointer(x, y) {
          scene.current?.setPointer(x, y)
        },
      }),
      [],
    )

    useEffect(() => {
      drawing.current?.setProgress(progress.current)
    }, [])

    useEffect(() => {
      const forced = forcedForm()
      if (!awaited) return
      const node = box.current
      const surface = canvas.current
      if (!node || !surface) return

      let cancelled = false
      let handle: BuildingSceneHandle | null = null
      let onScreen = false
      const sync = () => handle?.setRunning(onScreen && document.visibilityState === 'visible')

      const visibility = new IntersectionObserver(([entry]) => {
        onScreen = entry.isIntersecting
        sync()
      })
      const sizes = new ResizeObserver(() => handle?.resize())
      const giveUp = () => {
        if (cancelled) return
        setAwaited(false)
        setLive(false)
        handle?.dispose()
        handle = null
        scene.current = null
      }

      // After the content: when the browser has nothing better to do.
      const idle = window.requestIdleCallback ?? ((run: () => void) => window.setTimeout(run, 600))
      const cancelIdle = window.cancelIdleCallback ?? window.clearTimeout
      const scheduled = idle(() => {
        // The building you can see into: furnished rooms behind clear glass.
        void import('./interiorScene')
          .then((scene) => scene.createInteriorScene)
          .then((createScene) => {
            if (cancelled) return
            handle = createScene(surface, {
              compact: node.clientWidth < 560,
              maxPixelRatio: window.matchMedia('(pointer: coarse)').matches ? 1.5 : 2,
              watchdog: forced === null,
              onFirstFrame: () => !cancelled && setLive(true),
              onFail: giveUp,
            })
            handle.setProgress(progress.current)
            scene.current = handle
            visibility.observe(node)
            sizes.observe(node)
            document.addEventListener('visibilitychange', sync)
          })
          .catch(() => {
            // The chunk did not arrive: the drawing takes over.
            if (!cancelled) setAwaited(false)
          })
      })

      return () => {
        cancelled = true
        cancelIdle(scheduled)
        visibility.disconnect()
        sizes.disconnect()
        document.removeEventListener('visibilitychange', sync)
        handle?.dispose()
        scene.current = null
      }
      // `awaited` only ever goes from true to false, and then the scene is gone.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    return (
      <div ref={box} className={cn('relative h-full w-full', className)} aria-hidden="true">
        <div
          className={cn(
            'absolute inset-0 transition-opacity duration-700 ease-out-strong',
            live || awaited ? 'opacity-0' : 'opacity-100',
          )}
        >
          {/* Where the building is only ever shown finished, a still of the
              scene itself stands in for it: the very same building. */}
          {still ? <img src={still} alt="" decoding="async" className="h-full w-full object-contain" /> : <BuildingSvg ref={drawing} />}
        </div>
        <canvas
          ref={canvas}
          className={cn(
            'absolute inset-0 h-full w-full transition-opacity duration-700 ease-out-strong',
            live ? 'opacity-100' : 'opacity-0',
          )}
        />
      </div>
    )
  },
)
