<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo A's rows in the other languages: each province's data with the
 * "what for" column and the facts (first occupation, taxes…) translated, the
 * names of institutions and laws as written. Empty reads the Spanish.
 *
 * The locales are listed here rather than read from config/locales.php: a
 * migration must build the same schema whatever the config says later.
 */
return new class extends Migration
{
    private const LOCALES = ['en', 'fr', 'ar', 'ru', 'uk'];

    public function up(): void
    {
        Schema::table('province_guides', function (Blueprint $table) {
            foreach (self::LOCALES as $locale) {
                $table->json("data_{$locale}")->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('province_guides', function (Blueprint $table) {
            $table->dropColumn(array_map(fn (string $locale) => "data_{$locale}", self::LOCALES));
        });
    }
};
