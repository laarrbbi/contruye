<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order records what was paid but not how that price was reached.
 *
 * `amount` is the final figure and `discount_percent` holds the volume tier
 * alone — the coupon and campaign percentages that also moved the price are
 * computed at checkout and thrown away. So nothing could answer "why did this
 * order cost 39 920 instead of 49 900", and deleting the coupon later nulled
 * the only remaining link (orders.coupon_id is ON DELETE SET NULL).
 *
 * The quote already computes every term. This keeps it, so the provenance of a
 * paid order stops depending on catalogue rows that may be edited or deleted
 * afterwards — the same reason an invoice snapshots its lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('pricing_snapshot')->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pricing_snapshot');
        });
    }
};
