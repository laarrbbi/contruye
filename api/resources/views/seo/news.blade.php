{{-- The news as a crawler reads it; the app replaces it on load. --}}
@include('seo._header')
<main class="container space-y-8 py-12">
    <header class="space-y-3">
        <h1 class="text-3xl font-extrabold text-primary">{{ $title }}</h1>
        <p class="max-w-3xl leading-relaxed text-muted-foreground">{{ $lead }}</p>
    </header>
    @if ($posts->isEmpty())
        <p class="text-muted-foreground">{{ $empty }}</p>
    @else
        <ul class="space-y-8">
            @foreach ($posts as $post)
                <li class="space-y-2">
                    @if ($date($post))
                        <p class="text-sm text-muted-foreground"><time datetime="{{ $post->published_at->toDateString() }}">{{ $date($post) }}</time></p>
                    @endif
                    {{-- dir="auto": a piece not yet translated reads in Spanish, in its own direction. --}}
                    <h2 dir="auto" class="text-xl font-bold"><a href="{{ $postPath($post) }}">{{ $post->localizedTitle($locale) }}</a></h2>
                    @if ($post->localizedSummary($locale))
                        <p dir="auto" class="max-w-3xl leading-relaxed text-muted-foreground">{{ $post->localizedSummary($locale) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</main>
