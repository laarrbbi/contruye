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
            $table->string('title_fr');
            $table->string('title_ar');
            $table->text('description_fr')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedInteger('price_dzd');
            $table->unsignedInteger('price_eur')->nullable();
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
