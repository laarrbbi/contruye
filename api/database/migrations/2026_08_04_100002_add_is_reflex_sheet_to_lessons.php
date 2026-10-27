<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the lessons that ship a printable "fiche réflexe" (module 13.2 —
 * accident grave ou mortel), offered as a downloadable PDF in addition to
 * the on-screen lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('is_reflex_sheet')->default(false)->after('is_free_preview');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $t) => $t->dropColumn('is_reflex_sheet'));
    }
};
