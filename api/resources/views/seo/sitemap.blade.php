@use('App\Support\LocalizedUrl')
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($locales as $locale)
    <url>
        <loc>{{ LocalizedUrl::to($page['path'], $locale) }}</loc>
@if ($page['lastmod'])
        <lastmod>{{ $page['lastmod']->toAtomString() }}</lastmod>
@endif
@foreach (LocalizedUrl::alternates($page['path']) as $hreflang => $href)
        <xhtml:link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
@endforeach
    </url>
@endforeach
@endforeach
</urlset>
