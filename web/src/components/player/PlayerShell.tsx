import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ListVideo, X } from 'lucide-react'
import type { LearnData } from '@/api/learn'
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
    <div className="lg:grid lg:grid-cols-[320px_1fr]">
      {/* Desktop sidebar */}
      <aside className="sticky top-16 hidden h-[calc(100vh-4rem)] border-e bg-background lg:block">{sidebar}</aside>

      {/* Mobile slide-over */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
          <div className="absolute inset-y-0 start-0 flex w-80 max-w-[85vw] flex-col bg-background shadow-xl">
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
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
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

          {children}
        </div>
      </div>
    </div>
  )
}
