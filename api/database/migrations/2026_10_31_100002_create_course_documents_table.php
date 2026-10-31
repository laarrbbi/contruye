<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course-level texts that belong to no module: the introduction, the list of
 * resources and the two annexes. `access` says who may read one: any student
 * of the course (`enrolled`) or only one with full access (`full`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->unsignedInteger('order')->default(0);
            $table->string('title_es');
            $table->string('title_en')->nullable();
            $table->longText('body_es');
            $table->longText('body_en')->nullable();
            $table->string('access', 20)->default('enrolled');
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_documents');
    }
};
