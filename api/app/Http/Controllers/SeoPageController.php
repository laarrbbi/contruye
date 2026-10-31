<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\BusinessProfile;
use App\Services\CourseSocialProof;
use App\Support\LocalizedUrl;
use App\Support\PaymentWording;
use App\Support\SpaStrings;
use App\Support\StructuredData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        foreach ($courses as $course) {
            if ($course->slug !== config('seo.main_course')) {
                $pages->push(['path' => "/cursos/{$course->slug}", 'lastmod' => $course->updated_at]);
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
     * @return list<array{question: string, answer: string}>
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
            ->map(fn (string $key) => [
                'question' => SpaStrings::get($locale, "course.faq.{$key}.q"),
                'answer' => $key === 'payment' ? $payment : SpaStrings::get($locale, "course.faq.{$key}.a"),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function head(string $locale, string $path, string $title, string $description, array $nodes): string
    {
        return view('seo.head', [
            'title' => $title.' — '.SpaStrings::get($locale, 'app.name'),
            'description' => $description,
            'canonical' => LocalizedUrl::to($path, $locale),
            'alternates' => LocalizedUrl::alternates($path),
            'ogLocale' => self::OG_LOCALES[$locale],
            'ogLocaleAlternates' => array_values(array_diff_key(self::OG_LOCALES, [$locale => true])),
            'image' => rtrim((string) config('app.url'), '/').'/og-image.jpg',
            'imageAlt' => SpaStrings::get($locale, 'landing.hero.title'),
            'verification' => array_filter((array) config('seo.verification')),
            'jsonLd' => StructuredData::encode($nodes),
        ])->render();
    }

    /** @return array{home: string, name: string, languages: list<array{locale: string, name: string, href: string}>} */
    private function header(string $locale, string $path): array
    {
        return [
            'home' => LocalizedUrl::path('/', $locale),
            'name' => SpaStrings::get($locale, 'app.name'),
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
            .'<meta name="viewport" content="width=device-width, initial-scale=1.0" />'
            .'<!-- seo:start --><!-- seo:end --></head><body><div id="root"></div></body></html>';
    }
}
