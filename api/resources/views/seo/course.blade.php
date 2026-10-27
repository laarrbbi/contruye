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
    $campaign = $course->activeCampaign();
    $price = $campaign !== null ? $campaign->apply((int) $course->price_eur) : (int) $course->price_eur;
@endphp
@include('seo._header')
<main>
    <section class="bg-primary text-primary-foreground">
        <div class="container space-y-5 py-12 sm:py-16">
            <p class="text-sm font-semibold uppercase tracking-wide">{{ $s('landing.hero.eyebrow') }}</p>
            <h1 class="max-w-3xl text-3xl font-extrabold leading-tight sm:text-4xl">{{ $s('landing.hero.title') }}</h1>
            <p class="max-w-2xl leading-relaxed text-primary-foreground/80">{{ $s('landing.hero.body') }}</p>
            <p class="text-2xl font-extrabold">
                {{ Money::format($price, 'EUR', $locale) }}
                @if ($campaign !== null)
                    <s class="ms-2 text-base font-medium text-primary-foreground/70">{{ Money::format((int) $course->price_eur, 'EUR', $locale) }}</s>
                @endif
            </p>
            <p><a href="{{ $checkoutUrl }}" class="inline-flex rounded-md bg-white px-5 py-3 font-semibold text-primary">{{ $s('landing.hero.ctaPrimary') }}</a></p>
            <p class="text-sm text-primary-foreground/70">{{ $paymentNote }}</p>
        </div>
    </section>

    <section class="container space-y-6 py-12">
        <h2 class="text-2xl font-extrabold text-primary">{{ $course->localizedTitle($locale) }}</h2>
        {{-- dir="auto": material not yet translated reads in Spanish, in its own direction. --}}
        <p dir="auto" class="max-w-3xl leading-relaxed text-muted-foreground">{{ $course->localizedDescription($locale) }}</p>
        <ol class="space-y-4">
            @foreach ($course->modules as $module)
                <li>
                    <h3 dir="auto" class="font-semibold">{{ $module->order }}. {{ $module->localizedTitle($locale) }}</h3>
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
            <h2 class="text-2xl font-extrabold text-primary">{{ $s('course.faq.title') }}</h2>
            @foreach ($faq as $item)
                <div>
                    <h3 class="font-semibold">{{ $item['question'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ $item['answer'] }}</p>
                </div>
            @endforeach
        </section>
    @endif
</main>
