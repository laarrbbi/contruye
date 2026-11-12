<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The news, as the owner writes it: drafts and published pieces, each in
 * Spanish and in the languages it has been translated into, with a date and
 * an optional picture.
 */
class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = NewsPost::query()->orderByDesc('published_at')->orderByDesc('id')->get();

        return response()->json(['data' => $posts->map(fn (NewsPost $post) => $this->payload($post))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : NewsPost::uniqueSlug((string) ($data['title_es'] ?? ''));

        $post = NewsPost::query()->create($data);
        AuditLog::record($request->user(), 'news.created', $post, ['slug' => $post->slug, 'is_published' => $post->is_published]);

        return response()->json(['data' => $this->payload($post->refresh())], 201);
    }

    public function update(Request $request, NewsPost $post): JsonResponse
    {
        $data = $this->validated($request, $post);
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        $post->update($data);
        AuditLog::record($request->user(), 'news.updated', $post, ['slug' => $post->slug, 'is_published' => $post->is_published]);

        return response()->json(['data' => $this->payload($post->refresh())]);
    }

    public function destroy(Request $request, NewsPost $post): JsonResponse
    {
        if ($post->image_path !== null) {
            Storage::disk('local')->delete($post->image_path);
        }
        AuditLog::record($request->user(), 'news.deleted', $post, ['slug' => $post->slug]);
        $post->delete();

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, NewsPost $post): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $file = $request->file('image');
        $path = $file->storeAs('news', $post->id.'-'.Str::random(12).'.'.$file->extension(), 'local');
        if ($post->image_path !== null) {
            Storage::disk('local')->delete($post->image_path);
        }
        $post->forceFill(['image_path' => $path])->save();

        return response()->json(['data' => $this->payload($post->refresh())]);
    }

    public function deleteImage(NewsPost $post): JsonResponse
    {
        if ($post->image_path !== null) {
            Storage::disk('local')->delete($post->image_path);
            $post->forceFill(['image_path' => null])->save();
        }

        return response()->json(['data' => $this->payload($post->refresh())]);
    }

    /** The picture, drafts included: the admin sees it before publishing. */
    public function image(NewsPost $post): Response
    {
        abort_if($post->image_path === null || ! Storage::disk('local')->exists($post->image_path), 404);

        return Storage::disk('local')->response($post->image_path);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?NewsPost $post = null): array
    {
        $rules = [
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('news_posts', 'slug')->ignore($post?->id)],
            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
        foreach (config('locales.supported') as $locale) {
            $rules["title_{$locale}"] = ['nullable', 'string', 'max:160'];
            $rules["summary_{$locale}"] = ['nullable', 'string', 'max:300'];
            $rules["body_{$locale}"] = ['nullable', 'string', 'max:50000'];
        }
        $data = $request->validate($rules);

        // Every piece has a title in Spanish: it is its address and its fallback.
        if (blank($data['title_es'] ?? null)) {
            throw ValidationException::withMessages(['title_es' => __('A piece of news needs a title in Spanish.')]);
        }
        if ($data['is_published'] && blank($data['body_es'] ?? null)) {
            throw ValidationException::withMessages(['body_es' => __('A published piece of news needs its text in Spanish.')]);
        }
        // Published without a date: it carries the moment it was published.
        if ($data['is_published'] && blank($data['published_at'] ?? null)) {
            $data['published_at'] = $post?->published_at ?? now();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function payload(NewsPost $post): array
    {
        $fields = ['id', 'slug', 'is_published'];
        foreach (config('locales.supported') as $locale) {
            array_push($fields, "title_{$locale}", "summary_{$locale}", "body_{$locale}");
        }

        return $post->only($fields) + [
            'published_at' => $post->published_at?->toIso8601String(),
            'is_visible' => $post->isVisible(),
            'image_url' => $post->image_path !== null
                ? url("/api/admin/news/{$post->id}/image").'?v='.substr(md5($post->image_path), 0, 8)
                : null,
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }
}
