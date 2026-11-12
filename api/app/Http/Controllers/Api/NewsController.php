<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The news as visitors read it (/noticias): the published pieces whose date
 * has come, newest first, each in the visitor's language when it has a
 * translation and in Spanish otherwise.
 */
class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = NewsPost::visible()->orderByDesc('published_at')->orderByDesc('id')->paginate(12);

        return response()->json([
            'data' => $posts->getCollection()->map(fn (NewsPost $post) => self::card($post))->values(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = NewsPost::visible()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => self::card($post) + [
            'body_html' => $post->bodyHtml(),
            'body_lang' => $post->localizedLocale('body'),
        ]]);
    }

    /** The picture of a published piece. */
    public function image(string $slug): Response
    {
        $post = NewsPost::visible()->where('slug', $slug)->first();
        abort_if($post?->image_path === null || ! Storage::disk('local')->exists($post->image_path), 404);

        return Storage::disk('local')->response($post->image_path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** @return array<string, mixed> */
    public static function card(NewsPost $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->localizedTitle(),
            'title_lang' => $post->localizedLocale('title'),
            'summary' => $post->localizedSummary(),
            'published_at' => $post->published_at?->toIso8601String(),
            'image_url' => $post->imageUrl(),
        ];
    }
}
