<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The details a student wants on their invoices, kept on their account so the
 * owner does not have to ask for them by e-mail: the name (or company), the
 * fiscal id and the address. They only pre-fill the invoice form; an issued
 * invoice keeps its own copy (invoices.buyer_*), so a later edit never
 * rewrites one already issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('province_id');
            $table->string('billing_nif', 20)->nullable()->after('billing_name');
            $table->text('billing_address')->nullable()->after('billing_nif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_nif', 'billing_address']);
        });
    }
};
