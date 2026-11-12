import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Captions, Gauge, Languages, Lock, PlayCircle } from 'lucide-react'
import { LOCALE_NAMES, type Locale } from '@/i18n/locales'
import { usePlayback } from '@/api/courses'
import { cn, intlLocale } from '@/lib/utils'
import { WatermarkOverlay } from './WatermarkOverlay'

/** The speeds offered under a video. */
const RATES = [0.75, 1, 1.25, 1.5, 1.75, 2] as const
const RATE_KEY = 'construye.video.rate'
const CAPTIONS_KEY = 'construye.video.captions'

/** A choice this device remembers; private mode or blocked storage simply forgets it. */
function remembered(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function remember(key: string, value: string) {
  try {
    localStorage.setItem(key, value)
  } catch {
    // Not kept: it applies to this video only.
  }
}

interface VideoPlayerProps {
  lessonId: number
  /** Resume position in seconds — appended to the signed embed URL when > 0. */
  startAt?: number
  /** Watermark text (viewer e-mail). Falls back to the playback payload. */
  watermark?: string | null
  className?: string
}

/**
 * Signed Bunny Stream embed for a lesson (or, where Bunny is not configured, the
 * preview's video file), with loading skeleton, locked / error states and a
 * drifting watermark overlay.
 */
export function VideoPlayer({ lessonId, startAt = 0, watermark, className }: VideoPlayerProps) {
  const { t, i18n } = useTranslation()
  const playback = usePlayback(lessonId)
  // The lesson has no version in the student's language: say which one plays.
  const otherLanguage = playback.data && playback.data.locale !== i18n.language ? LOCALE_NAMES[playback.data.locale as Locale] : null

  const isFile = playback.data?.type === 'file'
  const video = useRef<HTMLVideoElement>(null)

  // The speed and the subtitles, as the viewer last chose them on this device.
  const [rate, setRate] = useState<number>(() => {
    const stored = Number(remembered(RATE_KEY))
    return RATES.includes(stored as (typeof RATES)[number]) ? stored : 1
  })
  const [captionsOn, setCaptionsOn] = useState(() => remembered(CAPTIONS_KEY) === '1')
  const captionsUrl = isFile ? (playback.data?.captions ?? null) : null

  // A browser resets the speed when the video loads: set it again then, and
  // as the default for what it loads next.
  const applyRate = () => {
    const element = video.current
    if (!element) return
    element.defaultPlaybackRate = rate
    element.playbackRate = rate
  }
  useEffect(applyRate, [rate])

  const applyCaptions = () => {
    const track = video.current?.textTracks[0]
    if (track) track.mode = captionsOn ? 'showing' : 'hidden'
  }
  useEffect(applyCaptions, [captionsOn])

  const chooseRate = (next: number) => {
    setRate(next)
    remember(RATE_KEY, String(next))
  }
  const toggleCaptions = () => {
    setCaptionsOn((on) => {
      remember(CAPTIONS_KEY, on ? '0' : '1')
      return !on
    })
  }
  const rateLabel = (value: number) =>
    `${new Intl.NumberFormat(intlLocale(i18n.language), { maximumFractionDigits: 2 }).format(value)}×`
  const src = useMemo(() => {
    if (!playback.data?.url) return null
    const resume = Math.floor(startAt)
    if (resume <= 0) return playback.data.url
    // A file resumes with a media fragment; Bunny's embed with its own parameter.
    return isFile ? `${playback.data.url}#t=${resume}` : `${playback.data.url}&t=${resume}`
  }, [playback.data?.url, startAt, isFile])

  const status = isAxiosError(playback.error) ? playback.error.response?.status : undefined
  const isLocked = status === 401 || status === 403

  const watermarkText = watermark ?? playback.data?.watermark ?? null

  return (
    <div className="space-y-2">
      <div
        className={cn(
          'relative aspect-video w-full overflow-hidden rounded-2xl bg-sombre-950 text-creme-100 shadow-[0_40px_80px_-50px_rgb(7_15_28/0.7)]',
          className,
        )}
      >
        {playback.isLoading && (
          // Still, not pulsing: a placeholder that throbs for the second it is
          // on screen at every lesson makes the player feel slower than it is.
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-muted text-muted-foreground">
            <PlayCircle className="h-12 w-12" />
            <p className="text-sm">{t('player.loadingVideo')}</p>
          </div>
        )}

        {playback.isError && (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center">
            <Lock className="h-10 w-10 opacity-80" />
            <p className="text-sm font-medium">
              {isLocked ? t('player.lockedMessage') : t('player.videoError')}
            </p>
          </div>
        )}

        {src && isFile && (
          <>
            {/* No download button, no picture-in-picture (a floating window
                would leave the watermark behind), no "save video as". */}
            <video
              ref={video}
              key={lessonId}
              src={src}
              poster={playback.data?.poster ?? undefined}
              controls
              controlsList="nodownload noremoteplayback"
              disablePictureInPicture
              onContextMenu={(event) => event.preventDefault()}
              onLoadedMetadata={() => {
                applyRate()
                applyCaptions()
              }}
              // The browser's own menu (Chrome's ⋮, Safari full screen) changes it too.
              onRateChange={(event) => {
                const next = event.currentTarget.playbackRate
                if (next !== rate) setRate(next)
              }}
              preload="metadata"
              playsInline
              className="absolute inset-0 h-full w-full bg-sombre-950"
            >
              {captionsUrl && (
                <track
                  kind="subtitles"
                  src={captionsUrl}
                  srcLang={playback.data?.locale}
                  label={LOCALE_NAMES[playback.data?.locale as Locale] ?? playback.data?.locale}
                  default={captionsOn}
                />
              )}
              {t('player.videoTitle')}
            </video>
            {watermarkText && <WatermarkOverlay text={watermarkText} />}
          </>
        )}

        {src && !isFile && (
          <>
            <iframe
              key={lessonId}
              src={src}
              title={t('player.videoTitle')}
              className="absolute inset-0 h-full w-full border-0"
              loading="lazy"
              // Not picture-in-picture: the floating window would leave the watermark behind.
              allow="accelerometer; autoplay; encrypted-media; gyroscope"
              allowFullScreen
            />
            {watermarkText && <WatermarkOverlay text={watermarkText} />}
          </>
        )}
      </div>
      {/* Under the picture, not over it: the speed and the subtitles, which an
          iPhone's own controls do not offer until full screen. */}
      {src && isFile && (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
          {captionsUrl ? (
            <button
              type="button"
              onClick={toggleCaptions}
              aria-pressed={captionsOn}
              className={cn(
                'inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors [@media(pointer:coarse)]:h-10',
                captionsOn ? 'bg-primary text-primary-foreground' : 'bg-foreground/[0.05] text-foreground/75 hover:bg-foreground/[0.08]',
              )}
            >
              <Captions className="h-4 w-4" aria-hidden />
              {t('player.captions')}
            </button>
          ) : (
            <span />
          )}
          <div role="group" aria-label={t('player.speed')} className="flex items-center gap-1">
            <Gauge className="me-1 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
            <span className="me-1 hidden text-sm text-muted-foreground sm:inline">{t('player.speed')}</span>
            {RATES.map((value) => (
              <button
                key={value}
                type="button"
                onClick={() => chooseRate(value)}
                aria-pressed={rate === value}
                className={cn(
                  'h-9 min-w-[2.75rem] rounded-lg px-2 text-sm font-medium tabular-nums transition-colors [@media(pointer:coarse)]:h-10',
                  rate === value ? 'bg-primary text-primary-foreground' : 'text-foreground/70 hover:bg-foreground/[0.06]',
                )}
              >
                <span dir="ltr">{rateLabel(value)}</span>
              </button>
            ))}
          </div>
        </div>
      )}
      {otherLanguage && (
        <p className="flex items-center gap-2 text-xs text-muted-foreground">
          <Languages className="h-3.5 w-3.5 shrink-0" aria-hidden />
          {t('player.videoInLanguage', { language: otherLanguage })}
        </p>
      )}
    </div>
  )
}
