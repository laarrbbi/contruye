<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->enum('currency', ['DZD', 'EUR'])->default('DZD');
            $table->enum('method', ['chargily', 'stripe', 'manual_ccp', 'manual_baridimob', 'cash']);
            $table->enum('status', ['pending', 'awaiting_review', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('chargily_checkout_id')->nullable()->index();
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('receipt_path')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'method']);
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
