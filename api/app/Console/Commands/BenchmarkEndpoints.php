<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Times the endpoints that carry the product, counting the SQL each one runs.
 *
 * Query count matters more than milliseconds on a laptop: it is the number
 * that stays true on production hardware. Run against a benchmark database
 * seeded by BenchmarkSeeder, never against real data.
 *
 *   php artisan bench:endpoints --runs=30
 */
class BenchmarkEndpoints extends Command
{
    protected $signature = 'bench:endpoints {--runs=20} {--json=}';

    protected $description = 'Measure latency and query counts for the hot endpoints';

    public function handle(): int
    {
        $runs = max(3, (int) $this->option('runs'));

        $course = Course::query()->where('slug', 'promotion-immobiliere-algerie')->first();
        if ($course === null) {
            $this->error('Seed the curriculum first.');

            return self::FAILURE;
        }

        // A student who owns everything: the heaviest legitimate payload.
        $fullAccess = User::query()
            ->whereHas('enrollments', fn ($q) => $q->whereNull('module_id'))
            ->first();
        $partial = User::query()
            ->whereHas('enrollments', fn ($q) => $q->whereNotNull('module_id'))
            ->first();
        $admin = User::query()->where('role', 'admin')->first();

        $cases = array_filter([
            ['name' => 'GET /courses (catalog, guest)', 'user' => null, 'uri' => '/api/courses'],
            ['name' => 'GET /courses/{slug} (landing, guest)', 'user' => null, 'uri' => "/api/courses/{$course->slug}"],
            ['name' => 'GET /wilayas (reference, guest)', 'user' => null, 'uri' => '/api/wilayas'],
            $fullAccess ? ['name' => 'GET /learn (full access)', 'user' => $fullAccess, 'uri' => "/api/courses/{$course->slug}/learn"] : null,
            $partial ? ['name' => 'GET /learn (partial owner)', 'user' => $partial, 'uri' => "/api/courses/{$course->slug}/learn"] : null,
            $fullAccess ? ['name' => 'GET /checklists (full access)', 'user' => $fullAccess, 'uri' => "/api/courses/{$course->slug}/checklists"] : null,
            $fullAccess ? ['name' => 'GET /orders (mine)', 'user' => $fullAccess, 'uri' => '/api/orders'] : null,
            $admin ? ['name' => 'GET /admin/orders (page 1)', 'user' => $admin, 'uri' => '/api/admin/orders'] : null,
            $admin ? ['name' => 'GET /admin/orders (page 50)', 'user' => $admin, 'uri' => '/api/admin/orders?page=50'] : null,
            $admin ? ['name' => 'GET /admin/students', 'user' => $admin, 'uri' => '/api/admin/students'] : null,
            $admin ? ['name' => 'GET /admin/stats', 'user' => $admin, 'uri' => '/api/admin/stats'] : null,
            $admin ? ['name' => 'GET /admin/partners', 'user' => $admin, 'uri' => '/api/admin/partners'] : null,
        ]);

        $results = [];

        foreach ($cases as $case) {
            $timings = [];
            $queries = 0;
            $bytes = 0;
            $status = 0;

            for ($i = 0; $i < $runs; $i++) {
                if ($case['user'] !== null) {
                    auth()->setUser($case['user']);
                } else {
                    auth()->forgetUser();
                }

                DB::flushQueryLog();
                DB::enableQueryLog();

                $start = hrtime(true);
                $response = $this->laravel->handle(
                    Request::create($case['uri'], 'GET', server: ['HTTP_ACCEPT' => 'application/json'])
                );
                $elapsed = (hrtime(true) - $start) / 1e6;

                $log = DB::getQueryLog();
                DB::disableQueryLog();

                $timings[] = $elapsed;
                $queries = count($log);
                $status = $response->getStatusCode();
                $bytes = strlen((string) $response->getContent());
            }

            sort($timings);
            $results[] = [
                'endpoint' => $case['name'],
                'status' => $status,
                'queries' => $queries,
                'kb' => round($bytes / 1024, 1),
                'p50' => round($this->percentile($timings, 0.50), 1),
                'p95' => round($this->percentile($timings, 0.95), 1),
                'p99' => round($this->percentile($timings, 0.99), 1),
            ];
        }

        $this->table(
            ['Endpoint', 'HTTP', 'Queries', 'KB', 'p50 ms', 'p95 ms', 'p99 ms'],
            array_map(fn ($r) => array_values($r), $results),
        );

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode([
                'runs' => $runs,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'results' => $results,
            ], JSON_PRETTY_PRINT));
            $this->info("Wrote {$path}");
        }

        $this->info('Peak memory: '.round(memory_get_peak_usage(true) / 1048576, 1).' MB');

        return self::SUCCESS;
    }

    /** @param list<float> $sorted */
    private function percentile(array $sorted, float $p): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $index = (int) ceil($p * count($sorted)) - 1;

        return $sorted[max(0, min($index, count($sorted) - 1))];
    }
}
