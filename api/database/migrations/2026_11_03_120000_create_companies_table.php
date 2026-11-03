<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The directory of companies a promoter works with, imported from company
 * lists (php artisan directory:import). Each one has a category derived from
 * its activity code, which ties it to the modules that teach that work
 * (config/directory.php); the admin decides which ones students see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('source_ref', 64);
            $table->string('name');
            $table->string('category', 40)->index();
            $table->string('activity_code', 8)->nullable();
            $table->string('legal_form', 120)->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 120)->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 32)->nullable();
            $table->unsignedInteger('employees')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index(['is_visible', 'category', 'province_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
