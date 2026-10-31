import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
// The site's typeface: Poppins (a rounded geometric, a free equivalent of
// Circular). Self-hosted, a few weights, font-display: swap — no third-party
// request, no invisible text.
import '@fontsource/poppins/latin-400.css'
import '@fontsource/poppins/latin-500.css'
import '@fontsource/poppins/latin-600.css'
import '@fontsource/poppins/latin-700.css'
import '@fontsource/poppins/latin-800.css'
// Russian and Ukrainian, Arabic: only the subsets for those scripts, whose
// unicode-range keeps them off the network until such a letter is on screen.
import '@fontsource/montserrat/cyrillic-400.css'
import '@fontsource/montserrat/cyrillic-500.css'
import '@fontsource/montserrat/cyrillic-600.css'
import '@fontsource/montserrat/cyrillic-700.css'
import '@fontsource/montserrat/cyrillic-800.css'
import '@fontsource/cairo/arabic-400.css'
import '@fontsource/cairo/arabic-600.css'
import '@fontsource/cairo/arabic-700.css'
import i18n, { i18nReady } from './i18n'
import './index.css'
import App from './App'
import { prefetchBuyingPath } from '@/lib/buyingPath'

// After a deploy, a tab still open on the previous release asks for page chunks
// that no longer exist. index.html is never cached, so one reload brings the
// new release; without it the visitor gets the crash screen. The guard stops a
// reload loop if a chunk is missing for some other reason.
window.addEventListener('vite:preloadError', (event) => {
  try {
    const last = Number(sessionStorage.getItem('chunkReloadAt') ?? 0)
    if (Date.now() - last < 10_000) return
    sessionStorage.setItem('chunkReloadAt', String(Date.now()))
  } catch {
    return
  }
  event.preventDefault()
  window.location.reload()
})

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
})

// Course titles, module titles and campaign labels come localized from the API
// (Accept-Language): cached responses are in the old language after a switch,
// whether it came from the switcher or from a language URL.
i18n.on('languageChanged', () => void queryClient.invalidateQueries())

i18nReady.then(() =>
  createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
    </QueryClientProvider>
  </StrictMode>,
  ),
).then(() => {
  // After the first paint, when the browser has nothing better to do.
  const idle = window.requestIdleCallback ?? ((cb: () => void) => window.setTimeout(cb, 1500))
  idle(() => prefetchBuyingPath())
})
