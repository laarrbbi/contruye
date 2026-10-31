<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo A, one row per province: its section of the annex as written
 * (`section_es`, Markdown) and the same rows as structured data, phase by
 * phase (`data`, from datos/partners_por_provincia.json), so a module can show
 * the student's province for its own phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('province_guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ccaa');
            $table->longText('section_es');
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('province_guides');
    }
};
