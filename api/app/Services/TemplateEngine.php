<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

/**
 * The financials service (financials/), which runs the package's engine: the
 * one way the platform calculates a template. Every calculation carries the
 * legal parameters in force, so a change in the admin reaches every template
 * of every user the next time it is calculated; a user's inputs never carry
 * them. The service refuses any cell that is not an input of the template.
 *
 * Over HTTP to the service (financials/server.js), or, where no service can
 * stay up (the Vercel preview), one `node financials/cli.js` per calculation:
 * the same engine and the same answers.
 */
class TemplateEngine
{
    public function __construct(
        private readonly LegalParameters $legal,
        private readonly ReferenceValues $reference,
    ) {}

    /**
     * The professional template (the Financials extra).
     *
     * @param  array<string, array<string, int|float|string>>  $inputs
     * @param  list<string>  $sheets  full sheets wanted back
     * @return array<string, mixed>
     */
    public function professional(array $inputs, array $sheets = []): array
    {
        return $this->post('/calcular', [
            'datos' => (object) $this->reference->withDefaults(ReferenceValues::PROFESSIONAL, $inputs),
            'hojas' => array_values($sheets),
            // The 2002 tariffs the admin has brought up to date (the engine restores the rest).
            'tarifas' => (object) $this->reference->tariffsForEngine(),
        ]);
    }

    /**
     * One of the five small templates of the course, by its number.
     *
     * @param  array<string, array<string, int|float|string>>  $inputs
     * @return array<string, mixed>
     */
    public function small(int $number, array $inputs): array
    {
        return $this->post("/pequenas/{$number}/calcular", ['datos' => (object) $this->reference->withDefaults((string) $number, $inputs)]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $body = [...$body, 'parametros' => $this->legal->forEngine()];

        [$status, $json] = config('services.plantilla.transport') === 'process'
            ? $this->viaProcess($path, $body)
            : $this->viaHttp($path, $body);

        if ($status === 422) {
            throw ValidationException::withMessages(['datos' => [(string) ($json['error'] ?? '')]]);
        }

        if ($status < 200 || $status >= 300 || ! is_array($json)) {
            Log::error('Financials service failed', ['status' => $status, 'path' => $path]);
            $this->unavailable();
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: int, 1: mixed}
     */
    private function viaHttp(string $path, array $body): array
    {
        try {
            $response = Http::timeout(config('services.plantilla.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.plantilla.url'), '/').$path, $body);
        } catch (ConnectionException $e) {
            Log::error('Financials service unreachable', ['error' => $e->getMessage()]);
            $this->unavailable();
        }

        return [$response->status(), $response->json()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: int, 1: mixed}
     */
    private function viaProcess(string $path, array $body): array
    {
        $result = Process::timeout(config('services.plantilla.timeout'))
            ->input(json_encode(['path' => $path, 'body' => $body], JSON_THROW_ON_ERROR))
            ->run([config('services.plantilla.node'), config('services.plantilla.cli')]);

        $answer = json_decode($result->output(), true);

        if (! $result->successful() || ! is_array($answer) || ! isset($answer['status'])) {
            Log::error('Financials process failed', ['exit' => $result->exitCode(), 'error' => mb_substr($result->errorOutput(), 0, 500)]);
            $this->unavailable();
        }

        return [(int) $answer['status'], $answer['body'] ?? null];
    }

    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => __('The financial template is not available at the moment. Please try again later.'),
        ], 503));
    }
}
