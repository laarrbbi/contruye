<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students' lists are sorted larger firms first, then by name. Without an
 * index in that order, every page sorted all the matching companies (up to
 * 100,000) to return 24: 250 ms a page. With it, MySQL reads them in order
 * and stops at the page (6 ms).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->rawIndex('is_visible, employees desc, name', 'companies_listing_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_listing_order_index');
        });
    }
};
