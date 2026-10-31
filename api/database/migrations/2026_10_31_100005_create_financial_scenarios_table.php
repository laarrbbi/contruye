<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's saved set of inputs for the financial template, keyed by the
 * template's own sheet names and cell addresses. The result is not stored:
 * it is recalculated from the inputs, with the professional's formulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('inputs');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_scenarios');
    }
};
