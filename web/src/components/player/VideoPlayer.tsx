import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { Languages, Lock, PlayCircle } from 'lucide-react'
import { LOCALE_NAMES, type Locale } from '@/i18n/locales'
import { usePlayback } from '@/api/courses'
import { cn } from '@/lib/utils'
import { WatermarkOverlay } from './WatermarkOverlay'

interface VideoPlayerProps {
  lessonId: number
  /** Resume position in seconds — appended to the signed embed URL when > 0. */
  startAt?: number
  /** Watermark text (viewer e-mail). Falls back to the playback payload. */
  watermark?: string | null
  className?: string
}

/**
 * Signed Bunny Stream embed for a lesson, with loading skeleton, locked /
 * error states and a drifting watermark overlay.
 */
export function VideoPlayer({ lessonId, startAt = 0, watermark, className }: VideoPlayerProps) {
  const { t, i18n } = useTranslation()
  const playback = usePlayback(lessonId)
  // The lesson has no version in the student's language: say which one plays.
  const otherLanguage = playback.data && playback.data.locale !== i18n.language ? LOCALE_NAMES[playback.data.locale as Locale] : null

  const src = useMemo(() => {
    if (!playback.data?.url) return null
    const resume = Math.floor(startAt)
    return resume > 0 ? `${playback.data.url}&t=${resume}` : playback.data.url
  }, [playback.data?.url, startAt])

  const status = isAxiosError(playback.error) ? playback.error.response?.status : undefined
  const isLocked = status === 401 || status === 403

  const watermarkText = watermark ?? playback.data?.watermark ?? null

  return (
    <div className="space-y-2">
      <div
        className={cn(
          'relative aspect-video w-full overflow-hidden rounded-lg bg-foreground/95 text-background',
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

        {src && (
          <>
            <iframe
              key={lessonId}
              src={src}
              title={t('player.videoTitle')}
              className="absolute inset-0 h-full w-full border-0"
              loading="lazy"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
            {watermarkText && <WatermarkOverlay text={watermarkText} />}
          </>
        )}
      </div>
      {otherLanguage && (
        <p className="flex items-center gap-2 text-xs text-muted-foreground">
          <Languages className="h-3.5 w-3.5 shrink-0" aria-hidden />
          {t('player.videoInLanguage', { language: otherLanguage })}
        </p>
      )}
    </div>
  )
}
