@use('App\Support\Money')
@use('App\Support\SpaStrings')
{{--
    What a crawler that does not run JavaScript reads, and what a visitor on a
    slow connection sees until the app takes over (React replaces the content
    of #root on its first render). Every string is the app's own or the
    course's public data — module and lesson titles only: module
    descriptions belong to buyers.
--}}
@php
    $s = fn (string $key) => SpaStrings::get($locale, $key);
    // A promotion the demo seeder invented announces itself by its label
    // ([DEMO]): it is not shown on the sales page, here no more than in the app.
    $campaign = $course->activeCampaign();
    if ($campaign !== null && str_starts_with(ltrim((string) $campaign->label), '[DEMO]')) {
        $campaign = null;
    }
    $price = $campaign !== null ? $campaign->apply((int) $course->price_eur) : (int) $course->price_eur;
@endphp
{{-- The page's selection colour and tap highlight from the first paint. --}}
<div class="landing-type">
@include('seo._header')
<main>
    {{-- The first screen as the app draws it: cream paper, the title in
         Poppins, the amber button with the price beside it. --}}
    <section class="bg-creme-100 text-plume">
        <div class="container py-16 sm:py-24">
            <p class="etiquette text-plume/70">{{ $s('landing.s1.eyebrow') }}</p>
            <h1 class="titre mt-5 max-w-[14ch] text-[clamp(2.9rem,1rem+6.2vw,6rem)]">{{ $s('landing.heroV2.title') }}</h1>
            <p class="mt-6 max-w-[31rem] text-lg leading-relaxed text-plume/85">{{ $s('landing.hero.title') }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-6">
                <a href="{{ $checkoutUrl }}" class="btn-or">{{ $s('landing.heroV2.cta') }}</a>
                <p class="titre text-[2.25rem] leading-none">
                    {{ Money::format($price, 'EUR', $locale) }}
                    @if ($campaign !== null)
                        <s class="ms-2 text-base text-plume/70">{{ Money::format((int) $course->price_eur, 'EUR', $locale) }}</s>
                    @endif
                </p>
            </div>
            <p class="mt-5 text-sm text-plume/70">{{ $paymentNote }}</p>
        </div>
    </section>

    <section class="container space-y-6 py-12">
        <h2 class="text-2xl font-semibold text-primary">{{ $course->localizedTitle($locale) }}</h2>
        {{-- dir="auto": material not yet translated reads in Spanish, in its own direction. --}}
        <p dir="auto" class="max-w-3xl leading-relaxed text-muted-foreground">{{ $course->localizedDescription($locale) }}</p>
        <ol class="space-y-4">
            @foreach ($course->modules as $module)
                <li>
                    <h3 dir="auto" class="font-semibold">@if ($module->kind === 'phase'){{ $module->order }}. @endif{{ $module->localizedTitle($locale) }}</h3>
                    @if ($module->lessons->isNotEmpty())
                        <ul class="mt-1 list-disc space-y-0.5 ps-6 text-sm text-muted-foreground">
                            @foreach ($module->lessons as $lesson)
                                <li dir="auto">{{ $lesson->localizedTitle($locale) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>

    @if ($faq !== [])
        <section class="container space-y-4 py-12">
            <h2 class="text-2xl font-semibold text-primary">{{ $s('course.faq.title') }}</h2>
            @foreach ($faq as $item)
                <div>
                    <h3 class="font-semibold">{{ $item['question'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{!! $item['answer_html'] !!}</p>
                </div>
            @endforeach
        </section>
    @endif
</main>
</div>
