import { createContext, useContext, useEffect } from 'react'

export interface PageHead {
  title: string | null
  description: string | null
}

/** Provided by PageTitleProvider, the one place that writes the page's title and description. */
export const PageTitleContext = createContext<(head: PageHead) => void>(() => {})

/**
 * Names the current page in the browser tab, the history and what a screen
 * reader announces on navigation: "Connexion — Ebni Academy". Every page used
 * to carry the site's own title, so ten open tabs all read the same. Pages
 * that do not call this keep the site's title.
 *
 * The public pages also pass their description: the same words the server
 * puts in their head (api SeoPageController), so the page Google renders says
 * what the page it fetched said.
 */
export function usePageTitle(title: string | null | undefined, description?: string | null) {
  const setHead = useContext(PageTitleContext)

  useEffect(() => {
    setHead({ title: title || null, description: description || null })
    return () => setHead({ title: null, description: null })
  }, [setHead, title, description])
}
