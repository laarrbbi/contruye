<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_es');
            $table->string('title_en')->nullable();
            $table->text('description_es')->nullable();
            $table->text('description_en')->nullable();
            // Whole euros, IVA included: the price a buyer is shown.
            $table->unsignedInteger('price_eur');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('cover_image')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
