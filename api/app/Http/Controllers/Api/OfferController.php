<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserOffer;
use App\Services\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The offers a student sees in the app: the ones whose moment has come,
 * that the admin has written and switched on, and that the student has not
 * closed. What the student does with one is recorded for the admin's figures.
 */
class OfferController extends Controller
{
    private const ACTIONS = ['seen' => 'seen_at', 'click' => 'clicked_at', 'dismiss' => 'dismissed_at'];

    public function __construct(private readonly OfferService $offers) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->offers->sync($user);

        return response()->json([
            'data' => $this->offers->open($user)
                ->map(fn (array $open) => $this->offers->present($open['offer'], $open['moment']))
                ->values(),
        ]);
    }

    /** seen, click or dismiss: the first time counts, later ones change nothing. */
    public function record(Request $request, string $stage, string $action): Response
    {
        $column = self::ACTIONS[$action];

        UserOffer::query()
            ->where('user_id', $request->user()->id)
            ->where('stage', $stage)
            ->whereNull($column)
            ->update([$column => now()]);

        return response()->noContent();
    }
}
