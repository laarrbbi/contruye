import { Suspense } from 'react'
import { Outlet } from 'react-router-dom'
import { RouteFallback } from '@/components/RouteFallback'
import { Footer } from './Footer'
import { Header } from './Header'

export function Layout() {
  return (
    // A bar fixed to the bottom of a phone screen (the sales page's) is marked
    // data-bottom-bar; the space it covers is reserved after the footer, so the
    // footer's last lines (the NIF, the registry entry) are never under it.
    // The bar is at most 99 px tall (Spanish, or a 320 px screen), plus the
    // home indicator's strip, which the bar pads itself out of.
    // The redesigned hero keeps its bar up to the lg breakpoint (data-bottom-bar="lg").
    <div className="flex min-h-svh flex-col max-md:has-[[data-bottom-bar]]:pb-[calc(7rem+env(safe-area-inset-bottom))] max-lg:has-[[data-bottom-bar=lg]]:pb-[calc(7rem+env(safe-area-inset-bottom))] [&:has([data-pied])>footer]:hidden [&:has([data-pied])]:bg-[#04090F] [&:has([data-plein])>footer]:hidden [&:has([data-plein])]:bg-sombre-950">
      <Header />
      {/* A safety net: nothing inside can make the page scroll sideways on a
          phone (clip, not hidden: sticky elements keep working). */}
      <main className="flex-1 overflow-x-clip">
        {/* Inside the layout, so the header stays put while a section loads. */}
        <Suspense fallback={<RouteFallback />}>
          <Outlet />
        </Suspense>
      </main>
      <Footer />
    </div>
  )
}
