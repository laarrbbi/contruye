<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LegalRegionRate;
use App\Models\NewsPost;
use App\Services\BusinessProfile;
use App\Services\CourseSocialProof;
use App\Support\Financials\SolarTeaser;
use App\Support\LocalizedUrl;
use App\Support\Markdown;
use App\Support\PaymentWording;
use App\Support\SpaStrings;
use App\Support\StructuredData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The public pages as search engines and link previews read them.
 *
 * The app is a single-page application: every URL used to return the same
 * empty shell, so crawlers that do not run JavaScript (Bing, Facebook,
 * WhatsApp, LinkedIn) saw no text at all, and Google, which renders with an
 * en-US browser, indexed the site in English. Each public page now leaves the
 * server with its own title, description, canonical URL, links to its other
 * languages, structured data and main content, in the language of its URL.
 * The app then takes the page over as usual.
 */
final class SeoPageController
{
    /** The language switcher's names, as the app shows them. */
    private const LANGUAGE_NAMES = [
        'es' => 'Español', 'en' => 'English', 'fr' => 'Français',
        'ar' => 'العربية', 'ru' => 'Русский', 'uk' => 'Українська',
    ];

    /** The solar calculator's page, the same path in every language. */
    public const SOLAR_PATH = '/calculadora-precio-solar';

    /** The news (Noticias, in the menu): the list, and one page per piece under it. */
    public const NEWS_PATH = '/noticias';

    /** Its questions, in the order the page shows them (web/src/i18n, solar.faq). */
    public const SOLAR_FAQ = ['taxes', 'vat', 'perM2', 'plusvalia', 'rates', 'full'];

    private const OG_LOCALES = [
        'es' => 'es_ES', 'en' => 'en_GB', 'fr' => 'fr_FR',
        'ar' => 'ar_AR', 'ru' => 'ru_RU', 'uk' => 'uk_UA',
    ];

    // The language comes from the route's defaults (routes/seo.php), read by
    // name: Laravel passes route parameters by position, URL ones first.
    public function home(Request $request): Response
    {
        return $this->coursePage((string) $request->route('locale'), (string) config('seo.main_course'));
    }

    public function course(Request $request): Response
    {
        return $this->coursePage((string) $request->route('locale'), (string) $request->route('slug'));
    }

    public function catalog(Request $request): Response
    {
        $locale = (string) $request->route('locale');
        app()->setLocale($locale);

        $courses = Course::published()->with('campaigns')->latest()->get();
        $url = LocalizedUrl::to('/cursos', $locale);
        $title = SpaStrings::get($locale, 'seo.catalog.title');

        $head = $this->head($locale, '/cursos', $title, SpaStrings::get($locale, 'seo.catalog.description'), [
            StructuredData::organization(),
            StructuredData::website(),
            StructuredData::itemList($courses->map(fn (Course $course) => $this->courseUrl($course, $locale))->all()),
            StructuredData::breadcrumbs([
                ['name' => SpaStrings::get($locale, 'app.name'), 'url' => LocalizedUrl::to('/', $locale)],
                ['name' => $title, 'url' => $url],
            ]),
        ]);

        $body = view('seo.catalog', [
            'locale' => $locale,
            'header' => $this->header($locale, '/cursos'),
            'courses' => $courses,
            'courseUrl' => fn (Course $course) => $this->courseUrl($course, $locale),
        ])->render();

        return $this->page($locale, $head, $body);
    }

    /**
     * The solar calculator's page: what a plot really costs with its taxes,
     * the rates of every autonomous community, and the questions people ask.
     * The rates and dates are the legal parameters in force; the calculator
     * itself runs in the app (SolarCalculatorController).
     */
    public function solarCalculator(Request $request, SolarTeaser $teaser): Response
    {
        $locale = (string) $request->route('locale');
        app()->setLocale($locale);

        $path = self::SOLAR_PATH;
        $url = LocalizedUrl::to($path, $locale);
        $options = $teaser->options();
        $percent = fn (float $value) => self::percent($value, $locale);
        $reviewedAt = $options['reviewed_at'] !== null
            ? Carbon::parse($options['reviewed_at'])->locale($locale)->isoFormat('LL')
            : null;
        // The page's words, with the figures they name filled in from the parameters in force.
        $s = fn (string $key) => strtr(SpaStrings::get($locale, "solar.{$key}"), [
            '{{vat}}' => $percent($options['vat']),
            '{{date}}' => (string) $reviewedAt,
        ]);

        $faq = collect(self::SOLAR_FAQ)->map(fn (string $key) => [
            'question' => $s("faq.{$key}.q"),
            'answer' => $s("faq.{$key}.a"),
        ])->all();

        $title = $s('pageTitle');
        $head = $this->head($locale, $path, $s('seoTitle'), $s('seoDescription'), [
            StructuredData::organization(),
            StructuredData::website(),
            StructuredData::webApplication($title, $s('seoDescription'), $url, $locale),
            StructuredData::faq($faq, $url),
            StructuredData::breadcrumbs([
                ['name' => SpaStrings::get($locale, 'app.name'), 'url' => LocalizedUrl::to('/', $locale)],
                ['name' => $title, 'url' => $url],
            ]),
        ]);

        $body = view('seo.solar-calculator', [
            'locale' => $locale,
            'header' => $this->header($locale, $path),
            's' => $s,
            'percent' => $percent,
            'regions' => $options['regions'],
            'reviewedAt' => $reviewedAt,
            'faq' => $faq,
            'courseUrl' => LocalizedUrl::path('/', $locale),
        ])->render();

        return $this->page($locale, $head, $body);
    }

    /** The news: the published pieces, newest first. */
    public function news(Request $request): Response
    {
        $locale = (string) $request->route('locale');
        app()->setLocale($locale);

        $posts = NewsPost::visible()->orderByDesc('published_at')->orderByDesc('id')->limit(50)->get();
        $url = LocalizedUrl::to(self::NEWS_PATH, $locale);
        $title = SpaStrings::get($locale, 'news.title');
        $postUrl = fn (NewsPost $post) => LocalizedUrl::to(self::NEWS_PATH.'/'.$post->slug, $locale);

        $head = $this->head($locale, self::NEWS_PATH, $title, SpaStrings::get($locale, 'news.seoDescription'), [
            StructuredData::organization(),
            StructuredData::website(),
            StructuredData::itemList($posts->map($postUrl)->all()),
            StructuredData::breadcrumbs([
                ['name' => SpaStrings::get($locale, 'app.name'), 'url' => LocalizedUrl::to('/', $locale)],
                ['name' => $title, 'url' => $url],
            ]),
        ]);

        $body = view('seo.news', [
            'locale' => $locale,
            'header' => $this->header($locale, self::NEWS_PATH),
            'title' => $title,
            'lead' => SpaStrings::get($locale, 'news.lead'),
            'empty' => SpaStrings::get($locale, 'news.empty'),
            'posts' => $posts,
            'postPath' => fn (NewsPost $post) => LocalizedUrl::path(self::NEWS_PATH.'/'.$post->slug, $locale),
            'date' => fn (NewsPost $post) => $post->published_at?->locale($locale)->isoFormat('LL'),
        ])->render();

        return $this->page($locale, $head, $body);
    }

    /** One piece of news, with its own title, description, picture and article data. */
    public function newsPost(Request $request): Response
    {
        $locale = (string) $request->route('locale');
        app()->setLocale($locale);

        $post = NewsPost::visible()->where('slug', (string) $request->route('slug'))->first();
        if ($post === null) {
            return $this->page(null, null, null, 404);
        }

        $path = self::NEWS_PATH.'/'.$post->slug;
        $url = LocalizedUrl::to($path, $locale);
        $title = (string) $post->localizedTitle($locale);
        $description = Str::limit((string) ($post->localizedSummary($locale) ?? Markdown::toText($post->localizedBody($locale))), 155);

        $head = $this->head($locale, $path, $title, $description, [
            StructuredData::organization(),
            StructuredData::website(),
            StructuredData::newsArticle($post, $locale, $url),
            StructuredData::breadcrumbs([
                ['name' => SpaStrings::get($locale, 'app.name'), 'url' => LocalizedUrl::to('/', $locale)],
                ['name' => SpaStrings::get($locale, 'news.title'), 'url' => LocalizedUrl::to(self::NEWS_PATH, $locale)],
                ['name' => $title, 'url' => $url],
            ]),
        ], $post->imageUrl());

        $body = view('seo.news-post', [
            'locale' => $locale,
            'header' => $this->header($locale, $path),
            'post' => $post,
            'title' => $title,
            'lang' => $post->localizedLocale('body', $locale),
            'html' => $post->bodyHtml($locale),
            'date' => $post->published_at?->locale($locale)->isoFormat('LL'),
            'back' => LocalizedUrl::path(self::NEWS_PATH, $locale),
            'backLabel' => SpaStrings::get($locale, 'news.back'),
        ])->render();

        return $this->page($locale, $head, $body);
    }

    /** "6 %", "0,75 %", "7.5%": a rate as the reader's language writes it. */
    private static function percent(float $value, string $locale): string
    {
        $formatter = new \NumberFormatter(match ($locale) {
            'en' => 'en_GB', 'fr' => 'fr_FR', 'ar' => 'ar@numbers=latn', 'ru' => 'ru_RU', 'uk' => 'uk_UA',
            default => 'es_ES',
        }, \NumberFormatter::PERCENT);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return (string) $formatter->format($value);
    }

    public function robots(): Response
    {
        // /api stays open: Google renders the app, and the app reads the
        // course from the API. Blocking it would hide the content again.
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /dashboard',
            'Disallow: /learn',
            'Disallow: /orders',
            'Disallow: /account',
            'Disallow: /newsletter',
            'Disallow: /reset-password',
            '',
            'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml',
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $courses = Course::published()->get(['id', 'slug', 'updated_at']);
        $main = $courses->firstWhere('slug', config('seo.main_course'));

        $pages = collect([['path' => '/', 'lastmod' => $main?->updated_at]]);
        $pages->push(['path' => '/cursos', 'lastmod' => $courses->max('updated_at')]);
        // The calculator's page changes when its rates do.
        $pages->push(['path' => self::SOLAR_PATH, 'lastmod' => LegalRegionRate::query()->max('updated_at') !== null
            ? Carbon::parse(LegalRegionRate::query()->max('updated_at'))
            : null]);
        foreach ($courses as $course) {
            if ($course->slug !== config('seo.main_course')) {
                $pages->push(['path' => "/cursos/{$course->slug}", 'lastmod' => $course->updated_at]);
            }
        }
        // The news: its list once there is a piece, and every piece.
        $news = NewsPost::visible()->orderByDesc('published_at')->get(['id', 'slug', 'published_at', 'updated_at']);
        if ($news->isNotEmpty()) {
            $pages->push(['path' => self::NEWS_PATH, 'lastmod' => $news->max('updated_at')]);
            foreach ($news as $post) {
                $pages->push(['path' => self::NEWS_PATH.'/'.$post->slug, 'lastmod' => $post->updated_at]);
            }
        }

        return response()
            ->view('seo.sitemap', ['pages' => $pages, 'locales' => config('locales.supported')])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function coursePage(string $locale, string $slug): Response
    {
        app()->setLocale($locale);

        $course = Course::published()->where('slug', $slug)->first();
        if ($course === null) {
            // The app shows its own "not found"; search engines get a 404.
            return $this->page(null, null, null, 404);
        }

        $course->loadCount('lessons')
            ->loadSum('lessons', 'duration_seconds')
            ->load('modules.lessons');
        CourseSocialProof::attach($course);

        $isMain = $slug === config('seo.main_course');
        $path = $isMain ? '/' : "/cursos/{$slug}";
        $url = LocalizedUrl::to($path, $locale);
        $faq = $isMain ? $this->faq($locale) : [];

        $nodes = [
            StructuredData::organization(),
            StructuredData::website(),
            StructuredData::course($course, $locale, $url),
        ];
        if ($faq !== []) {
            $nodes[] = StructuredData::faq($faq, $url);
        }
        if (! $isMain) {
            $nodes[] = StructuredData::breadcrumbs([
                ['name' => SpaStrings::get($locale, 'app.name'), 'url' => LocalizedUrl::to('/', $locale)],
                ['name' => SpaStrings::get($locale, 'seo.catalog.title'), 'url' => LocalizedUrl::to('/cursos', $locale)],
                ['name' => $course->localizedTitle($locale), 'url' => $url],
            ]);
        }

        $head = $this->head(
            $locale,
            $path,
            $isMain ? SpaStrings::get($locale, 'seo.home.title') : $course->localizedTitle($locale),
            $isMain
                // Counted, as on the page: never a module count typed by hand.
                ? str_replace('{{modules}}', (string) $course->modules->where('kind', 'phase')->count(), SpaStrings::get($locale, 'seo.home.description'))
                : Str::limit((string) $course->localizedDescription($locale), 155),
            $nodes,
        );

        $body = view('seo.course', [
            'locale' => $locale,
            'header' => $this->header($locale, $path),
            'course' => $course,
            'faq' => $faq,
            'paymentNote' => PaymentWording::sentence($locale, SpaStrings::get($locale, 'landing.hero.paymentNote')),
            'checkoutUrl' => '/register?redirect='.rawurlencode("/checkout/{$slug}"),
        ])->render();

        return $this->page($locale, $head, $body);
    }

    /**
     * The FAQ as the sales page shows it: the same questions, in the same
     * order, and "how do I reach support?" only once a channel exists.
     *
     * @return list<array{question: string, answer: string, answer_html: string}>
     */
    private function faq(string $locale): array
    {
        $profile = BusinessProfile::public();
        $reachable = $profile['whatsapp_url'] !== null || $profile['phone_tel'] !== null || $profile['email'] !== null;

        // The ways to pay are the complete offer planned for launch, whatever
        // is configured yet (constitution I, amended 2026-10-09). Cash, which
        // the owner takes in person, only when there is a way to reach him.
        $payment = str_replace('{{methods}}', PaymentWording::methods($locale), SpaStrings::get($locale, 'course.faq.payment.a'))
            .($reachable ? ' '.SpaStrings::get($locale, 'payments.cash') : '');

        return collect(SpaStrings::faqKeys())
            ->reject(fn (string $key) => $key === 'support' && ! $reachable)
            ->map(function (string $key) use ($locale, $payment) {
                $answer = $key === 'payment' ? $payment : SpaStrings::get($locale, "course.faq.{$key}.a");

                return [
                    'question' => SpaStrings::get($locale, "course.faq.{$key}.q"),
                    'answer' => $answer,
                    // Escaped, with the laws it names linked, as the app shows it.
                    'answer_html' => (string) Markdown::inline($answer),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    /** @param  ?string  $image  the page's own picture (a piece of news), of unknown size; else the site's */
    private function head(string $locale, string $path, string $title, string $description, array $nodes, ?string $image = null): string
    {
        return view('seo.head', [
            'title' => $title.' — '.SpaStrings::get($locale, 'app.name'),
            'description' => $description,
            'canonical' => LocalizedUrl::to($path, $locale),
            'alternates' => LocalizedUrl::alternates($path),
            'ogLocale' => self::OG_LOCALES[$locale],
            'ogLocaleAlternates' => array_values(array_diff_key(self::OG_LOCALES, [$locale => true])),
            'image' => $image ?? rtrim((string) config('app.url'), '/').'/og-image.jpg',
            'imageSize' => $image === null ? [1200, 630] : null,
            'imageAlt' => SpaStrings::get($locale, 'landing.heroV2.title'),
            'verification' => array_filter((array) config('seo.verification')),
            'jsonLd' => StructuredData::encode($nodes),
        ])->render();
    }

    /** @return array{home: string, name: string, links: list<array{href: string, label: string}>, languages: list<array{locale: string, name: string, href: string}>} */
    private function header(string $locale, string $path): array
    {
        return [
            'home' => LocalizedUrl::path('/', $locale),
            'name' => SpaStrings::get($locale, 'app.name'),
            // The sections of the app's menu that are pages of this site.
            'links' => [
                ['href' => LocalizedUrl::path(self::SOLAR_PATH, $locale), 'label' => SpaStrings::get($locale, 'nav.calculator')],
                ['href' => LocalizedUrl::path(self::NEWS_PATH, $locale), 'label' => SpaStrings::get($locale, 'nav.news')],
            ],
            'languages' => collect(config('locales.supported'))
                ->reject(fn (string $other) => $other === $locale)
                ->map(fn (string $other) => [
                    'locale' => $other,
                    'name' => self::LANGUAGE_NAMES[$other],
                    'href' => LocalizedUrl::path($path, $other),
                ])
                ->values()
                ->all(),
        ];
    }

    private function courseUrl(Course $course, string $locale): string
    {
        return $course->slug === config('seo.main_course')
            ? LocalizedUrl::to('/', $locale)
            : LocalizedUrl::to("/cursos/{$course->slug}", $locale);
    }

    /**
     * The built app's index.html with this page's head, language and content.
     * Without a page head (a 404) the shell keeps its own, which says noindex.
     */
    private function page(?string $locale, ?string $head, ?string $body, int $status = 200): Response
    {
        $html = $this->shell();

        if ($locale !== null) {
            $html = (string) preg_replace(
                '/<html\b[^>]*>/',
                '<html lang="'.$locale.'" dir="'.(in_array($locale, config('locales.rtl', []), true) ? 'rtl' : 'ltr').'">',
                $html,
                1,
            );
        }

        if ($head !== null) {
            // A callback, not a replacement string: the JSON-LD contains
            // backslashes that preg_replace would read as back-references.
            $html = (string) preg_replace_callback('/<!--\s*seo:start.*?<!--\s*seo:end\s*-->/s', fn () => $head, $html, 1, $replaced);

            if ($replaced === 0) {
                // Markers gone (an edited index.html): never serve the shell's
                // noindex on a public page. Drop its tags, add the page's own.
                Log::warning('seo:start / seo:end markers missing from the SPA index');
                $html = (string) preg_replace('/<title>.*?<\/title>|<meta\s+name="(?:description|robots)"[^>]*>/s', '', $html);
                $html = str_replace('</head>', $head.'</head>', $html);
            }
        }

        if ($body !== null) {
            $html = str_replace('<div id="root"></div>', '<div id="root">'.$body.'</div>', $html);
        }

        return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function shell(): string
    {
        $path = (string) config('seo.spa_index');
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        // Only before the first front-end build: say so, and still serve the
        // page's head and content.
        Log::warning('SPA index not found for server-rendered pages', ['path' => $path]);

        return '<!doctype html><html lang="es"><head><meta charset="UTF-8" />'
            .'<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content" />'
            .'<!-- seo:start --><!-- seo:end --></head><body><div id="root"></div></body></html>';
    }
}
