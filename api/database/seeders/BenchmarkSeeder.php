<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds a realistic production-scale dataset for benchmarking.
 *
 * Benchmarks against a 50-row database are actively misleading: every plan
 * looks fine and no missing index hurts. This seeds to roughly 10x the volume
 * this course is expected to reach, with the skew a real cohort has — most
 * students barely start, a few finish everything.
 *
 * Never wired into DatabaseSeeder: run it explicitly, against a throwaway
 * database.
 *
 *   php artisan db:seed --class=BenchmarkSeeder
 */
class BenchmarkSeeder extends Seeder
{
    /** 10x the expected first-year cohort. */
    private const STUDENTS = 20000;

    private const CHUNK = 1000;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('BenchmarkSeeder writes invented data and refuses to run in production.');
        }

        $course = Course::query()->where('slug', 'promocion-inmobiliaria-espana')->firstOrFail();
        $modules = $course->modules()->orderBy('order')->get();
        $lessons = Lesson::query()->whereIn('module_id', $modules->pluck('id'))->orderBy('id')->get();

        $this->command?->info(sprintf(
            'Seeding %d students over %d modules / %d lessons…',
            self::STUDENTS, $modules->count(), $lessons->count(),
        ));

        $now = now();
        $firstUserId = null;

        foreach (array_chunk(range(1, self::STUDENTS), self::CHUNK) as $chunk) {
            $users = [];
            foreach ($chunk as $i) {
                $users[] = [
                    'name' => "Bench Student {$i}",
                    'email' => "bench-{$i}@bench.invalid",
                    'phone' => '6'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                    'password' => '$2y$04$abcdefghijklmnopqrstuv',
                    'role' => 'student',
                    'locale' => ['es', 'en'][$i % 2],
                    'email_verified_at' => $now,
                    'created_at' => $now->copy()->subDays($i % 365),
                    'updated_at' => $now,
                ];
            }
            DB::table('users')->insert($users);
            $firstUserId ??= DB::table('users')->where('email', 'bench-1@bench.invalid')->value('id');
        }

        $userIds = DB::table('users')->where('email', 'like', 'bench-%@bench.invalid')
            ->orderBy('id')->pluck('id')->all();

        $this->seedOrdersAndEnrollments($course, $modules, $userIds, $now);
        $this->seedProgress($lessons, $userIds, $now);
        $this->seedChecklistTicks($userIds, $now);

        $this->command?->info('Row counts: '.json_encode($this->counts()));
    }

    /**
     * Realistic mix: ~35 % never buy, ~40 % buy a few modules, ~25 % go full.
     *
     * @param  Collection<int, Module>  $modules
     * @param  list<int>  $userIds
     */
    private function seedOrdersAndEnrollments(Course $course, $modules, array $userIds, $now): void
    {
        $orders = [];
        $enrollments = [];
        $moduleIds = $modules->pluck('id')->all();

        foreach ($userIds as $index => $userId) {
            $bucket = $index % 20;
            if ($bucket < 7) {
                continue; // browsed, never bought
            }

            $full = $bucket >= 15;
            $created = $now->copy()->subDays($index % 365);

            $orders[] = [
                'user_id' => $userId,
                'course_id' => $course->id,
                'amount' => $full ? 490 : 147,
                'currency' => 'EUR',
                'method' => ['stripe', 'manual_transfer', 'manual_bizum', 'cash'][$index % 4],
                'scope' => $full ? 'full' : 'modules',
                'status' => $index % 11 === 0 ? 'awaiting_review' : 'paid',
                'paid_at' => $index % 11 === 0 ? null : $created,
                'created_at' => $created,
                'updated_at' => $created,
            ];

            if ($index % 11 === 0) {
                continue; // awaiting review: not enrolled yet
            }

            if ($full) {
                $enrollments[] = [
                    'user_id' => $userId, 'course_id' => $course->id, 'module_id' => null,
                    'created_at' => $created, 'updated_at' => $created,
                ];
            } else {
                foreach (array_slice($moduleIds, 0, 3) as $moduleId) {
                    $enrollments[] = [
                        'user_id' => $userId, 'course_id' => $course->id, 'module_id' => $moduleId,
                        'created_at' => $created, 'updated_at' => $created,
                    ];
                }
            }

            if (count($orders) >= self::CHUNK) {
                DB::table('orders')->insert($orders);
                $orders = [];
            }
            if (count($enrollments) >= self::CHUNK) {
                DB::table('enrollments')->insert($enrollments);
                $enrollments = [];
            }
        }

        if ($orders !== []) {
            DB::table('orders')->insert($orders);
        }
        if ($enrollments !== []) {
            DB::table('enrollments')->insert($enrollments);
        }
    }

    /**
     * The long tail: most students watch the first lessons, few reach the end.
     *
     * @param  Collection<int, Lesson>  $lessons
     * @param  list<int>  $userIds
     */
    private function seedProgress($lessons, array $userIds, $now): void
    {
        $lessonIds = $lessons->pluck('id')->all();
        $rows = [];

        foreach ($userIds as $index => $userId) {
            // Zipf-ish: half drop out in module 1, a quarter finish.
            $watched = match (true) {
                $index % 4 === 0 => count($lessonIds),
                $index % 3 === 0 => (int) (count($lessonIds) * 0.5),
                $index % 2 === 0 => 4,
                default => 1,
            };

            foreach (array_slice($lessonIds, 0, $watched) as $position => $lessonId) {
                $rows[] = [
                    'user_id' => $userId,
                    'lesson_id' => $lessonId,
                    'completed_at' => $position < $watched - 1 ? $now->copy()->subDays($index % 200) : null,
                    'last_position_seconds' => 120 + ($position * 7) % 900,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                if (count($rows) >= 5000) {
                    DB::table('lesson_progress')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('lesson_progress')->insert($rows);
        }
    }

    /** @param list<int> $userIds */
    private function seedChecklistTicks(array $userIds, $now): void
    {
        $itemIds = DB::table('checklist_items')->orderBy('id')->pluck('id')->all();
        if ($itemIds === []) {
            return;
        }

        $rows = [];
        foreach ($userIds as $index => $userId) {
            if ($index % 3 !== 0) {
                continue;
            }
            foreach (array_slice($itemIds, 0, 10 + ($index % 30)) as $itemId) {
                $rows[] = [
                    'user_id' => $userId, 'checklist_item_id' => $itemId,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($rows) >= 5000) {
                    DB::table('checklist_item_user')->insert($rows);
                    $rows = [];
                }
            }
        }
        if ($rows !== []) {
            DB::table('checklist_item_user')->insert($rows);
        }
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];
        foreach (['users', 'orders', 'enrollments', 'lesson_progress', 'checklist_item_user', 'custom_tasks'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
