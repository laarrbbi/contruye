{{-- Nicolás, the course's AI assistant, as a crawler reads it: what it
     will do and that it is not open yet; the app replaces it on load with the
     same words, the sign-up, and the chat once it opens. --}}
@include('seo._header')
<main class="container max-w-3xl space-y-6 py-16">
    <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ $s('eyebrow') }}</p>
    <h1 class="text-3xl font-extrabold text-primary">{{ $s('title') }}</h1>
    <p class="text-lg leading-relaxed text-muted-foreground">{{ $s('lead') }}</p>
    <h2 class="text-xl font-bold">{{ $s('featuresTitle') }}</h2>
    <ul class="space-y-3">
        @foreach ($features as $feature)
            <li><strong>{{ $feature['title'] }}.</strong> {{ $feature['body'] }}</li>
        @endforeach
    </ul>
    <p class="text-sm text-muted-foreground">{{ $s('disclaimer') }}</p>
    <p class="leading-relaxed">{{ $s('notify') }}</p>
    <p><a href="{{ $courseUrl }}" class="font-semibold underline">{{ $s('current') }}</a></p>
</main>
