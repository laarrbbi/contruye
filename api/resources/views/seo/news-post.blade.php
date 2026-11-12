{{-- One piece of news as a crawler reads it; the app replaces it on load. --}}
@include('seo._header')
<main class="container max-w-3xl space-y-6 py-12">
    <p><a href="{{ $back }}" class="text-sm underline">{{ $backLabel }}</a></p>
    <article lang="{{ $lang }}" dir="{{ in_array($lang, config('locales.rtl', []), true) ? 'rtl' : 'ltr' }}" class="space-y-5">
        <header class="space-y-3">
            @if ($date)
                <p class="text-sm text-muted-foreground"><time datetime="{{ $post->published_at->toDateString() }}">{{ $date }}</time></p>
            @endif
            <h1 class="text-3xl font-extrabold text-primary">{{ $title }}</h1>
            @if ($post->localizedSummary($locale))
                <p class="text-lg leading-relaxed text-muted-foreground">{{ $post->localizedSummary($locale) }}</p>
            @endif
        </header>
        @if ($post->imageUrl())
            <img src="{{ $post->imageUrl() }}" alt="" class="aspect-[16/9] w-full rounded-2xl object-cover" />
        @endif
        {{-- Markdown rendered on the server, raw HTML stripped (App\Support\Markdown). --}}
        <div class="md-content">{!! $html !!}</div>
    </article>
</main>
