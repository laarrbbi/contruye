<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three offers a student is shown along the course: one a day after the
 * purchase (for a day), one at the first milestone, the big one at the end.
 *
 * `offers` holds the three slots. They are created here, empty and inactive:
 * the owner writes what each one sells in the admin, and nothing reaches a
 * student before that. `user_offers` records, per student and stage, when the
 * trigger happened and what the student did with the offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $locales = config('locales.supported');

        Schema::create('offers', function (Blueprint $table) use ($locales) {
            $table->id();
            $table->string('stage', 20)->unique();
            $table->boolean('is_active')->default(false);
            foreach ($locales as $locale) {
                $table->string("title_{$locale}")->nullable();
                $table->text("body_{$locale}")->nullable();
                $table->string("cta_label_{$locale}", 80)->nullable();
            }
            // What the button leads to: a course of this platform, or a page elsewhere.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 500)->nullable();
            // Whole euros, shown on the card. Only for a link: a course shows its own price.
            $table->unsignedInteger('price_eur')->nullable();
            // Hours after the trigger before it shows, and for how long (null: until dismissed).
            $table->unsignedSmallInteger('delay_hours')->default(0);
            $table->unsignedSmallInteger('available_hours')->nullable();
            // The milestone: how many completed modules.
            $table->unsignedTinyInteger('modules_required')->default(1);
            $table->boolean('send_email')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->timestamp('triggered_at');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stage']);
            $table->index(['stage', 'triggered_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Set by the unsubscribe link of an offer e-mail. Offers still show in the app.
            $table->timestamp('offer_emails_opted_out_at')->nullable();
        });

        $now = now();
        DB::table('offers')->insert([
            ['stage' => 'after_purchase', 'delay_hours' => 24, 'available_hours' => 24, 'created_at' => $now, 'updated_at' => $now],
            ['stage' => 'milestone', 'delay_hours' => 0, 'available_hours' => null, 'created_at' => $now, 'updated_at' => $now],
            ['stage' => 'course_end', 'delay_hours' => 0, 'available_hours' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('offer_emails_opted_out_at');
        });
        Schema::dropIfExists('user_offers');
        Schema::dropIfExists('offers');
    }
};
