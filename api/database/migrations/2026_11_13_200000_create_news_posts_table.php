<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The news (Noticias, in the menu): pieces the owner writes in the admin,
 * in Spanish and, when there is a translation, in the other languages. The
 * table starts empty: nothing is published that nobody wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            // The address of the piece (/noticias/<slug>), the same in every language.
            $table->string('slug', 160)->unique();
            foreach (config('locales.supported') as $locale) {
                $table->string("title_{$locale}", 160)->nullable();
                $table->string("summary_{$locale}", 300)->nullable();
                $table->text("body_{$locale}")->nullable();
            }
            // A picture uploaded in the admin, on the local disk (news/…).
            $table->string('image_path')->nullable();
            $table->boolean('is_published')->default(false);
            // The date the piece carries; it shows from that moment on.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
