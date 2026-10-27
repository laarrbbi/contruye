<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use Illuminate\Http\JsonResponse;

class WilayaController extends Controller
{
    /**
     * The 58 wilayas, in matricule order. Public reference data: it feeds the
     * partner filters and the admin coverage editor, and never changes.
     */
    public function index(): JsonResponse
    {
        $wilayas = Wilaya::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name_fr', 'name_ar'])
            ->map(fn (Wilaya $wilaya) => [
                'id' => $wilaya->id,
                'code' => $wilaya->code,
                'name' => $wilaya->localizedName(),
                'name_fr' => $wilaya->name_fr,
                'name_ar' => $wilaya->name_ar,
            ]);

        // Reference data, so it is worth caching for a day — but PRIVATE: the
        // stateful API may attach a session cookie, which a shared cache must
        // never store. `name` is localized, hence the Vary (appended, because
        // CORS already set Vary: Origin).
        $response = response()->json(['data' => $wilayas])
            ->header('Cache-Control', 'private, max-age=86400');

        $response->setVary(['Accept-Language'], false);

        return $response;
    }
}
