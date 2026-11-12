<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The newsletter's subscribers. Double opt-in: an address is only `confirmed`
 * once its owner clicks the link sent to it, and the moment and the address
 * they did it from are kept as the proof of consent (RGPD art. 7.1, LSSI art.
 * 21). One token per subscriber confirms and unsubscribes; a new one is drawn
 * when an unsubscribed address signs up again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 5)->default('es');
            // Where the form was: the home page, the solar calculator's page…
            $table->string('source', 40)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'unsubscribed'])->default('pending')->index();
            $table->string('token', 64)->unique();
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_ip', 45)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
