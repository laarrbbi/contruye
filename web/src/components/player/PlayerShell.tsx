import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ListVideo, X } from 'lucide-react'
import type { LearnData } from '@/api/learn'
import { CurrentOffer } from '@/components/offers/OfferCard'
import { Button } from '@/components/ui/button'
import { PlayerSidebar, type PlayerLocation } from './PlayerSidebar'

/**
 * The course player's frame: the course navigation (a sidebar on desktop, a
 * slide-over on a phone) and the page's own column. Lessons, quizzes, the
 * course documents and Financials all live in it.
 */
export function PlayerShell({
  data,
  location,
  children,
}: {
  data: LearnData
  location: PlayerLocation
  children: ReactNode
}) {
  const { t } = useTranslation()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const locationKey = JSON.stringify(location)

  // Close the slide-over whenever the student moves somewhere else.
  useEffect(() => {
    setSidebarOpen(false)
  }, [locationKey])

  const sidebar = <PlayerSidebar data={data} location={location} onNavigate={() => setSidebarOpen(false)} />

  return (
    <div className="lg:grid lg:grid-cols-[340px_1fr]">
      {/* Desktop sidebar */}
      <aside className="theme-night sticky top-16 hidden h-[calc(100vh-4rem)] lg:block">{sidebar}</aside>

      {/* Mobile slide-over */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
          <div className="theme-night absolute inset-y-0 start-0 flex w-80 max-w-[85vw] flex-col shadow-xl">
            <div className="flex items-center justify-between border-b px-4 py-3">
              <span className="text-sm font-semibold">{t('player.contents')}</span>
              <Button variant="ghost" size="icon" onClick={() => setSidebarOpen(false)}>
                <X className="h-4 w-4" />
                <span className="sr-only">{t('common.cancel')}</span>
              </Button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">{sidebar}</div>
          </div>
        </div>
      )}

      {/* Page column. Not a <main>: the site layout already provides it. */}
      <div className="min-w-0">
        <div className="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-8 lg:py-12">
          {/* Mobile toolbar */}
          <div className="flex items-center gap-2 lg:hidden">
            <Button variant="outline" size="sm" onClick={() => setSidebarOpen(true)}>
              <ListVideo className="h-4 w-4" />
              {t('player.showContents')}
            </Button>
            <span className="text-xs text-muted-foreground">
              {t('player.progressPercent', { percent: data.course.progress_percent })}
            </span>
          </div>

          <CurrentOffer variant="compact" />

          {children}
        </div>
      </div>
    </div>
  )
}
