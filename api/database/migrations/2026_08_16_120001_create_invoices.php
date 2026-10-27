<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small key-value store for admin-editable configuration. First use:
        // the seller (emisor) block of invoices — razón social, NIF, domicilio,
        // datos registrales… — which the client fills in once real numbers are
        // known.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();

            // FAC-{year}-{sequence:05d}; the (year, sequence) pair backs the
            // correlative numbering the Reglamento de facturación requires.
            $table->string('number')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->unique(['year', 'sequence']);

            $table->date('issued_at');

            // Buyer block (RD 1619/2012, art. 6: name and address; the NIF
            // when the buyer is a business or a professional, or asks for it).
            $table->string('buyer_name');
            $table->text('buyer_address');
            $table->string('buyer_nif')->nullable();

            // Seller block snapshotted at issuance so later settings edits
            // never rewrite an already-issued invoice.
            $table->json('seller');

            // Line items snapshotted at issuance; all amounts in centimes.
            $table->json('lines');

            $table->decimal('iva_rate', 5, 2);
            $table->unsignedBigInteger('subtotal_net_c');
            $table->unsignedBigInteger('discount_net_c')->default(0);
            $table->unsignedBigInteger('total_net_c');
            $table->unsignedBigInteger('iva_c');
            $table->unsignedBigInteger('total_gross_c');
            $table->string('currency', 3);

            $table->string('payment_method_label');
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('settings');
    }
};
