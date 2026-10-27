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
 * en-US browser, indexed the French site in English. Each public page now
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
        'title_es' => 'La Promotion Immobilière en Algérie',
        'title_en' => 'الترقية العقارية في الجزائر',
        'description_es' => 'De la création de la société à la remise des clés.',
        'price_dzd' => 90000,
        'price_eur' => 300,
    ], $attributes));

    $module = Module::factory()->create([
        'course_id' => $course->id,
        'order' => 1,
        'title_es' => 'Le foncier : trouver le terrain',
        'description_es' => 'TEXTE PAYANT du module, réservé aux acheteurs.',
    ]);
    Lesson::factory()->create(['module_id' => $module->id, 'title_es' => 'Vérifier un titre']);

    return $course;
}

it('serves the home page in French with its own head, whatever the browser speaks', function () {
    mainCourse();

    $html = $this->withHeader('Accept-Language', 'en-US')->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="fr" dir="ltr">')
        ->toContain('<title>'.SpaStrings::get('fr', 'seo.home.title').' — Construye Academy</title>')
        ->toContain('<meta name="description" content="'.e(str_replace('{{modules}}', '1', SpaStrings::get('fr', 'seo.home.description'))).'" />')
        ->toContain('<link rel="canonical" href="https://construye.example/" />')
        ->toContain('<link rel="alternate" hreflang="ar" href="https://construye.example/ar" />')
        ->toContain('<link rel="alternate" hreflang="en" href="https://construye.example/en" />')
        ->toContain('<link rel="alternate" hreflang="x-default" href="https://construye.example/" />')
        ->toContain('content="index, follow')
        ->not->toContain('noindex')
        // The page's content, readable without JavaScript.
        ->toContain('<h1')
        ->toContain(e(SpaStrings::get('fr', 'landing.hero.title')))
        ->toContain('Le foncier : trouver le terrain')
        ->toContain('Vérifier un titre')
        // The app still boots from the same shell.
        ->toContain('<script type="module" src="/src/main.tsx"></script>');
});

it('serves /ar in Arabic, right to left, and /en in English', function () {
    mainCourse();

    $arabic = $this->get('/ar')->assertOk()->getContent();
    expect($arabic)
        ->toContain('<html lang="ar" dir="rtl">')
        ->toContain('<title>'.SpaStrings::get('ar', 'seo.home.title').' — '.SpaStrings::get('ar', 'app.name').'</title>')
        ->toContain('<link rel="canonical" href="https://construye.example/ar" />')
        ->toContain('الترقية العقارية في الجزائر');
    expect(seoGraph($arabic)['FAQPage']['mainEntity'][0]['name'])
        ->toBe(SpaStrings::get('ar', 'course.faq.'.SpaStrings::faqKeys()[0].'.q'));

    expect($this->get('/en')->assertOk()->getContent())
        ->toContain('<html lang="en" dir="ltr">')
        ->toContain('<link rel="canonical" href="https://construye.example/en" />');
});

it('describes the course for search engines with its real price, today', function () {
    $course = mainCourse();
    config([
        'services.manual_payment.ccp_number' => '0000000000',
        'services.manual_payment.ccp_name' => 'Titulaire',
        'services.stripe.enabled' => false,
    ]);

    $graph = seoGraph($this->get('/')->getContent());

    expect($graph)->toHaveKeys(['Organization', 'WebSite', 'Course', 'FAQPage'])
        ->and($graph['Course']['name'])->toBe('La Promotion Immobilière en Algérie')
        ->and($graph['Course']['hasCourseInstance']['courseMode'])->toBe('Online')
        ->and($graph['Course']['syllabusSections'][0]['name'])->toBe('Le foncier : trouver le terrain')
        ->and($graph['Course']['offers'])->toHaveCount(1)
        ->and($graph['Course']['offers'][0])->toMatchArray(['price' => 90000, 'priceCurrency' => 'DZD'])
        // No review, no rating: never a placeholder score.
        ->and($graph['Course'])->not->toHaveKey('aggregateRating');

    // A running promotion is the price, until the day it ends.
    Campaign::factory()->create(['course_id' => $course->id, 'percent_off' => 20, 'ends_at' => now()->addDays(3)]);
    $offer = seoGraph($this->get('/')->getContent())['Course']['offers'][0];
    expect($offer['price'])->toBe(72000)
        ->and($offer['priceValidUntil'])->toBe(now()->addDays(3)->toDateString());

    // A rating appears with the first approved review, and only approved ones count.
    Review::factory()->approved()->create(['course_id' => $course->id, 'rating' => 4]);
    Review::factory()->create(['course_id' => $course->id, 'rating' => 1]);
    expect(seoGraph($this->get('/')->getContent())['Course']['aggregateRating'])
        ->toMatchArray(['ratingValue' => 4, 'reviewCount' => 1]);
});

it('offers a euro price only when card payment in euros is switched on', function () {
    mainCourse();
    config(['services.manual_payment.ccp_number' => '0000000000', 'services.manual_payment.ccp_name' => 'Titulaire']);

    config(['services.stripe.enabled' => false]);
    expect(collect(seoGraph($this->get('/')->getContent())['Course']['offers'])->pluck('priceCurrency')->all())->toBe(['DZD']);

    config(['services.stripe.enabled' => true, 'services.stripe.secret' => 'sk_live_x', 'services.stripe.webhook_secret' => 'whsec_x']);
    expect(collect(seoGraph($this->get('/')->getContent())['Course']['offers'])->pluck('priceCurrency')->all())->toBe(['DZD', 'EUR']);
});

it('keeps paid module text out of the page and its structured data', function () {
    mainCourse();

    expect($this->get('/')->getContent())->not->toContain('TEXTE PAYANT');
});

it('cannot be broken out of by a course title', function () {
    mainCourse(['title_es' => 'Titre</script><img src=x onerror=alert(1)>']);

    $html = $this->get('/')->getContent();

    // JSON-LD carries < and > as unicode escapes; the visible copy is escaped.
    $inJsonLd = substr(json_encode('Titre</script><img', JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), 1, -1);
    expect($html)->not->toContain('</script><img')
        ->and($html)->toContain($inJsonLd)
        ->and($html)->toContain('Titre&lt;/script&gt;&lt;img');
});

it('points the main course URL at the home page, and gives other courses their own', function () {
    mainCourse();
    $other = Course::factory()->create(['slug' => 'autre-formation', 'title_es' => 'Autre formation']);

    expect($this->get('/courses/'.config('seo.main_course'))->assertOk()->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/" />');
    expect($this->get('/ar/courses/'.config('seo.main_course'))->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/ar" />');

    $html = $this->get('/courses/autre-formation')->assertOk()->getContent();
    expect($html)
        ->toContain('<link rel="canonical" href="https://construye.example/courses/autre-formation" />')
        ->toContain('<title>Autre formation — Construye Academy</title>');
    expect(seoGraph($html))->toHaveKey('BreadcrumbList')->not->toHaveKey('FAQPage');
});

it('answers 404, not indexable, for a course that does not exist or is not published', function () {
    Course::factory()->create(['slug' => 'brouillon', 'status' => 'draft']);

    foreach (['/courses/nexiste-pas', '/courses/brouillon', '/en/courses/brouillon'] as $url) {
        expect($this->get($url)->assertNotFound()->getContent())
            ->toContain('<meta name="robots" content="noindex" />')
            ->not->toContain('rel="canonical"');
    }
});

it('lists the published courses on the catalogue', function () {
    mainCourse();
    Course::factory()->create(['slug' => 'brouillon', 'title_es' => 'Formation brouillon', 'status' => 'draft']);

    $html = $this->get('/formations')->assertOk()->getContent();

    expect($html)->toContain('<link rel="canonical" href="https://construye.example/formations" />')
        ->toContain('La Promotion Immobilière en Algérie')
        ->not->toContain('Formation brouillon');
    expect(seoGraph($html)['ItemList']['itemListElement'][0]['url'])->toBe('https://construye.example/');
});

it('opens no session for a crawler', function () {
    mainCourse();

    $this->get('/')->assertOk()->assertCookieMissing(config('session.cookie'));
});

it('publishes a sitemap with every page in every language', function () {
    mainCourse();
    Course::factory()->create(['slug' => 'autre-formation']);
    Course::factory()->create(['slug' => 'brouillon', 'status' => 'draft']);

    $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent());
    $locs = [];
    foreach ($xml->url as $url) {
        $locs[] = (string) $url->loc;
    }

    expect($locs)->toContain('https://construye.example/', 'https://construye.example/ar', 'https://construye.example/en')
        ->toContain('https://construye.example/formations', 'https://construye.example/ar/formations')
        ->toContain('https://construye.example/courses/autre-formation', 'https://construye.example/en/courses/autre-formation')
        ->not->toContain('https://construye.example/courses/brouillon')
        // The main course is the home page, listed once as such.
        ->not->toContain('https://construye.example/courses/'.config('seo.main_course'));

    $alternates = [];
    foreach ($xml->url[0]->children('http://www.w3.org/1999/xhtml')->link as $link) {
        $alternates[] = (string) $link->attributes()->hreflang;
    }
    expect($alternates)->toBe(['fr', 'ar', 'en', 'x-default']);
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
    $question = SpaStrings::get('fr', 'course.faq.payment.q');
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
    $methods = 'carte CIB ou Edahabia, versement CCP, BaridiMob ou carte internationale (Visa, Mastercard)';

    $html = $this->get('/')->getContent();
    expect(paymentAnswer($html))->toBe("Vous pouvez payer par {$methods}.")
        // The hero's note, read without JavaScript.
        ->and($html)->toContain("Paiement\u{00A0}: {$methods}. ".SpaStrings::get('fr', 'landing.hero.paymentNote'));
})->with([
    'before any gateway is configured' => [[
        'services.chargily.enabled' => false,
        'services.chargily.api_key' => '',
        'services.chargily.secret' => '',
        'services.stripe.enabled' => false,
        'services.stripe.secret' => '',
        'services.stripe.webhook_secret' => '',
        'services.manual_payment.ccp_number' => '',
        'services.manual_payment.ccp_name' => '',
        'services.manual_payment.baridimob_rip' => '',
    ], []],
    // The launch case the decision is about. TestCase configures all four, so
    // without this a page that names only the configured methods once there
    // is at least one would pass unnoticed.
    'with only CCP, the other gateways still waiting' => [[
        'services.chargily.enabled' => false,
        'services.stripe.enabled' => false,
        'services.manual_payment.baridimob_rip' => '',
    ], ['manual_ccp']],
]);

it('offers cash only when there is a way to reach the owner', function () {
    // Cash is taken by the owner in person: "contact us" with no contact is a dead end.
    mainCourse();
    $answer = 'Vous pouvez payer par carte CIB ou Edahabia, versement CCP, BaridiMob ou carte internationale (Visa, Mastercard).';

    expect(paymentAnswer($this->get('/')->getContent()))->toBe($answer);

    completeSellerSettings();
    expect(paymentAnswer($this->get('/')->getContent()))->toBe($answer.' '.SpaStrings::get('fr', 'payments.cash'));
});

it('counts the modules in the description instead of typing them', function () {
    $course = mainCourse();
    Module::factory()->create(['course_id' => $course->id, 'order' => 2]);

    expect($this->get('/')->getContent())
        ->toContain('<meta name="description" content="'.e(str_replace('{{modules}}', '2', SpaStrings::get('fr', 'seo.home.description'))).'" />');
});

it('has every string of the app in Arabic and English', function () {
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

    // Five FAQ answers had no Arabic: the Arabic site showed them in French.
    foreach (['ar', 'en'] as $locale) {
        expect(array_values(array_diff($read('fr'), $read($locale))))->toBe([], "missing from web/src/i18n/{$locale}.json");
    }
});
