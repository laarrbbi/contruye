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
        // Reference table: the 52 Spanish provinces, seeded by ProvinceSeeder.
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            // INE code (1-52) — what every administrative form uses.
            $table->unsignedTinyInteger('code')->unique();
            $table->string('name_es');
            $table->string('name_en');
        });

        // Where a partner actually operates. A partner with national coverage
        // has no rows here — see partners.is_national.
        Schema::create('partner_province', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();

            $table->unique(['partner_id', 'province_id']);
            $table->index('province_id');
        });

        Schema::table('partners', function (Blueprint $table) {
            // Banks, insurers and online services cover the whole country:
            // they match every province filter without 52 pivot rows.
            $table->boolean('is_national')->default(false)->after('province');
        });

        // Where the learner is building: set once, every partner list filters
        // to it by default, on every device.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('locale')->constrained()->nullOnDelete();
        });

        $this->migrateFreeTextProvinces();

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('province')->nullable()->after('email');
        });

        // Write every covered province back, comma-separated — exactly the form
        // up() knows how to re-parse, so rollback + re-migrate round-trips.
        // National coverage has no text equivalent and is lost on rollback.
        foreach (DB::table('partners')->pluck('id') as $partnerId) {
            $names = DB::table('partner_province')
                ->join('provinces', 'provinces.id', '=', 'partner_province.province_id')
                ->where('partner_province.partner_id', $partnerId)
                ->orderBy('provinces.code')
                ->pluck('provinces.name_es');

            if ($names->isNotEmpty()) {
                DB::table('partners')->where('id', $partnerId)->update(['province' => $names->join(', ')]);
            }
        }

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('is_national');
        });

        Schema::dropIfExists('partner_province');
        Schema::dropIfExists('provinces');
    }

    /**
     * Carry the old free-text `partners.province` into the pivot. Values are
     * matched accent- and case-insensitively against the official names (both
     * halves of a bilingual one, "Alicante/Alacant") and the English exonym; a
     * value that matches nothing is simply dropped (the partner ends up with
     * no coverage, which the admin sees and fixes) rather than guessed at.
     */
    private function migrateFreeTextProvinces(): void
    {
        $reference = require database_path('seeders/data/provinces.php');

        // Only the columns this migration creates: the reference file has
        // grown a name per language since (2026_11_01_100001 fills those).
        if (DB::table('provinces')->count() === 0) {
            DB::table('provinces')->insert(array_map(
                fn (array $province) => array_intersect_key($province, array_flip(['code', 'name_es', 'name_en'])),
                $reference,
            ));
        }

        // Names fold to a bare a-z0-9 key so "Málaga" matches "malaga".
        $latinKey = static function (string $value): string {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

            return preg_replace('/[^a-z0-9]/', '', strtolower($ascii)) ?? '';
        };

        $byKey = [];

        foreach (DB::table('provinces')->get(['id', 'code', 'name_es', 'name_en']) as $province) {
            foreach ([...explode('/', $province->name_es), $province->name_en] as $name) {
                $key = $latinKey($name);
                if ($key !== '') {
                    $byKey[$key] = $province->id;
                }
            }
            $byKey[(string) $province->code] = $province->id;
        }

        $rows = [];
        $unmatched = [];

        $partners = DB::table('partners')->whereNotNull('province')->get(['id', 'province']);

        foreach ($partners as $partner) {
            // A free-text field may well hold "Madrid, Toledo" or "Madrid / Toledo".
            foreach (preg_split('/[,;\/|]+/u', (string) $partner->province) ?: [] as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }

                $provinceId = $byKey[$latinKey($piece)] ?? null;

                if ($provinceId === null) {
                    $unmatched[] = "partner #{$partner->id}: \"{$piece}\"";

                    continue;
                }

                $rows[$partner->id.'-'.$provinceId] = [
                    'partner_id' => $partner->id,
                    'province_id' => $provinceId,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('partner_province')->insert(array_values($rows));
        }

        // The column is dropped right after: say out loud what could not be
        // carried over, so nothing disappears unannounced.
        if ($unmatched !== []) {
            Log::warning('Province migration: unrecognised free-text values dropped', $unmatched);
            echo PHP_EOL.'  Provincias no reconocidas (cobertura a volver a introducir): '
                .implode(' ; ', $unmatched).PHP_EOL;
        }
    }
};
