<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reference values of the templates that the admin keeps up to date
 * (financials/valores_de_referencia.json): the 2002 tariffs of the
 * professional template and the values the inputs are presented with. A row
 * exists only for a value the admin has changed; without one, the package's
 * value applies. Every change is kept, with who made it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_reference_values', function (Blueprint $table) {
            $table->id();
            $table->string('template', 12);
            $table->string('sheet', 60);
            $table->string('cell', 10);
            $table->decimal('value', 20, 8);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['template', 'sheet', 'cell']);
        });

        Schema::create('template_reference_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template', 12);
            $table->string('sheet', 60);
            $table->string('cell', 10);
            $table->decimal('old_value', 20, 8);
            $table->decimal('new_value', 20, 8);
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_reference_changes');
        Schema::dropIfExists('template_reference_values');
    }
};
