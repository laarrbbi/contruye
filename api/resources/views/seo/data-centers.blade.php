{{-- The data centers course, announced, as a crawler reads it; the app
     replaces it on load with the same words and the sign-up. --}}
@include('seo._header')
<main class="container max-w-3xl space-y-6 py-16">
    <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ $s('eyebrow') }}</p>
    <h1 class="text-3xl font-extrabold text-primary">{{ $s('title') }}</h1>
    <p class="text-lg leading-relaxed text-muted-foreground">{{ $s('lead') }}</p>
    <p class="leading-relaxed">{{ $s('notify') }}</p>
    <p><a href="{{ $courseUrl }}" class="font-semibold underline">{{ $s('current') }}</a></p>
</main>
