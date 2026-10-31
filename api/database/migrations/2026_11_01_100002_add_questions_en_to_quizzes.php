<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English quiz questions, beside the French, Arabic, Russian and Ukrainian
 * ones (2026_11_01_100001). Quizzes were created with an English title but
 * no English questions; an empty column reads the instructor's Spanish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->json('questions_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('questions_en');
        });
    }
};
