<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOffer;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The two links of an offer e-mail. Both are signed: the signature is the
 * only thing that says which student the link belongs to.
 */
class OfferLinkController extends Controller
{
    /** The offer's button: counted as a click, then on to what it sells. */
    public function open(UserOffer $moment, OfferService $offers): RedirectResponse
    {
        $moment->loadMissing('offer.course', 'user');
        if ($moment->clicked_at === null) {
            $moment->forceFill(['clicked_at' => now()])->save();
        }

        $target = $moment->offer === null ? null : $offers->targetUrl($moment->offer, $moment->user->locale ?: config('locales.default'));

        return redirect()->away($target ?? (string) config('app.frontend_url'));
    }

    /** No more offers by e-mail. They still show in the student's course. */
    public function unsubscribe(User $user): Response
    {
        if ($user->offer_emails_opted_out_at === null) {
            $user->forceFill(['offer_emails_opted_out_at' => now()])->save();
        }

        app()->setLocale($user->locale ?: config('locales.default'));

        return response()->view('offers.unsubscribed');
    }
}
