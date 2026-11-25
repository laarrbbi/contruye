<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where a word of a piece of the course stands: in the title of its lesson
 * or text, in one of its own headings or bold openings, or in its lines. The
 * search weighs the three apart (config assistant.retrieval.weights): a
 * question is asked with the words that say what a piece is about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_postings', function (Blueprint $table) {
            $table->unsignedTinyInteger('title_tf')->default(0)->after('tf');
            $table->unsignedTinyInteger('lead_tf')->default(0)->after('title_tf');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_postings', function (Blueprint $table) {
            $table->dropColumn(['title_tf', 'lead_tf']);
        });
    }
};
