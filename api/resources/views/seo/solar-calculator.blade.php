{{-- The solar calculator's page as a crawler reads it; the app replaces it on
     load with the same words and the live calculator. --}}
<div class="landing-type">
@include('seo._header')
<main class="bg-creme-100 text-plume">
    <section class="container space-y-5 py-16">
        <p class="etiquette text-plume/70">{{ $s('eyebrow') }} · {{ $s('free') }}</p>
        <h1 class="titre text-[clamp(2.4rem,1.4rem+3.6vw,4.2rem)]">{{ $s('pageTitle') }}</h1>
        <p class="max-w-3xl text-lg leading-relaxed text-plume/85">{{ $s('lead') }}</p>
    </section>

    <section class="container space-y-4 py-10">
        <h2 class="text-2xl font-semibold">{{ $s('how.title') }}</h2>
        <ul class="max-w-3xl space-y-2 leading-relaxed">
            <li><strong>{{ $s('how.itpTerm') }}:</strong> {{ $s('how.itp') }}</li>
            <li><strong>{{ $s('how.ajdTerm') }}:</strong> {{ $s('how.ajd') }}</li>
            <li><strong>{{ $s('how.costTerm') }}</strong> = {{ $s('how.cost') }}</li>
            <li><strong>{{ $s('how.perM2Term') }}</strong> = {{ $s('how.perM2') }}</li>
        </ul>
    </section>

    <section class="container space-y-4 py-10">
        <h2 class="text-2xl font-semibold">{{ $s('rates.title') }}</h2>
        @if ($reviewedAt)
            <p class="max-w-3xl text-sm text-plume/70">{{ $s('rates.lead') }}</p>
        @endif
        <table>
            <thead>
                <tr>
                    <th scope="col">{{ $s('rates.community') }}</th>
                    <th scope="col">{{ $s('rates.itp') }}</th>
                    <th scope="col">{{ $s('rates.ajd') }}</th>
                    <th scope="col">{{ $s('rates.note') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($regions as $region)
                    <tr>
                        <th scope="row">{{ $region['name'] }}</th>
                        <td>{{ $percent($region['itp']) }}</td>
                        <td>{{ $percent($region['ajd']) }}</td>
                        {{-- The package's notes are Spanish: said so, for every language. --}}
                        <td lang="es">{{ $region['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="max-w-3xl text-sm text-plume/70">{{ $s('disclaimer') }}</p>
    </section>

    <section class="container space-y-4 py-10">
        <h2 class="text-2xl font-semibold">{{ $s('faq.title') }}</h2>
        @foreach ($faq as $item)
            <div>
                <h3 class="font-semibold">{{ $item['question'] }}</h3>
                <p class="mt-1 max-w-3xl leading-relaxed text-plume/80">{{ $item['answer'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="container space-y-3 py-12">
        <h2 class="text-2xl font-semibold">{{ $s('course.title') }}</h2>
        <p class="max-w-3xl leading-relaxed text-plume/80">{{ $s('course.body') }}</p>
        <p><a href="{{ $courseUrl }}" class="font-semibold underline">{{ $s('course.cta') }}</a></p>
    </section>
</main>
</div>
