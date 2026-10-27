<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-module action checklists.
 *
 * - `checklist_items`: the curated steps, distilled from the module content
 *   and seeded — the bridge between the lesson and the student's own project.
 * - `checklist_item_user`: a row means "this student checked this step".
 * - `custom_tasks`: the student's own Google-Tasks-style additions, per module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('text_fr', 500);
            $table->string('text_ar', 500)->nullable();
            $table->timestamps();

            $table->index(['module_id', 'order']);
        });

        Schema::create('checklist_item_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['checklist_item_id', 'user_id']);
        });

        Schema::create('custom_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_tasks');
        Schema::dropIfExists('checklist_item_user');
        Schema::dropIfExists('checklist_items');
    }
};
