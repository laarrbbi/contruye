<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the buyer sent the CCP / BaridiMob receipt. A buyer who transferred
 * 90 000 DA and hears nothing for days is the one who asks for a refund;
 * the admin needs to see how long each receipt has waited, and updated_at
 * moves on every edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('receipt_uploaded_at')->nullable()->after('receipt_path');
        });

        // Best available value for receipts already sent: the last update,
        // which for an order still awaiting review is the upload itself.
        DB::table('orders')
            ->whereNotNull('receipt_path')
            ->update(['receipt_uploaded_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('receipt_uploaded_at');
        });
    }
};
