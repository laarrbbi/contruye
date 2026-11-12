<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The newsletter's list, for the owner: how many confirmed, who, from where,
 * and the confirmed addresses as a CSV for the mailing tool, each with its
 * own unsubscribe link (every newsletter must carry it).
 */
class NewsletterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'confirmed', 'unsubscribed'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $page = NewsletterSubscriber::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('email', 'like', Like::contains($search)))
            ->latest()
            ->latest('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (NewsletterSubscriber $subscriber) => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'locale' => $subscriber->locale,
                'source' => $subscriber->source,
                'status' => $subscriber->status,
                'created_at' => $subscriber->created_at?->toISOString(),
                'confirmed_at' => $subscriber->confirmed_at?->toISOString(),
                'unsubscribed_at' => $subscriber->unsubscribed_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'counts' => NewsletterSubscriber::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['email', 'locale', 'source', 'confirmed_at', 'unsubscribe_url']);

            NewsletterSubscriber::query()->where('status', 'confirmed')->orderBy('id')->lazy(200)
                ->each(fn (NewsletterSubscriber $subscriber) => fputcsv($out, array_map(self::cell(...), [
                    $subscriber->email,
                    $subscriber->locale,
                    (string) $subscriber->source,
                    $subscriber->confirmed_at?->toIso8601String(),
                    $subscriber->link('unsubscribe'),
                ])));

            fclose($out);
        }, 'newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A value a spreadsheet will not run as a formula. */
    private static function cell(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
