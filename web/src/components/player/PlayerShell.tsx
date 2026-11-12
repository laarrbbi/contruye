import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ListVideo, X } from 'lucide-react'
import type { LearnData } from '@/api/learn'
import { CurrentOffer } from '@/components/offers/OfferCard'
import { Button } from '@/components/ui/button'
import { PlayerSidebar, type PlayerLocation } from './PlayerSidebar'
import { ProtectedArea } from '@/components/protection/ProtectedArea'
import { ProvinceWelcome } from './ProvinceWelcome'

/**
 * The course player's frame: the page's own column, and the course
 * navigation beside it (a sidebar on the right on desktop, a slide-over from
 * the same side on a phone). Lessons, quizzes, the
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
    // The page on the left, the course's contents on the right (mirrored in
    // Arabic), as course marketplaces lay their players out.
    <div className="lg:grid lg:grid-cols-[minmax(0,1fr)_360px]">
      {/* Mobile slide-over */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
          <div className="theme-night absolute inset-y-0 end-0 flex w-80 max-w-[85vw] flex-col shadow-xl">
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

      {/* Page column. Not a <main>: the site layout already provides it.
          The course material inside it is protected (ProtectedArea). */}
      <ProtectedArea className="min-w-0">
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
          {/* First visit without a province: ask once, then every module opens on it. */}
          <ProvinceWelcome />

          {children}
        </div>
      </ProtectedArea>

      {/* Desktop sidebar */}
      <aside className="theme-night sticky top-16 hidden h-[calc(100vh-4rem)] lg:block">{sidebar}</aside>
    </div>
  )
}
