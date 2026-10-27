<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C21/C40: unique(user_id, course_id, module_id) never stopped duplicate
 * full-course enrollments, because MySQL treats NULLs as distinct. Mapping
 * NULL to 0 (module ids start at 1) makes "one full-access row per student
 * per course" a database rule. Functional key parts need MySQL 8.0.13+.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('enrollments')
            ->whereNull('module_id')
            ->select('user_id', 'course_id')
            ->groupBy('user_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        // Enrollments trace payments: which duplicate to keep is a human
        // decision, never one a migration makes silently.
        if ($duplicates > 0) {
            throw new RuntimeException(
                "{$duplicates} student/course pair(s) hold more than one full-access enrollment. "
                .'Resolve them by hand, then run the migration again.'
            );
        }

        DB::statement('ALTER TABLE enrollments ADD UNIQUE INDEX enrollments_one_access_unique (user_id, course_id, (IFNULL(module_id, 0)))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE enrollments DROP INDEX enrollments_one_access_unique');
    }
};
