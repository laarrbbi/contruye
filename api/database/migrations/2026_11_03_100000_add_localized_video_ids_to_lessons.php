<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lesson's video in other languages than the authored one: a map of
 * locale → Bunny video id. bunny_video_id stays the Spanish video, which
 * every other locale falls back to, as the lesson text does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('bunny_video_ids')->nullable()->after('bunny_video_id');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('bunny_video_ids');
        });
    }
};
