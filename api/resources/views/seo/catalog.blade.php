@use('App\Support\Money')
@use('App\Support\SpaStrings')
{{-- The catalogue as a crawler reads it; the app replaces it on load. --}}
@include('seo._header')
<main class="container space-y-8 py-12">
    <h1 class="text-3xl font-extrabold text-primary">{{ SpaStrings::get($locale, 'app.tagline') }}</h1>
    <ul class="space-y-6">
        @foreach ($courses as $course)
            @php
                $campaign = $course->activeCampaign();
                $price = $campaign !== null ? $campaign->apply((int) $course->price_dzd) : (int) $course->price_dzd;
            @endphp
            <li class="space-y-2">
                <h2 class="text-xl font-bold"><a href="{{ $courseUrl($course) }}">{{ $course->localizedTitle($locale) }}</a></h2>
                <p dir="auto" class="leading-relaxed text-muted-foreground">{{ $course->localizedDescription($locale) }}</p>
                <p class="font-semibold">{{ Money::format($price, 'DZD', $locale) }}</p>
            </li>
        @endforeach
    </ul>
</main>
