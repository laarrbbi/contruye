import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, ArrowRight } from 'lucide-react'
import { newsDate, useNews, type NewsCard } from '@/api/news'
import { TEXTE } from '@/components/landing/kit/type'
import { Button } from '@/components/ui/button'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, isRtl, type Locale } from '@/i18n'
import { NEWS_PATH, localizedPath } from '@/i18n/paths'
import { cn } from '@/lib/utils'

/**
 * The news (Noticias, in the menu): what the owner publishes from the admin,
 * newest first. It starts empty and says so. The server renders the same
 * list for search engines (seo/news).
 */
export function NewsPage() {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  const [params, setParams] = useSearchParams()
  const page = Math.max(1, Number(params.get('page')) || 1)
  const { data, isLoading, isError } = useNews(page)
  usePageTitle(t('news.title'), t('news.seoDescription'))

  const go = (next: number) => {
    setParams(next > 1 ? { page: String(next) } : {})
    window.scrollTo({ top: 0 })
  }

  return (
    <div className="bg-creme-100 text-plume">
      <section data-tone="paper" className="container py-16 lg:py-24">
        <header className="max-w-3xl">
          <p className="etiquette flex items-center gap-3 text-plume/70">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {t('news.eyebrow')}
          </p>
          <h1 className="titre mt-5 text-balance text-[clamp(2.4rem,1.4rem+3.6vw,4.2rem)]">{t('news.title')}</h1>
          <p className={cn(TEXTE, 'mt-6 text-plume/80')}>{t('news.lead')}</p>
        </header>

        <div className="mt-12 lg:mt-16">
          {isLoading ? (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
              {[0, 1, 2].map((i) => (
                <div key={i} className="h-72 animate-pulse rounded-2xl bg-plume/[0.05]" />
              ))}
            </div>
          ) : isError ? (
            <p className="text-destructive">{t('common.error')}</p>
          ) : !data || data.data.length === 0 ? (
            <p className="rounded-2xl border border-dashed border-plume/20 px-6 py-16 text-center text-plume/65">{t('news.empty')}</p>
          ) : (
            <>
              <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {data.data.map((post) => (
                  <li key={post.slug} className="flex">
                    <NewsCardLink post={post} locale={locale} />
                  </li>
                ))}
              </ul>
              {data.meta.last_page > 1 && (
                <nav className="mt-12 flex items-center justify-between gap-4" aria-label={t('news.title')}>
                  <Button variant="outline" disabled={page <= 1} onClick={() => go(page - 1)}>
                    <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                    {t('news.newer')}
                  </Button>
                  <span className="text-sm tabular-nums text-plume/60" dir="ltr">
                    {page} / {data.meta.last_page}
                  </span>
                  <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => go(page + 1)}>
                    {t('news.older')}
                    <ArrowRight className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
                  </Button>
                </nav>
              )}
            </>
          )}
        </div>
      </section>
    </div>
  )
}

function NewsCardLink({ post, locale }: { post: NewsCard; locale: Locale }) {
  const { t } = useTranslation()
  // A piece not translated yet keeps its own language and direction.
  const lang = post.title_lang !== locale ? post.title_lang : undefined
  return (
    <Link
      to={localizedPath(`${NEWS_PATH}/${post.slug}`, locale)}
      className="group flex w-full flex-col overflow-hidden rounded-2xl bg-white/70 ring-1 ring-plume/10 transition-shadow duration-200 hover:shadow-[0_24px_48px_-28px_rgb(20_26_34/0.45)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      {post.image_url && (
        <img src={post.image_url} alt="" loading="lazy" className="aspect-[16/9] w-full object-cover" />
      )}
      <div className="flex flex-1 flex-col p-6">
        <time dateTime={post.published_at} className="text-sm text-plume/60">
          {newsDate(post.published_at, locale)}
        </time>
        <div lang={lang} dir={lang ? (isRtl(lang) ? 'rtl' : 'ltr') : undefined} className="mt-3 flex-1">
          <h2 className="titre text-[1.375rem] leading-snug">{post.title}</h2>
          {post.summary && <p className="mt-3 line-clamp-3 leading-relaxed text-plume/75">{post.summary}</p>}
        </div>
        <span className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-primary">
          {t('news.readMore')}
          <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" aria-hidden />
        </span>
      </div>
    </Link>
  )
}
