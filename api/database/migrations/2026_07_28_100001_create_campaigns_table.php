<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('label_es');
            $table->string('label_en')->nullable();
            $table->unsignedTinyInteger('percent_off');
            $table->timestamp('starts_at')->nullable();
            // Required: a promotion always has a real end date. The countdown
            // shown to students is this exact timestamp — it never resets, and
            // once it passes the price genuinely goes back up.
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'is_active', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
