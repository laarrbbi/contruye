<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The quiz at the end of each module and the final exam, as the instructor
 * wrote them (questions kept as JSON, exactly as delivered), and every
 * attempt a student makes. The correct answers never leave the server before
 * an attempt is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // Null for the final exam, which covers the whole course.
            $table->foreignId('module_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('module');
            $table->string('title_es');
            $table->string('title_en')->nullable();
            // Correct answers needed to pass: 7 of 10, 32 of 40…
            $table->unsignedSmallInteger('pass_mark');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('questions');
            $table->timestamps();

            $table->index(['course_id', 'kind']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('total');
            $table->boolean('passed')->nullable();
            $table->timestamp('started_at');
            // Timed attempts (the final exam) must be submitted before this.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quizzes');
    }
};
