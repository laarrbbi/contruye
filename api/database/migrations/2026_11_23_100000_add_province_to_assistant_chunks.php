<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The assistant's index also holds the province directory (Anexo A): the
 * offices of each province, phase by phase. A piece of it belongs to one
 * province and reaches only the students of that province (Scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_chunks', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->after('lesson_id');
            $table->index('province_id');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_chunks', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
            $table->dropColumn('province_id');
        });
    }
};
