<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Which service the partner provides along the promotion circuit.
            $table->string('category')->default('autre');
            $table->text('description_fr')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('wilaya')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category']);
        });

        // A partner can be recommended on several modules, with a per-module note.
        Schema::create('module_partner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('note_fr')->nullable();
            $table->string('note_ar')->nullable();
            $table->timestamps();

            $table->unique(['module_id', 'partner_id']);
            $table->index(['module_id', 'order']);
        });

        // Editable written content shown alongside the lesson video.
        Schema::table('lessons', function (Blueprint $table) {
            $table->longText('content_fr')->nullable()->after('is_free_preview');
            $table->longText('content_ar')->nullable()->after('content_fr');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['content_fr', 'content_ar']);
        });

        Schema::dropIfExists('module_partner');
        Schema::dropIfExists('partners');
    }
};
