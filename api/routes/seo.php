<?php

use App\Http\Controllers\SeoPageController;
use Illuminate\Support\Facades\Route;

/*
 * Public pages, served with their own head and content for search engines
 * and link previews (SeoPageController). One URL per language: French at the
 * root, /ar and /en for the others. Loaded without the session middleware:
 * a crawler visit must not open a session.
 *
 * nginx sends exactly these paths to Laravel (deploy/nginx/ebni.conf).
 */

foreach (config('locales.supported') as $locale) {
    $prefix = $locale === config('locales.default') ? '' : $locale;

    Route::prefix($prefix)->group(function () use ($locale) {
        Route::get('/', [SeoPageController::class, 'home'])->defaults('locale', $locale);
        Route::get('/formations', [SeoPageController::class, 'catalog'])->defaults('locale', $locale);
        Route::get('/courses/{slug}', [SeoPageController::class, 'course'])->defaults('locale', $locale);
    });
}

Route::get('/robots.txt', [SeoPageController::class, 'robots']);
Route::get('/sitemap.xml', [SeoPageController::class, 'sitemap']);
