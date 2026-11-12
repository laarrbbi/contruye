<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\Account\DeviceSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

/**
 * The account page's own actions: the e-mail and the password (each behind
 * the current password), the devices the account is open on, and a copy of
 * everything the platform holds about the student.
 */
class AccountController extends Controller
{
    public function updateEmail(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string', 'current_password:web'],
        ]);

        $user->forceFill(['email' => $data['email']])->save();

        return UserResource::make($user->load('province'));
    }

    /**
     * A new password closes every other device: whoever had the old one (a
     * lost phone, a shared computer) is signed out with it.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        $session = $request->hasSession() ? $request->session() : null;
        $closed = DeviceSessions::closeOthers($user, $session?->getId());

        // A fresh id for this device too: the old one was issued under the old
        // password.
        $session?->migrate(true);

        return response()->json(['data' => ['closed_sessions' => $closed]]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;

        return response()->json([
            'data' => DeviceSessions::list($request->user(), $current),
            'meta' => ['supported' => DeviceSessions::supported(), 'max_devices' => config('auth.max_devices')],
        ]);
    }

    public function closeSession(Request $request, string $session): JsonResponse
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;

        abort_unless(DeviceSessions::close($request->user(), $session, $current), 404);

        return response()->json(['message' => 'ok']);
    }

    public function closeOtherSessions(Request $request): JsonResponse
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;

        return response()->json(['data' => ['closed_sessions' => DeviceSessions::closeOthers($request->user(), $current)]]);
    }

    /**
     * Everything the platform holds about the student, as one JSON file: the
     * right of access and portability (RGPD arts. 15 and 20). Course material
     * is not theirs to export; their own records are.
     */
    public function export(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user()->load('province');

        $orders = $user->orders()->with(['course', 'orderModules.module', 'invoice'])->oldest()->get();

        $data = [
            'exported_at' => now()->toISOString(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'province' => $user->province?->localizedName(),
                'created_at' => $user->created_at?->toISOString(),
            ],
            'billing' => [
                'name' => $user->billing_name,
                'fiscal_id' => $user->billing_nif,
                'address' => $user->billing_address,
            ],
            'orders' => $orders->map(fn ($order) => [
                'id' => $order->id,
                'course' => $order->course->localizedTitle(),
                'scope' => $order->scope,
                'modules' => $order->orderModules->map(fn ($line) => $line->module?->localizedTitle())->filter()->values(),
                'amount_eur' => $order->amount,
                'method' => $order->method,
                'status' => $order->status,
                'created_at' => $order->created_at?->toISOString(),
                'paid_at' => $order->paid_at?->toISOString(),
                'invoice' => $order->invoice?->number,
            ])->values(),
            'enrollments' => $user->enrollments()->with(['course', 'module'])->get()->map(fn ($enrollment) => [
                'course' => $enrollment->course?->localizedTitle(),
                'module' => $enrollment->module?->localizedTitle(),
                'since' => $enrollment->created_at?->toISOString(),
                'expires_at' => $enrollment->expires_at?->toISOString(),
            ])->values(),
            'lessons_completed' => $user->lessonProgress()->whereNotNull('completed_at')->with('lesson')->get()
                ->map(fn ($progress) => [
                    'lesson' => $progress->lesson?->localizedTitle(),
                    'completed_at' => $progress->completed_at?->toISOString(),
                ])->values(),
            'quiz_attempts' => QuizAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')
                ->with('quiz')->oldest('submitted_at')->get()
                ->map(fn ($attempt) => [
                    'quiz' => $attempt->quiz?->localizedTitle(),
                    'score' => $attempt->score,
                    'total' => $attempt->total,
                    'passed' => (bool) $attempt->passed,
                    'submitted_at' => $attempt->submitted_at?->toISOString(),
                ])->values(),
        ];

        $name = 'construye-mis-datos-'.now()->format('Y-m-d').'.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
