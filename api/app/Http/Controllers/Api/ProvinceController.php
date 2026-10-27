<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class ProvinceController extends Controller
{
    /**
     * The 52 provinces, in INE code order. Public reference data: it feeds the
     * partner filters and the admin coverage editor, and never changes.
     */
    public function index(): JsonResponse
    {
        $provinces = Province::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name_es', 'name_en'])
            ->map(fn (Province $province) => [
                'id' => $province->id,
                'code' => $province->code,
                'name' => $province->localizedName(),
                'name_es' => $province->name_es,
                'name_en' => $province->name_en,
            ]);

        // Reference data, so it is worth caching for a day — but PRIVATE: the
        // stateful API may attach a session cookie, which a shared cache must
        // never store. `name` is localized, hence the Vary (appended, because
        // CORS already set Vary: Origin).
        $response = response()->json(['data' => $provinces])
            ->header('Cache-Control', 'private, max-age=86400');

        $response->setVary(['Accept-Language'], false);

        return $response;
    }
}
