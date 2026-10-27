<?php

/*
 * Public pages rendered by the server for search engines and link previews
 * (App\Http\Controllers\SeoPageController). Every visible string comes from
 * the SPA's own translation files, so the two can never say different things.
 */

return [

    // The built SPA shell the public pages are rendered into. Its
    // <!-- seo:start --> … <!-- seo:end --> block is replaced per page.
    'spa_index' => env('SEO_SPA_INDEX', base_path('../web/dist/index.html')),

    // web/src/i18n/{fr,ar,en}.json and the FAQ order (web/src/content).
    'translations' => base_path('../web/src/i18n'),
    'faq' => base_path('../web/src/content/faq.json'),

    // The course the home page sells: /courses/{this} is the same page as /,
    // so its canonical URL is the home page.
    'main_course' => 'promotion-immobiliere-algerie',

    // Search Console / Bing Webmaster Tools ownership, when verified by meta
    // tag rather than DNS. Empty means no tag.
    'verification' => [
        'google-site-verification' => env('GOOGLE_SITE_VERIFICATION'),
        'msvalidate.01' => env('BING_SITE_VERIFICATION'),
    ],

];
