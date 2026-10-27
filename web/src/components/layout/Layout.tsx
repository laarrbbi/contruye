import { Suspense } from 'react'
import { Outlet } from 'react-router-dom'
import { RouteFallback } from '@/components/RouteFallback'
import { Footer } from './Footer'
import { Header } from './Header'

export function Layout() {
  return (
    // A bar fixed to the bottom of a phone screen (the sales page's) is marked
    // data-bottom-bar; the space it covers is reserved after the footer, so the
    // footer's last lines (the RC, the NIF) are never under it. The bar is at
    // most 99 px tall (French, or a 320 px screen).
    <div className="flex min-h-screen flex-col max-md:has-[[data-bottom-bar]]:pb-28">
      <Header />
      <main className="flex-1">
        {/* Inside the layout, so the header stays put while a section loads. */}
        <Suspense fallback={<RouteFallback />}>
          <Outlet />
        </Suspense>
      </main>
      <Footer />
    </div>
  )
}
