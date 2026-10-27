<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('modules', 'price_eur')) {
            Schema::table('modules', function (Blueprint $table) {
                // Null = module not sold separately (full-access only).
                $table->unsignedInteger('price_eur')->nullable()->after('title_en');
            });
        }

        if (! Schema::hasColumn('courses', 'module_discount_tiers')) {
            Schema::table('courses', function (Blueprint $table) {
                // Volume tiers [{min_modules, percent_off}, ...]; null = defaults.
                $table->json('module_discount_tiers')->nullable()->after('price_eur');
            });
        }

        if (! Schema::hasColumn('enrollments', 'module_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                // Null = full-course access; set = access to that single module.
                $table->foreignId('module_id')->nullable()->after('course_id')
                    ->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('enrollments', function (Blueprint $table) {
            // Create the replacement index BEFORE dropping the old pair-unique:
            // the user_id foreign key needs a leftmost-user_id index at all
            // times, and both indexes start with user_id.
            // MySQL unique indexes allow repeated NULLs, so full-access rows
            // (module_id NULL) are additionally guarded app-side
            // (firstOrCreate inside a transaction).
            $table->unique(['user_id', 'course_id', 'module_id']);
            $table->dropUnique(['user_id', 'course_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('scope', ['full', 'modules'])->default('full')->after('method');
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('amount');
        });

        Schema::create('order_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            // Snapshot of the per-module price at purchase time (pre-discount).
            $table->unsignedInteger('unit_price');
            $table->timestamps();

            $table->unique(['order_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_modules');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['scope', 'discount_percent']);
        });

        // Mirror up()'s ordering. enrollments.user_id's foreign key needs a
        // user_id-leftmost index at all times, and the composite unique is the
        // only one — dropping it first fails with MySQL error 1553.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unique(['user_id', 'course_id']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id', 'module_id']);
            $table->dropConstrainedForeignId('module_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('module_discount_tiers');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('price_eur');
        });
    }
};
