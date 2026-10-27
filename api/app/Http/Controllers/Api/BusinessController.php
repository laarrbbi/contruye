<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BusinessProfile;
use Illuminate\Http\JsonResponse;

/**
 * Public identity and contact details — the footer, the FAQ and the payment
 * pages read this. Only what the admin has actually filled is returned.
 */
class BusinessController extends Controller
{
    public function show(): JsonResponse
    {
        return response()
            ->json(['data' => BusinessProfile::public()])
            // Revalidate every time. A long max-age meant the owner saved his
            // WhatsApp number, opened the site, and saw nothing for five
            // minutes — which reads as "it did not save". The payload is tiny
            // and the SPA already deduplicates within a visit.
            ->header('Cache-Control', 'no-cache');
    }
}
