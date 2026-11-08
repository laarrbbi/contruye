<?php

use App\Support\Course\CourseSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legal parameters of the templates (the «Parámetros legales» sheet):
 * the six rates the law fixes and the ITP and AJD of the 19 communities. One
 * set for every user and every template, the big one and the five small ones;
 * the admin changes it when the law changes, and each change is kept with who
 * made it and the value before.
 *
 * The rows start as the package delivers them
 * (curso/2_plantilla_financiera/plantilla/parametros_legales.json). Values are
 * stored as the engine takes them: a fraction (0.21 = 21 %).
 */
return new class extends Migration
{
    private const PACKAGE_FILE = '2_plantilla_financiera/plantilla/parametros_legales.json';

    public function up(): void
    {
        Schema::create('legal_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->string('cell', 10);
            $table->decimal('value', 9, 6);
            $table->decimal('original_value', 9, 6)->nullable();
            $table->string('legal_basis');
            $table->string('source')->nullable();
            $table->text('used_in');
            $table->unsignedSmallInteger('order');
            $table->timestamps();
        });

        Schema::create('legal_region_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->decimal('itp', 9, 6);
            $table->decimal('ajd', 9, 6);
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('order');
            $table->timestamps();
        });

        // Every change, never edited: who, when, which value, before and after.
        Schema::create('legal_parameter_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 80);
            $table->string('field', 10);
            $table->decimal('old_value', 9, 6);
            $table->decimal('new_value', 9, 6);
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });

        $package = CourseSource::fromConfig()->json(self::PACKAGE_FILE);
        $now = now();

        DB::table('legal_parameters')->insert(array_map(fn (array $p, int $i) => [
            'code' => $p['codigo'],
            'label' => $p['etiqueta'],
            'cell' => $p['celda'],
            'value' => $p['valor'],
            'original_value' => $p['valor_original_2002'] ?? null,
            'legal_basis' => $p['base_legal'],
            'source' => $p['fuente'] ?? null,
            'used_in' => $p['donde_se_usa'],
            'order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $package['parametros'], array_keys($package['parametros'])));

        DB::table('legal_region_rates')->insert(array_map(fn (array $c, int $i) => [
            'name' => $c['nombre'],
            'itp' => $c['itp'],
            'ajd' => $c['ajd'],
            'note' => ($c['nota'] ?? '') === '' ? null : $c['nota'],
            'order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $package['comunidades'], array_keys($package['comunidades'])));

        // The package's own review date, until the admin reviews them.
        DB::table('settings')->insert([
            'key' => 'legal.review',
            'value' => json_encode(['at' => $package['revisado'], 'by' => null]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'legal.review')->delete();
        Schema::dropIfExists('legal_parameter_changes');
        Schema::dropIfExists('legal_region_rates');
        Schema::dropIfExists('legal_parameters');
    }
};
