<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirmMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * The newsletter's sign-up, with double opt-in. The answer to a sign-up is
 * the same whether the address was new, pending or already confirmed, so the
 * form cannot be used to learn who is subscribed; a confirmation e-mail goes
 * at most once every ten minutes to the same address, so it cannot be used to
 * flood someone's inbox either.
 */
class NewsletterController extends Controller
{
    private const RESEND_AFTER_MINUTES = 10;

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'locale' => ['sometimes', 'string', Rule::in(config('locales.supported'))],
            'source' => ['sometimes', 'nullable', 'string', Rule::in(NewsletterSubscriber::SOURCES)],
            // A field people never see: a bot that fills every input fills this one.
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $accepted = response()->json(['message' => __('Check your inbox: we have sent you a link to confirm.')], 202);

        if (filled($data['website'] ?? null)) {
            return $accepted;
        }

        $email = mb_strtolower(trim($data['email']));
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);

        if ($subscriber->status === 'confirmed') {
            return $accepted;
        }

        if (! $subscriber->exists || $subscriber->status === 'unsubscribed') {
            $subscriber->fill([
                'status' => 'pending',
                'token' => NewsletterSubscriber::newToken(),
                'confirmed_at' => null,
                'confirmed_ip' => null,
                'unsubscribed_at' => null,
            ]);
        }

        $subscriber->fill([
            'locale' => $data['locale'] ?? app()->getLocale(),
            'source' => $data['source'] ?? $subscriber->source,
            'consent_ip' => $request->ip(),
        ]);

        $due = $subscriber->confirmation_sent_at === null
            || $subscriber->confirmation_sent_at->lt(now()->subMinutes(self::RESEND_AFTER_MINUTES));

        if ($due) {
            $subscriber->confirmation_sent_at = now();
        }
        $subscriber->save();

        if ($due) {
            Mail::to($subscriber->email)->queue(new NewsletterConfirmMail($subscriber));
        }

        return $accepted;
    }

    public function confirm(Request $request): JsonResponse
    {
        $subscriber = $this->byToken($request);

        if ($subscriber->status !== 'confirmed') {
            $subscriber->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_ip' => $request->ip(),
                'unsubscribed_at' => null,
            ]);
        }

        return response()->json(['data' => ['status' => 'confirmed', 'email' => $subscriber->email]]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $subscriber = $this->byToken($request);

        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        return response()->json(['data' => ['status' => 'unsubscribed', 'email' => $subscriber->email]]);
    }

    private function byToken(Request $request): NewsletterSubscriber
    {
        $token = $request->validate(['token' => ['required', 'string', 'size:64']])['token'];

        return NewsletterSubscriber::query()->where('token', $token)->firstOr(fn () => abort(404, __('This link is not valid any more.')));
    }
}
