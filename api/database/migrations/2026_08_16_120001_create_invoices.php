<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small key-value store for admin-editable configuration. First use:
        // the seller (émetteur) block of invoices — raison sociale, RC, NIF,
        // NIS, AI… — which the client fills in once real numbers are known.
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
            // legally required gap-free sequential numbering.
            $table->string('number')->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->unique(['year', 'sequence']);

            $table->date('issued_at');

            // Buyer block (décret 05-468: nom/raison sociale + adresse;
            // RC/NIF/AI/NIS only when the buyer is a business).
            $table->string('buyer_name');
            $table->text('buyer_address');
            $table->string('buyer_rc')->nullable();
            $table->string('buyer_nif')->nullable();
            $table->string('buyer_nis')->nullable();
            $table->string('buyer_ai')->nullable();

            // Seller block snapshotted at issuance so later settings edits
            // never rewrite an already-issued invoice.
            $table->json('seller');

            // Line items snapshotted at issuance; all amounts in centimes.
            $table->json('lines');

            $table->decimal('tva_rate', 5, 2);
            $table->unsignedBigInteger('subtotal_ht_c');
            $table->unsignedBigInteger('discount_ht_c')->default(0);
            $table->unsignedBigInteger('total_ht_c');
            $table->unsignedBigInteger('tva_c');
            $table->unsignedBigInteger('total_ttc_c');
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
