<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Financials\SolarTeaser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The public sample of Plantilla 1 · Compra del solar (SolarTeaser): the sales
 * page's calculator and the /calculadora-precio-solar page. No account needed;
 * the full template stays in Module 1.
 */
class SolarCalculatorController extends Controller
{
    public function __construct(private readonly SolarTeaser $teaser) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->teaser->options()]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'community' => ['required', 'string', Rule::in($this->teaser->options()['communities'])],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000000'],
            'seller_company' => ['required', 'boolean'],
            'buildable_m2' => ['required', 'numeric', 'min:1', 'max:10000000'],
        ]);

        return response()->json(['data' => $this->teaser->calculate([
            'community' => $data['community'],
            'price' => $data['price'] + 0,
            'seller_company' => (bool) $data['seller_company'],
            'buildable_m2' => $data['buildable_m2'] + 0,
        ])]);
    }
}
