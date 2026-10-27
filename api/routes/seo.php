<?php

use App\Http\Controllers\SeoPageController;
use Illuminate\Support\Facades\Route;

/*
 * Public pages, served with their own head and content for search engines
 * and link previews (SeoPageController). One URL per language: Spanish at the
 * root, English under /en. Loaded without the session middleware:
 * a crawler visit must not open a session.
 *
 * nginx sends exactly these paths to Laravel (deploy/nginx/construye.conf).
 */

foreach (config('locales.supported') as $locale) {
    $prefix = $locale === config('locales.default') ? '' : $locale;

    Route::prefix($prefix)->group(function () use ($locale) {
        Route::get('/', [SeoPageController::class, 'home'])->defaults('locale', $locale);
        Route::get('/cursos', [SeoPageController::class, 'catalog'])->defaults('locale', $locale);
        Route::get('/cursos/{slug}', [SeoPageController::class, 'course'])->defaults('locale', $locale);
    });
}

Route::get('/robots.txt', [SeoPageController::class, 'robots']);
Route::get('/sitemap.xml', [SeoPageController::class, 'sitemap']);
