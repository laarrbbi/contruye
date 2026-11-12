<?php

use App\Models\Course;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;

/*
 * The data centers course, announced in the menu: a page that says it is
 * coming and nothing more, and a sign-up whose source tells the owner who
 * came for it.
 */

it('serves the coming-soon page to search engines, in every language, and lists it', function () {
    config(['seo.spa_index' => base_path('../web/index.html'), 'app.url' => 'https://construye.example']);
    Course::factory()->create(['slug' => config('seo.main_course'), 'status' => 'published']);

    expect($this->get('/data-centers')->assertOk()->getContent())
        ->toContain('<title>Curso de construcción de data centers (próximamente) — Construye Academy</title>')
        ->toContain('<link rel="canonical" href="https://construye.example/data-centers"')
        ->toContain('Curso de construcción de data centers</h1>')
        ->not->toContain('noindex');

    expect($this->get('/en/data-centers')->assertOk()->getContent())
        ->toContain('Data center construction course</h1>');

    expect($this->get('/sitemap.xml')->getContent())
        ->toContain('<loc>https://construye.example/data-centers</loc>')
        ->toContain('<loc>https://construye.example/ar/data-centers</loc>');

    // In the menu of every public page.
    expect($this->get('/')->getContent())->toContain('href="/data-centers"');
});

it('signs up those who want to hear when it opens, marked as such', function () {
    Mail::fake();

    $this->postJson('/api/newsletter', ['email' => 'dc@example.es', 'source' => 'datacenters'])->assertStatus(202);

    expect(NewsletterSubscriber::query()->sole()->source)->toBe('datacenters');
});
