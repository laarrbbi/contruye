<?php

use App\Models\Course;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * The news (Noticias, in the menu): written by the owner in the admin, shown
 * once published and dated, in the reader's language or in Spanish, and
 * served to search engines with its own head and article data.
 */

function newsPost(array $attributes = []): NewsPost
{
    return NewsPost::query()->create($attributes + [
        'slug' => 'nueva-ley-de-vivienda',
        'title_es' => 'Nueva ley de vivienda',
        'summary_es' => 'Lo que cambia para el promotor.',
        'body_es' => "La ley cambia **tres** cosas.\n\n<script>alert(1)</script>",
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
}

it('shows only the published pieces whose date has come, newest first', function () {
    newsPost(['slug' => 'antigua', 'title_es' => 'Antigua', 'published_at' => now()->subDays(5)]);
    newsPost(['slug' => 'reciente', 'title_es' => 'Reciente', 'published_at' => now()->subHour()]);
    newsPost(['slug' => 'borrador', 'title_es' => 'Borrador', 'is_published' => false]);
    newsPost(['slug' => 'programada', 'title_es' => 'Programada', 'published_at' => now()->addDay()]);

    $this->getJson('/api/news')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'reciente')
        ->assertJsonPath('data.1.slug', 'antigua')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/news/borrador')->assertNotFound();
    $this->getJson('/api/news/programada')->assertNotFound();
});

it('reads a piece in the visitor\'s language, in Spanish where it is not translated, without raw HTML', function () {
    newsPost(['title_en' => 'New housing law']);

    $this->getJson('/api/news/nueva-ley-de-vivienda', ['Accept-Language' => 'en', 'X-Locale' => 'en'])->assertOk()
        ->assertJsonPath('data.title', 'New housing law')
        ->assertJsonPath('data.title_lang', 'en')
        // The text has no English yet: it comes in Spanish, and says so.
        ->assertJsonPath('data.body_lang', 'es');

    $html = $this->getJson('/api/news/nueva-ley-de-vivienda')->json('data.body_html');
    expect($html)->toContain('<strong>tres</strong>')->not->toContain('<script>');
});

it('lets only the admin write, publish and delete the news', function () {
    $student = User::factory()->create(['role' => 'student']);
    $this->actingAs($student)->getJson('/api/admin/news')->assertForbidden();
    $this->actingAs($student)->postJson('/api/admin/news', ['title_es' => 'X', 'is_published' => false])->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);

    // A draft needs a title; its address comes from it.
    $this->actingAs($admin)->postJson('/api/admin/news', ['is_published' => false])->assertUnprocessable()->assertJsonValidationErrors('title_es');
    $id = $this->actingAs($admin)->postJson('/api/admin/news', ['title_es' => 'Ayudas a la vivienda 2027', 'is_published' => false])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'ayudas-a-la-vivienda-2027')
        ->assertJsonPath('data.is_visible', false)
        ->json('data.id');

    // A second piece with the same title gets its own address.
    $this->actingAs($admin)->postJson('/api/admin/news', ['title_es' => 'Ayudas a la vivienda 2027', 'is_published' => false])
        ->assertJsonPath('data.slug', 'ayudas-a-la-vivienda-2027-2');

    // Publishing needs the text in Spanish; without a date it carries today's.
    $this->actingAs($admin)->putJson("/api/admin/news/{$id}", ['title_es' => 'Ayudas a la vivienda 2027', 'is_published' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('body_es');
    $this->actingAs($admin)->putJson("/api/admin/news/{$id}", ['title_es' => 'Ayudas a la vivienda 2027', 'body_es' => 'Texto.', 'is_published' => true])
        ->assertOk()
        ->assertJsonPath('data.is_visible', true);
    expect(NewsPost::query()->find($id)->published_at)->not->toBeNull();
    $this->getJson('/api/news/ayudas-a-la-vivienda-2027')->assertOk();

    // An address that another piece has, or that is not one, is refused.
    $this->actingAs($admin)->putJson("/api/admin/news/{$id}", ['title_es' => 'X', 'slug' => 'ayudas-a-la-vivienda-2027-2', 'is_published' => false])
        ->assertJsonValidationErrors('slug');
    $this->actingAs($admin)->putJson("/api/admin/news/{$id}", ['title_es' => 'X', 'slug' => 'Con Espacios', 'is_published' => false])
        ->assertJsonValidationErrors('slug');

    $this->actingAs($admin)->deleteJson("/api/admin/news/{$id}")->assertNoContent();
    expect(NewsPost::query()->find($id))->toBeNull();
});

it('keeps a picture per piece, shows it once published, and refuses what is not an image', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $post = newsPost(['is_published' => false]);

    $this->actingAs($admin)->postJson("/api/admin/news/{$post->id}/image", ['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertJsonValidationErrors('image');

    $url = $this->actingAs($admin)->postJson("/api/admin/news/{$post->id}/image", ['image' => UploadedFile::fake()->image('obra.jpg', 1200, 630)])
        ->assertOk()->json('data.image_url');
    expect($url)->toContain("/api/admin/news/{$post->id}/image");
    $path = $post->fresh()->image_path;
    Storage::disk('local')->assertExists($path);

    // A draft's picture stays private.
    $this->getJson('/api/news/nueva-ley-de-vivienda/image')->assertNotFound();
    $post->update(['is_published' => true]);
    $this->get('/api/news/nueva-ley-de-vivienda/image')->assertOk();

    // Replaced, the old file goes; removed, so does the new one.
    $this->actingAs($admin)->postJson("/api/admin/news/{$post->id}/image", ['image' => UploadedFile::fake()->image('otra.png', 800, 600)])->assertOk();
    Storage::disk('local')->assertMissing($path);
    $this->actingAs($admin)->deleteJson("/api/admin/news/{$post->id}/image")->assertOk()->assertJsonPath('data.image_url', null);
    expect(Storage::disk('local')->files('news'))->toBe([]);
});

it('serves the news to search engines: the list, each piece, and the sitemap', function () {
    config(['seo.spa_index' => base_path('../web/index.html'), 'app.url' => 'https://construye.example']);
    Course::factory()->create(['slug' => config('seo.main_course'), 'status' => 'published']);

    // No piece yet: the list says so, and the sitemap leaves it out.
    expect($this->get('/noticias')->assertOk()->getContent())->toContain('Todavía no hay noticias');
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('/noticias');

    newsPost();
    newsPost(['slug' => 'borrador', 'is_published' => false]);

    expect($this->get('/noticias')->assertOk()->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/noticias"')
        ->toContain('href="/noticias/nueva-ley-de-vivienda"')
        ->not->toContain('borrador');

    $html = $this->get('/noticias/nueva-ley-de-vivienda')->assertOk()->getContent();
    expect($html)
        ->toContain('<title>Nueva ley de vivienda — Construye Academy</title>')
        ->toContain('<meta name="description" content="Lo que cambia para el promotor." />')
        ->toContain('<link rel="canonical" href="https://construye.example/noticias/nueva-ley-de-vivienda"')
        ->toContain('<strong>tres</strong>')
        ->not->toContain('<script>alert')
        ->not->toContain('noindex');
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
    $article = collect(json_decode($match[1], true)['@graph'])->firstWhere('@type', 'NewsArticle');
    expect($article['headline'])->toBe('Nueva ley de vivienda')
        ->and($article['inLanguage'])->toBe('es');

    // Each language at its own address.
    expect($this->get('/en/noticias/nueva-ley-de-vivienda')->assertOk()->getContent())
        ->toContain('<link rel="canonical" href="https://construye.example/en/noticias/nueva-ley-de-vivienda"');

    $this->get('/noticias/borrador')->assertNotFound();

    expect($this->get('/sitemap.xml')->getContent())
        ->toContain('<loc>https://construye.example/noticias</loc>')
        ->toContain('<loc>https://construye.example/noticias/nueva-ley-de-vivienda</loc>')
        ->toContain('<loc>https://construye.example/uk/noticias/nueva-ley-de-vivienda</loc>')
        ->not->toContain('/noticias/borrador');

    // The menu's sections are links on every public page, the home page included.
    expect($this->get('/')->getContent())
        ->toContain('href="/noticias"')
        ->toContain('href="/calculadora-precio-solar"');
});
