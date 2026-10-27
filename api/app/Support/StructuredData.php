<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Module;
use App\Services\BusinessProfile;
use App\Services\PaymentMethods;

/**
 * schema.org JSON-LD for the public pages: who sells, what the course is,
 * what it costs today, and what buyers ask. Only real data: an empty business
 * field, a course without reviews, lessons not yet filmed, a payment method
 * that is switched off — each simply leaves its property out.
 */
final class StructuredData
{
    /** @return array<string, mixed> */
    public static function organization(): array
    {
        $profile = BusinessProfile::public();
        $base = self::base();

        $organization = [
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => 'Ebni Academy',
            'url' => $base.'/',
            'logo' => $base.'/ebni-logo.png',
        ];

        if ($profile['business_name'] !== null) {
            $organization['legalName'] = $profile['business_name'];
        }
        if ($profile['address'] !== null) {
            $organization['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $profile['address'],
                'addressCountry' => 'DZ',
            ];
        }

        $phone = BusinessProfile::international($profile['phone'] ?? $profile['whatsapp']);
        $contact = array_filter([
            'telephone' => $phone !== null ? '+'.$phone : null,
            'email' => $profile['email'],
        ]);
        if ($contact !== []) {
            $organization['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer support'] + $contact;
        }

        return $organization;
    }

    /** @return array<string, mixed> */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::base().'/#website',
            'url' => self::base().'/',
            'name' => 'Ebni Academy',
            'inLanguage' => config('locales.supported'),
            'publisher' => ['@id' => self::base().'/#organization'],
        ];
    }

    /**
     * The course with its price today: the running promotion included, with
     * the date it ends. A currency is offered only when a payment method that
     * takes it is switched on (a CCP-only launch offers no euro price).
     *
     * @return array<string, mixed>
     */
    public static function course(Course $course, string $locale, string $url): array
    {
        $campaign = $course->activeCampaign();
        $offers = [];
        $currencies = [
            'DZD' => self::anyAvailable(['chargily', 'manual_ccp', 'manual_baridimob']) ? $course->price_dzd : null,
            'EUR' => PaymentMethods::isAvailable('stripe') ? $course->price_eur : null,
        ];
        foreach ($currencies as $currency => $price) {
            if ($price === null) {
                continue;
            }
            $offer = [
                '@type' => 'Offer',
                'category' => 'Paid',
                'price' => $campaign !== null ? $campaign->apply((int) $price) : (int) $price,
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
            ];
            if ($campaign !== null) {
                $offer['priceValidUntil'] = $campaign->ends_at->toDateString();
            }
            $offers[] = $offer;
        }

        $data = [
            '@type' => 'Course',
            '@id' => $url.'#course',
            'name' => $course->localizedTitle($locale),
            'description' => (string) $course->localizedDescription($locale),
            'url' => $url,
            'image' => self::base().'/og-image.jpg',
            // The material is written in French; Arabic once modules carry it.
            'inLanguage' => $course->modules->contains(fn (Module $module) => filled($module->description_ar)) ? ['fr', 'ar'] : 'fr',
            'provider' => ['@id' => self::base().'/#organization'],
            'hasCourseInstance' => array_filter([
                '@type' => 'CourseInstance',
                'courseMode' => 'Online',
                // Only once lessons are filmed: 0 means not recorded yet.
                'courseWorkload' => self::isoDuration((int) ($course->lessons_sum_duration_seconds ?? 0)),
            ]),
            'syllabusSections' => $course->modules->map(fn (Module $module) => [
                '@type' => 'Syllabus',
                'name' => $module->localizedTitle($locale),
            ])->values()->all(),
        ];

        if ($offers !== []) {
            $data['offers'] = $offers;
        }

        if ((int) $course->reviews_count > 0 && $course->rating !== null) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $course->rating, 1),
                'reviewCount' => (int) $course->reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $data;
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public static function faq(array $items, string $url): array
    {
        return [
            '@type' => 'FAQPage',
            '@id' => $url.'#faq',
            'mainEntity' => array_map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $items),
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    public static function itemList(array $urls): array
    {
        return [
            '@type' => 'ItemList',
            'itemListElement' => array_map(fn (string $url, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $url,
            ], $urls, array_keys($urls)),
        ];
    }

    /**
     * The <script type="application/ld+json"> body. JSON_HEX_TAG writes < and
     * > as unicode escapes: a course title containing "</script>" cannot
     * close the tag and inject markup.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function encode(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }

    /** @param  list<string>  $methods */
    private static function anyAvailable(array $methods): bool
    {
        foreach ($methods as $method) {
            if (PaymentMethods::isAvailable($method)) {
                return true;
            }
        }

        return false;
    }

    private static function isoDuration(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return 'PT'.($hours > 0 ? $hours.'H' : '').($minutes > 0 || $hours === 0 ? $minutes.'M' : '');
    }

    private static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
