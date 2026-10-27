import { Component, type ErrorInfo, type ReactNode } from 'react'
import i18n from '@/i18n'

/**
 * Last line of defence: a rendering error anywhere used to leave a white page.
 * This shows what happened and a way out, in the visitor's language. It is a
 * class because React only catches render errors in class components.
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Visible in the browser console and in any error tracker added later.
    console.error('Render error', error, info.componentStack)
  }

  render() {
    if (!this.state.failed) return this.props.children

    const t = i18n.t.bind(i18n)
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <h1 className="text-2xl font-bold">{t('crash.title')}</h1>
        <p className="max-w-md text-muted-foreground">{t('crash.body')}</p>
        <div className="flex flex-wrap justify-center gap-3">
          <button
            type="button"
            onClick={() => window.location.reload()}
            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-transform duration-150 ease-out-strong active:scale-[0.97]"
          >
            {t('crash.reload')}
          </button>
          <a href="/" className="rounded-md border px-4 py-2 text-sm font-medium">
            {t('notFound.home')}
          </a>
        </div>
      </div>
    )
  }
}
