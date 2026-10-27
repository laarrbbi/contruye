<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reference table: the 58 official wilayas, seeded by WilayaSeeder.
        Schema::create('wilayas', function (Blueprint $table) {
            $table->id();
            // Official matricule (1-58) — what every administrative form uses.
            $table->unsignedTinyInteger('code')->unique();
            $table->string('name_fr');
            $table->string('name_ar');
        });

        // Where a partner actually operates. A partner with national coverage
        // has no rows here — see partners.is_national.
        Schema::create('partner_wilaya', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wilaya_id')->constrained()->cascadeOnDelete();

            $table->unique(['partner_id', 'wilaya_id']);
            $table->index('wilaya_id');
        });

        Schema::table('partners', function (Blueprint $table) {
            // Banks, insurers and online services cover the whole country:
            // they match every wilaya filter without 58 pivot rows.
            $table->boolean('is_national')->default(false)->after('wilaya');
        });

        // Where the learner is building: set once, every partner list filters
        // to it by default, on every device.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('wilaya_id')->nullable()->after('locale')->constrained()->nullOnDelete();
        });

        $this->migrateFreeTextWilayas();

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('wilaya');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilaya_id');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('wilaya')->nullable()->after('email');
        });

        // Write every covered wilaya back, comma-separated — exactly the form
        // up() knows how to re-parse, so rollback + re-migrate round-trips.
        // National coverage has no text equivalent and is lost on rollback.
        foreach (DB::table('partners')->pluck('id') as $partnerId) {
            $names = DB::table('partner_wilaya')
                ->join('wilayas', 'wilayas.id', '=', 'partner_wilaya.wilaya_id')
                ->where('partner_wilaya.partner_id', $partnerId)
                ->orderBy('wilayas.code')
                ->pluck('wilayas.name_fr');

            if ($names->isNotEmpty()) {
                DB::table('partners')->where('id', $partnerId)->update(['wilaya' => $names->join(', ')]);
            }
        }

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('is_national');
        });

        Schema::dropIfExists('partner_wilaya');
        Schema::dropIfExists('wilayas');
    }

    /**
     * Carry the old free-text `partners.wilaya` into the pivot. Values are
     * matched accent- and case-insensitively against the official names; a
     * value that matches nothing is simply dropped (the partner ends up with
     * no coverage, which the admin sees and fixes) rather than guessed at.
     */
    private function migrateFreeTextWilayas(): void
    {
        $reference = require database_path('seeders/data/wilayas.php');

        if (DB::table('wilayas')->count() === 0) {
            DB::table('wilayas')->insert($reference);
        }

        // Latin names fold to a bare a-z0-9 key so "Béjaïa" matches "bejaia".
        // Arabic never survives that transliteration — every name would fold to
        // the empty string and collide onto one wilaya — so Arabic is matched
        // on its own exact, whitespace-collapsed form instead.
        $latinKey = static function (string $value): string {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

            return preg_replace('/[^a-z0-9]/', '', strtolower($ascii)) ?? '';
        };
        $arabicKey = static fn (string $value): string => preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        $byLatin = [];
        $byArabic = [];
        foreach (DB::table('wilayas')->get(['id', 'code', 'name_fr', 'name_ar']) as $wilaya) {
            $key = $latinKey($wilaya->name_fr);
            if ($key !== '') {
                $byLatin[$key] = $wilaya->id;
            }
            $byLatin[(string) $wilaya->code] = $wilaya->id;
            $byArabic[$arabicKey($wilaya->name_ar)] = $wilaya->id;
        }

        $rows = [];
        $unmatched = [];
        $partners = DB::table('partners')->whereNotNull('wilaya')->get(['id', 'wilaya']);

        foreach ($partners as $partner) {
            // A free-text field may well hold "Alger, Blida" or "Alger / Tipaza".
            foreach (preg_split('/[,;\/|]+/u', (string) $partner->wilaya) ?: [] as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }

                $wilayaId = $byArabic[$arabicKey($piece)]
                    ?? ($byLatin[$latinKey($piece)] ?? null);

                if ($wilayaId === null) {
                    $unmatched[] = "partner #{$partner->id}: \"{$piece}\"";

                    continue;
                }

                $rows[$partner->id.'-'.$wilayaId] = [
                    'partner_id' => $partner->id,
                    'wilaya_id' => $wilayaId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('partner_wilaya')->insert(array_values($rows));
        }

        // The column is dropped right after: say out loud what could not be
        // carried over, so nothing disappears unannounced.
        if ($unmatched !== []) {
            Log::warning('Wilaya migration: unrecognised free-text values dropped', $unmatched);
            echo PHP_EOL.'  Wilayas non reconnues (couverture à ressaisir) : '
                .implode(' ; ', $unmatched).PHP_EOL;
        }
    }
};
