import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Tag, Timer } from 'lucide-react'
import type { Campaign } from '@/api/courses'
import { cn } from '@/lib/utils'

interface Remaining {
  days: number
  hours: number
  minutes: number
  seconds: number
  done: boolean
}

function remainingUntil(iso: string): Remaining {
  const ms = new Date(iso).getTime() - Date.now()
  if (!Number.isFinite(ms) || ms <= 0) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0, done: true }
  }
  const total = Math.floor(ms / 1000)
  return {
    days: Math.floor(total / 86400),
    hours: Math.floor((total % 86400) / 3600),
    minutes: Math.floor((total % 3600) / 60),
    seconds: total % 60,
    done: false,
  }
}

function Unit({ value, label }: { value: number; label: string }) {
  return (
    <span className="flex min-w-[3rem] flex-col items-center rounded-md bg-background/15 px-2 py-1">
      <span className="text-lg font-extrabold leading-none tabular-nums">
        {String(value).padStart(2, '0')}
      </span>
      <span className="mt-0.5 text-[10px] uppercase tracking-wide opacity-80">{label}</span>
    </span>
  )
}

/**
 * Countdown to a promotion's real end date.
 *
 * The deadline comes from the server and is the same for every visitor — it
 * does not restart on reload. When it reaches zero the banner disappears and
 * the course data is refetched, because the price has genuinely gone back up.
 */
export function CampaignBanner({
  campaign,
  slug,
  className,
}: {
  campaign: Campaign | null | undefined
  slug?: string
  className?: string
}) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [remaining, setRemaining] = useState<Remaining | null>(() =>
    campaign ? remainingUntil(campaign.ends_at) : null,
  )

  useEffect(() => {
    if (!campaign) {
      setRemaining(null)
      return
    }
    setRemaining(remainingUntil(campaign.ends_at))
    const id = window.setInterval(() => {
      const next = remainingUntil(campaign.ends_at)
      setRemaining(next)
      if (next.done) {
        window.clearInterval(id)
        // The promotion is over: pull the real (higher) price.
        qc.invalidateQueries({ queryKey: ['course', slug] })
        qc.invalidateQueries({ queryKey: ['quote'] })
      }
    }, 1000)
    return () => window.clearInterval(id)
  }, [campaign, slug, qc])

  if (!campaign || !remaining || remaining.done) return null

  return (
    <div
      className={cn(
        'flex flex-wrap items-center justify-center gap-x-4 gap-y-2 bg-primary px-4 py-2.5 text-primary-foreground',
        className,
      )}
    >
      <span className="flex items-center gap-2 text-sm font-semibold">
        <Tag className="h-4 w-4 shrink-0" />
        <bdi>{campaign.label}</bdi>
        <span className="rounded-full bg-background/20 px-2 py-0.5" dir="ltr">
          −{campaign.percent_off}%
        </span>
      </span>

      <span className="flex items-center gap-2">
        <Timer className="h-4 w-4 shrink-0 opacity-80" />
        <span className="text-xs opacity-80">{t('campaign.endsIn')}</span>
        {/* Fixed LTR: a duration reads the same way in both languages. */}
        <span className="flex items-center gap-1" dir="ltr">
          {remaining.days > 0 && <Unit value={remaining.days} label={t('campaign.days')} />}
          <Unit value={remaining.hours} label={t('campaign.hours')} />
          <Unit value={remaining.minutes} label={t('campaign.minutes')} />
          <Unit value={remaining.seconds} label={t('campaign.seconds')} />
        </span>
      </span>
    </div>
  )
}
