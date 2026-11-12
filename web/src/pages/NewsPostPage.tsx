import { useEffect } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft } from 'lucide-react'
import { newsDate, useNewsPost } from '@/api/news'
import { MarkdownContent } from '@/components/MarkdownContent'
import { usePageTitle } from '@/hooks/usePageTitle'
import { LOCALES, isRtl, type Locale } from '@/i18n'
import { NEWS_PATH, localizedPath } from '@/i18n/paths'

/**
 * One piece of news, in the reader's language when it has a translation and
 * in Spanish otherwise (and the page says so). The server renders the same
 * piece, with its article data, for search engines (seo/news-post).
 */
export function NewsPostPage() {
  const { t, i18n } = useTranslation()
  const locale = (LOCALES.find((l) => l === i18n.language) ?? 'es') as Locale
  const { slug } = useParams()
  const { data: post, isLoading, isError } = useNewsPost(slug)
  usePageTitle(post?.title ?? t('news.title'), post?.summary ?? undefined)
  const back = localizedPath(NEWS_PATH, locale)

  // Article data, unless the server already wrote it into the page's head.
  useEffect(() => {
    if (!post || document.head.querySelector('script[type="application/ld+json"]')) return
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.dataset.page = 'news'
    script.text = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'NewsArticle',
      headline: post.title.slice(0, 110),
      description: post.summary ?? undefined,
      datePublished: post.published_at,
      inLanguage: post.body_lang,
      image: post.image_url ? [post.image_url] : undefined,
      mainEntityOfPage: window.location.href,
      publisher: { '@type': 'Organization', name: 'Construye Academy' },
    }).replace(/</g, '\\u003c')
    document.head.appendChild(script)
    return () => script.remove()
  }, [post])

  if (isLoading) {
    return <div className="container min-h-[60svh] py-16" aria-busy="true" />
  }

  if (isError || !post) {
    return (
      <div className="container flex min-h-[60svh] flex-col items-center justify-center gap-5 text-center">
        <p className="text-muted-foreground">{t('news.notFound')}</p>
        <Link to={back} className="lien-edito text-sm">
          {t('news.back')}
        </Link>
      </div>
    )
  }

  const lang = post.body_lang
  const foreign = lang !== locale

  return (
    <div className="bg-creme-100 text-plume">
      <article data-tone="paper" className="container max-w-3xl py-12 lg:py-20">
        <Link to={back} className="-my-2 -ms-1 inline-flex items-center gap-1.5 rounded-md px-1 py-3 text-sm text-plume/65 transition-colors hover:text-plume">
          <ArrowLeft className="h-4 w-4 rtl:-scale-x-100" aria-hidden />
          {t('news.back')}
        </Link>

        <header className="mt-8">
          <time dateTime={post.published_at} className="etiquette flex items-center gap-3 text-plume/70">
            <span aria-hidden className="h-px w-8 shrink-0 bg-or" />
            {newsDate(post.published_at, locale)}
          </time>
          <div lang={foreign ? lang : undefined} dir={foreign ? (isRtl(lang) ? 'rtl' : 'ltr') : undefined}>
            <h1 className="titre mt-5 text-balance text-[clamp(2rem,1.3rem+2.8vw,3.5rem)]">{post.title}</h1>
            {post.summary && <p className="mt-5 text-pretty text-lg leading-relaxed text-plume/80 lg:text-xl">{post.summary}</p>}
          </div>
          {foreign && <p className="mt-4 text-sm text-plume/60">{t('news.inSpanish')}</p>}
        </header>

        {post.image_url && <img src={post.image_url} alt="" className="mt-10 aspect-[16/9] w-full rounded-2xl object-cover ring-1 ring-plume/10" />}

        <MarkdownContent html={post.body_html} lang={foreign ? lang : undefined} className="mt-10 text-base leading-relaxed [&_p]:text-[1.0625rem]" />
      </article>
    </div>
  )
}
