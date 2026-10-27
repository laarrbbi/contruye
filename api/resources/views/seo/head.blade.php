{{-- Replaces the <!-- seo:start --> … <!-- seo:end --> block of the app's
     index.html on public pages (SeoPageController). --}}
<title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}" />
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />
    <link rel="canonical" href="{{ $canonical }}" />
@foreach ($alternates as $hreflang => $href)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
@endforeach
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Ebni Academy" />
    <meta property="og:locale" content="{{ $ogLocale }}" />
@foreach ($ogLocaleAlternates as $alternate)
    <meta property="og:locale:alternate" content="{{ $alternate }}" />
@endforeach
    <meta property="og:url" content="{{ $canonical }}" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta property="og:image" content="{{ $image }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="{{ $imageAlt }}" />
    <meta name="twitter:card" content="summary_large_image" />
@foreach ($verification as $name => $value)
    <meta name="{{ $name }}" content="{{ $value }}" />
@endforeach
    <script type="application/ld+json">{!! $jsonLd !!}</script>
