<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The financials service (financials/), which runs the package's engine: the
 * one way the platform calculates a template. Every calculation carries the
 * legal parameters in force, so a change in the admin reaches every template
 * of every user the next time it is calculated; a user's inputs never carry
 * them. The service refuses any cell that is not an input of the template.
 */
class TemplateEngine
{
    public function __construct(private readonly LegalParameters $legal) {}

    /**
     * The professional template (the Financials extra).
     *
     * @param  array<string, array<string, int|float|string>>  $inputs
     * @param  list<string>  $sheets  full sheets wanted back
     * @return array<string, mixed>
     */
    public function professional(array $inputs, array $sheets = []): array
    {
        return $this->post('/calcular', ['datos' => (object) $inputs, 'hojas' => array_values($sheets)]);
    }

    /**
     * One of the five small templates of the course, by its number.
     *
     * @param  array<string, array<string, int|float|string>>  $inputs
     * @return array<string, mixed>
     */
    public function small(int $number, array $inputs): array
    {
        return $this->post("/pequenas/{$number}/calcular", ['datos' => (object) $inputs]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = Http::timeout(config('services.plantilla.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.plantilla.url'), '/').$path, [...$body, 'parametros' => $this->legal->forEngine()]);
        } catch (ConnectionException $e) {
            Log::error('Financials service unreachable', ['error' => $e->getMessage()]);
            $this->unavailable();
        }

        if ($response->status() === 422) {
            throw ValidationException::withMessages(['datos' => [(string) $response->json('error')]]);
        }

        if (! $response->successful()) {
            Log::error('Financials service failed', ['status' => $response->status(), 'path' => $path]);
            $this->unavailable();
        }

        return $response->json();
    }

    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => __('The financial template is not available at the moment. Please try again later.'),
        ], 503));
    }
}
