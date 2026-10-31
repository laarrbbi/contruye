<?php

use App\Models\Campaign;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Review;
use App\Services\PaymentMethods;
use App\Support\SpaStrings;

/*
 * The public pages as search engines and link previews read them, without
 * running JavaScript. Every URL used to return the same empty app shell:
 * Bing, Facebook and WhatsApp saw no text, and Google, rendering with an
 * en-US browser, indexed the Spanish site in English. Each public page now
 * leaves the server with its own head, structured data and content, in the
 * language of its URL.
 */

beforeEach(function () {
    // The source template: the same seo markers the build keeps.
    config(['seo.spa_index' => base_path('../web/index.html'), 'app.url' => 'https://construye.example']);
});

/** @return array<string, mixed> */
function seoGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
    $graph = json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR)['@graph'];

    return collect($graph)->keyBy('@type')->all();
}

function mainCourse(array $attributes = []): Course
{
    $course = Course::factory()->create(array_merge([
        'slug' => config('seo.main_course'),
        'title_es' => 'Promoción inmobiliaria en España',
        'title_en' => 'Real-estate development in Spain',
        'description_es' => 'De la compra del solar a la entrega de llaves.',
        'price_eur' => 490,
    ], $attributes));

    $module = Module::factory()->create([
        'course_id' => $course->id,
        'order' => 1,
        'title_es' => 'El solar: encontrarlo y comprarlo',
        // As the course is seeded: the material is not translated yet.
        'title_en' => null,
        'description_es' => 'TEXTO DE PAGO del módulo, reservado a los compradores.',
    ]);
    Lesson::factory()->create(['module_id' => $module->id, 'title_es' => 'Consultar la nota simple', 'title_en' => null]);

    return $course;
}

it('serves the home page in Spanish with its own head, whatever the browser speaks', function () {
    mainCourse();

    $html = $this->withHeader('Accept-Language', 'en-US')->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="es" dir="ltr">')
        ->toContain('<title>'.SpaStrings::get('es', 'seo.home.title').' — Construye Academy</title>')
        ->toContain('<meta name="description" content="'.e(str_replace('{{modules}}', '1', SpaStrings::get('es', 'seo.home.description'))).'" />')
        ->toContain('<link rel="canonical" href="https://construye.example/" />')
        ->toContain('<link rel="alternate" hreflang="es" href="https://construye.example/" />')
        ->toContain('<link rel="alternate" hreflang="en" href="https://construye.example/en" />')
        ->toContain('<link rel="alternate" hreflang="x-default" href="https://construye.example/" />')
        ->toContain('content="index, follow')
        ->not->toContain('noindex')
        // The page's content, readable without JavaScript.
        ->toContain('<h1')
        ->toContain(e(SpaStrings::get('es', 'landing.hero.title')))
        ->toContain('El solar: encontrarlo y comprarlo')
        ->toContain('Consultar la nota simple')
        // The app still boots from the same shell.
        ->toContain('<script type="module" src="/src/main.tsx"></script>');
});

it('serves /en in English, whatever the browser speaks', function () {
    mainCourse();

    $english = $this->withHeader('Accept-Language', 'es-ES')->get('/en')->assertOk()->getContent();
    expect($english)
        ->toContain('<html lang="en" dir="ltr">')
        ->toContain('<title>'.SpaStrings::get('en', 'seo.home.title').' — '.SpaStrings::get('en', 'app.name').'</title>')
        ->toContain('<link rel="canonical" href="https://construye.example/en" />')
        ->toContain('Real-estate development in Spain');
    expect(seoGraph($english)['FAQPage']['mainEntity'][0]['name'])
        ->toBe(SpaStrings::get('en', 'course.faq.'.SpaStrings::faqKeys()[0].'.q'));
});

it('serves French, Arabic, Russian and Ukrainian under their own prefix, Arabic right to left', function () {
    mainCourse();

    foreach (['fr', 'ar', 'ru', 'uk'] as $locale) {
        $html = $this->withHeader('Accept-Language', 'es-ES')->get("/{$locale}")->assertOk()->getContent();

        expect($html)
            ->toContain('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">')
            ->toContain('<title>'.e(SpaStrings::get($locale, 'seo.home.title')).' — '.e(SpaStrings::get($locale, 'app.name')).'</title>')
            ->toContain('<link rel="canonical" href="https://construye.example/'.$locale.'" />')
            ->toContain('<link rel="alternate" hreflang="'.$locale.'" href="https://construye.example/'.$locale.'" />')
            ->toContain(e(SpaStrings::get($locale, 'landing.hero.title')));
    }
});

it('falls back to the Spanish material where the English is not written yet', function () {
    // The interface is translated; the course material is authored in Spanish
    // and an English visitor reads it rather than a blank.
    mainCourse(['title_en' => null]);

    expect($this->get('/en')->assertOk()->getContent())
        ->toContain('Promoción inmobiliaria en España')
        ->toContain('El solar: encontrarlo y comprarlo');
});

it('describes the course for search engines with its real price, today', function () {
    $course = mainCourse();
    config([
        'services.stripe.enabled' => false,
    ]);

    $graph = seoGraph($this->get('/')->getContent());

    expect($graph)->toHaveKeys(['Organization', 'WebSite', 'Course', 'FAQPage'])
        ->and($graph['Course']['name'])->toBe('Promoción inmobiliaria en España')
        ->and($graph['Course']['inLanguage'])->toBe('es')
        ->and($graph['Course']['hasCourseInstance']['courseMode'])->toBe('Online')
        ->and($graph['Course']['syllabusSections'][0]['name'])->toBe('El solar: encontrarlo y comprarlo')
        ->and($graph['Course']['offers'])->toHaveCount(1)
        ->and($graph['Course']['offers'][0])->toMatchArray(['price' => 490, 'priceCurrency' => 'EUR'])
        // No review, no rating: never a placeholder score.
        ->and($graph['Course'])->not->toHaveKey('aggregateRating');

    // A running promotion is the price, until the day it ends.
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 20, 'ends_at' => now()->addDays(3)]);
    $offer = seoGraph($this->get('/')->getContent())['Course']['offers'][0];
    expect($offer['price'])->toBe(392)
        ->and($offer['priceValidUntil'])->toBe(now()->addDays(3)->toDateString());

    // A rating appears with the first approved review, and only approved ones count.
    Review::factory()->approved()->create(['course_id' => $course->id, 'rating' => 4]);
    Review::factory()->create(['course_id' => $course->id, 'rating' => 1]);
    expect(seoGraph($this->get('/')->getContent())['Course']['aggregateRating'])
        ->toMatchArray(['ratingValue' => 4, 'reviewCount' => 1]);
});

it('offers a price only while some way to pay can take the money', function () {
    mainCourse();
    $offers = fn () => seoGraph($this->get('/')->getContent())['Course']['offers'] ?? [];

    config([
        'services.stripe.enabled' => false,
        'services.manual_payment.iban' => '',
        'services.manual_payment.bizum_phone' => '',
    ]);
    expect($offers())->toBe([]);

    // One bank-transfer account is enough to sell, in euros, once.
    config(['services.manual_payment.iban' => 'ES00 0000 0000 0000 0000 0000']);
    expect(collect($offers())->pluck('priceCurrency')->all())->toBe(['EUR']);

    // A second way to pay is not a second offer.
    config(['services.stripe.enabled' => true]);
    expect(collect($offers())->pluck('priceCurrency')->all())->toBe(['EUR']);
});

it('keeps paid module text out of the page and its structured data', function () {
    mainCourse();

    expect($this->get('/')->getContent())->not->toContain('TEXTO DE PAGO');
});

it('cannot be broken out of by a course title', function () {
    mainCourse(['title_es' => 'Título</script><img src=x onerror=alert(1)>']);

    $html = $this->get('/')->getContent();

    // JSON-LD carries < and > as unicode escapes; the visible copy is escaped.
    $inJsonLd = substr(json_encode('Título</script><img', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1);
    expect($html)->not->toContain('</script><img')
        ->and($html)->toContain($inJsonLd)
        ->and($html)->toContain('Título&lt;/script&gt;&lt;img');
});

it('points the main course URL at the home page, and gives other courses their own', function () {
    mainCourse();
    $other = Course::factory()->create(['slug' => 'otro-curso', 'title_es' => 'Otro curso']);

    expect($this->get('/cursos/'.config('seo.main_course'))->assertOk()->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/" />');
    expect($this->get('/en/cursos/'.config('seo.main_course'))->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/en" />');

    $html = $this->withHeader('Accept-Language', 'es')->get('/cursos/otro-curso')->assertOk()->getContent();
    expect($html)
        ->toContain('<link rel="canonical" href="https://construye.example/cursos/otro-curso" />')
        ->toContain('<title>Otro curso — Construye Academy</title>');
    expect(seoGraph($html))->toHaveKey('BreadcrumbList')->not->toHaveKey('FAQPage');
});

it('answers 404, not indexable, for a course that does not exist or is not published', function () {
    Course::factory()->create(['slug' => 'borrador', 'status' => 'draft']);

    foreach (['/cursos/no-existe', '/cursos/borrador', '/en/cursos/borrador'] as $url) {
        expect($this->get($url)->assertNotFound()->getContent())
            ->toContain('<meta name="robots" content="noindex" />')
            ->not->toContain('rel="canonical"');
    }
});

it('lists the published courses on the catalogue', function () {
    mainCourse();
    Course::factory()->create(['slug' => 'borrador', 'title_es' => 'Curso en borrador', 'status' => 'draft']);

    $html = $this->get('/cursos')->assertOk()->getContent();

    expect($html)->toContain('<link rel="canonical" href="https://construye.example/cursos" />')
        ->toContain('Promoción inmobiliaria en España')
        ->not->toContain('Curso en borrador');
    expect(seoGraph($html)['ItemList']['itemListElement'][0]['url'])->toBe('https://construye.example/');
});

it('opens no session for a crawler', function () {
    mainCourse();

    $this->get('/')->assertOk()->assertCookieMissing(config('session.cookie'));
});

it('publishes a sitemap with every page in every language', function () {
    mainCourse();
    Course::factory()->create(['slug' => 'otro-curso']);
    Course::factory()->create(['slug' => 'borrador', 'status' => 'draft']);

    $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent());
    $locs = [];
    foreach ($xml->url as $url) {
        $locs[] = (string) $url->loc;
    }

    expect($locs)->toContain('https://construye.example/', 'https://construye.example/en')
        ->toContain('https://construye.example/cursos', 'https://construye.example/en/cursos')
        ->toContain('https://construye.example/cursos/otro-curso', 'https://construye.example/en/cursos/otro-curso')
        ->not->toContain('https://construye.example/cursos/borrador')
        // The main course is the home page, listed once as such.
        ->not->toContain('https://construye.example/cursos/'.config('seo.main_course'));

    $alternates = [];
    foreach ($xml->url[0]->children('http://www.w3.org/1999/xhtml')->link as $link) {
        $alternates[] = (string) $link->attributes()->hreflang;
    }
    expect($alternates)->toBe(['es', 'en', 'fr', 'ar', 'ru', 'uk', 'x-default']);
});

it('serves a robots.txt that points at the sitemap and leaves the API open', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    // Google renders the app, and the app reads the course from /api.
    expect($robots)->toContain('Sitemap: https://construye.example/sitemap.xml')
        ->toContain('Disallow: /admin')
        ->not->toContain('Disallow: /api');
});

it('keeps the app shell not indexable and marked for the server', function () {
    $shell = file_get_contents(base_path('../web/index.html'));

    // Without the markers the server could not replace the head; without
    // noindex the app's own screens and 404s would be indexed.
    expect($shell)->toMatch('/<!--\s*seo:start/')
        ->toContain('<!-- seo:end -->')
        ->toContain('<meta name="robots" content="noindex" />');
});

/*
 * The content pass made the page name only the ways to pay the checkout could
 * take, so a launch with one gateway configured hid the rest of the offer. The
 * owner decided otherwise (constitution I, amended 2026-10-09): the sales page
 * presents the complete offer planned for launch, the checkout takes only what
 * is configured, and the structured data offers a euro price only once a card
 * can pay it (tested above).
 */

/** The FAQ's payment answer as the structured data carries it. */
function paymentAnswer(string $html): ?string
{
    $question = SpaStrings::get('es', 'course.faq.payment.q');
    foreach (seoGraph($html)['FAQPage']['mainEntity'] as $entry) {
        if ($entry['name'] === $question) {
            return $entry['acceptedAnswer']['text'];
        }
    }

    return null;
}

it('names every way to pay planned for launch, whatever is configured yet', function (array $settings, array $available) {
    mainCourse();
    config($settings);
    expect(PaymentMethods::available())->toBe($available);
    $methods = 'tarjeta bancaria (Visa, Mastercard), transferencia bancaria o Bizum';

    $html = $this->get('/')->getContent();
    expect(paymentAnswer($html))->toBe("Puedes pagar con {$methods}.")
        // The hero's note, read without JavaScript.
        ->and($html)->toContain("Pago: {$methods}. ".SpaStrings::get('es', 'landing.hero.paymentNote'));
})->with([
    'before any gateway is configured' => [[
        'services.stripe.enabled' => false,
        'services.stripe.secret' => '',
        'services.stripe.webhook_secret' => '',
        'services.manual_payment.iban' => '',
        'services.manual_payment.holder' => '',
        'services.manual_payment.bizum_phone' => '',
    ], []],
    // The launch case the decision is about. TestCase configures all three, so
    // without this a page that names only the configured methods once there
    // is at least one would pass unnoticed.
    'with only the bank transfer, the other ways still waiting' => [[
        'services.stripe.enabled' => false,
        'services.manual_payment.bizum_phone' => '',
    ], ['manual_transfer']],
]);

it('offers cash only when there is a way to reach the owner', function () {
    // Cash is taken by the owner in person: "contact us" with no contact is a dead end.
    mainCourse();
    $answer = 'Puedes pagar con tarjeta bancaria (Visa, Mastercard), transferencia bancaria o Bizum.';

    expect(paymentAnswer($this->get('/')->getContent()))->toBe($answer);

    completeSellerSettings();
    expect(paymentAnswer($this->get('/')->getContent()))->toBe($answer.' '.SpaStrings::get('es', 'payments.cash'));
});

it('counts the modules in the description instead of typing them', function () {
    $course = mainCourse();
    Module::factory()->create(['course_id' => $course->id, 'order' => 2]);

    expect($this->get('/')->getContent())
        ->toContain('<meta name="description" content="'.e(str_replace('{{modules}}', '2', SpaStrings::get('es', 'seo.home.description'))).'" />');
});

it('has every string of the app in every language', function () {
    $flatten = function (array $strings, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($strings as $key => $value) {
            $keys = is_array($value)
                ? array_merge($keys, $flatten($value, "{$prefix}{$key}."))
                : array_merge($keys, ["{$prefix}{$key}"]);
        }

        return $keys;
    };
    $read = fn (string $locale) => $flatten(json_decode(file_get_contents(config('seo.translations')."/{$locale}.json"), true));

    // Five FAQ answers once had no translation: that site showed them in the
    // authored language. Every locale but the authored one is checked.
    foreach (array_diff(config('locales.supported'), [config('locales.default')]) as $locale) {
        expect(array_values(array_diff($read('es'), $read($locale))))->toBe([], "missing from web/src/i18n/{$locale}.json");
    }
});

it('translates the app, in each language\'s own plural forms', function () {
    $read = function (string $locale): array {
        $flat = [];
        $walk = function (array $strings, string $prefix) use (&$walk, &$flat): void {
            foreach ($strings as $key => $value) {
                is_array($value) ? $walk($value, "{$prefix}{$key}.") : $flat["{$prefix}{$key}"] = $value;
            }
        };
        $walk(json_decode(file_get_contents(config('seo.translations')."/{$locale}.json"), true), '');

        return $flat;
    };

    // The CLDR plural categories i18next asks for. A count with no string in
    // its category falls through to Spanish: "3 урока" needs _few, "11 درساً"
    // needs _many. A new locale fails here until its forms are listed.
    $categories = [
        'es' => ['one', 'other'],
        'en' => ['one', 'other'],
        'fr' => ['one', 'other'],
        'ar' => ['zero', 'one', 'two', 'few', 'many', 'other'],
        'ru' => ['one', 'few', 'many', 'other'],
        'uk' => ['one', 'few', 'many', 'other'],
    ];
    expect(array_keys($categories))->toEqualCanonicalizing(config('locales.supported'));

    $spanish = $read('es');
    $english = $read('en');
    $plurals = array_unique(array_map(
        fn (string $key) => preg_replace('/_(one|other)$/', '', $key),
        array_filter(array_keys($spanish), fn (string $key) => preg_match('/_(one|other)$/', $key)),
    ));

    foreach (config('locales.supported') as $locale) {
        $strings = $read($locale);

        foreach ($plurals as $base) {
            foreach ($categories[$locale] as $category) {
                expect(array_key_exists("{$base}_{$category}", $strings))->toBeTrue("web/src/i18n/{$locale}.json has no {$base}_{$category}");
            }
        }

        // The four languages first shipped as copies of the English file. A
        // copy passes the key check above; it does not pass this one. French
        // shares a few strings with English by nature ("Module {{number}}").
        if (! in_array($locale, ['es', 'en'], true)) {
            $copied = array_filter($english, fn (string $text, string $key) => strlen($text) > 12 && ($strings[$key] ?? null) === $text, ARRAY_FILTER_USE_BOTH);
            expect(count($copied))->toBeLessThan(count($english) * 0.03, "web/src/i18n/{$locale}.json repeats the English: ".implode(', ', array_keys($copied)));
        }
    }
});
