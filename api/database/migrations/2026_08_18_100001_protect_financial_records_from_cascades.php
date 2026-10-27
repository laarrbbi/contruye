<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid orders, their line items and the enrollments they grant are financial
 * records. They were cascading away behind catalogue edits: deleting a course
 * erased every order ever placed for it, deleting a user erased their paid
 * orders, and deleting a module erased the order lines an issued invoice is
 * built from.
 *
 * A catalogue edit must never be able to destroy a payment record. These
 * become RESTRICT, so the database refuses; the admin controllers explain the
 * refusal before it gets that far.
 */
return new class extends Migration
{
    /** @var list<array{table: string, column: string, references: string}> */
    private array $constraints = [
        ['table' => 'orders', 'column' => 'course_id', 'references' => 'courses'],
        ['table' => 'orders', 'column' => 'user_id', 'references' => 'users'],
        ['table' => 'order_modules', 'column' => 'module_id', 'references' => 'modules'],
        ['table' => 'enrollments', 'column' => 'course_id', 'references' => 'courses'],
    ];

    public function up(): void
    {
        foreach ($this->constraints as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->dropForeign([$fk['column']]);
                $table->foreign($fk['column'])->references('id')->on($fk['references'])->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->constraints as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->dropForeign([$fk['column']]);
                $table->foreign($fk['column'])->references('id')->on($fk['references'])->cascadeOnDelete();
            });
        }
    }
};
