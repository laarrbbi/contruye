<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A promotion's Arabic name is written when a human writes it, like every
 * other Arabic field: until then Arabic visitors read the French name. Required,
 * it stopped a French-speaking owner from starting a promotion at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('label_ar')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('label_ar')->nullable(false)->change();
        });
    }
};
