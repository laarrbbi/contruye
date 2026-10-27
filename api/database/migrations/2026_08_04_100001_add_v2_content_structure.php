<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structure for the v2.0 curriculum (13 modules):
 * - module descriptions (the module's introductory text)
 * - lesson key_points (the bullets of each lesson)
 * - tagged content blocks (legal / partner_slot / expert_tip / text)
 * - a slot for the "carte du circuit administratif" asset
 *
 * Arabic columns become nullable: the v2.0 pass is French-only by design —
 * the AR keys exist but stay empty until a human translation is done, and the
 * localized* accessors fall back to French meanwhile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->text('description_fr')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description_fr');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->json('key_points_fr')->nullable()->after('content_ar');
            $table->json('key_points_ar')->nullable()->after('key_points_fr');
        });

        Schema::table('courses', function (Blueprint $table) {
            // Visual map of the administrative circuit (delays + counters).
            // Asset produced later; this is the slot for it.
            $table->string('circuit_map_path')->nullable()->after('cover_image');
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            // Exactly one owner: a block belongs either to a module (trailing
            // legal/partner tags) or to a lesson (inline tags).
            $table->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->default('text'); // text|legal|partner_slot|expert_tip
            $table->unsignedInteger('order')->default(0);
            $table->text('body_fr')->nullable();
            $table->text('body_ar')->nullable();
            // Which kind of partner this slot is asking for, so real partners
            // attached to the module can fill it. Never invents contacts.
            $table->string('partner_category')->nullable();
            // Passages flagged "à vérifier / à confirmer / à verrouiller":
            // shown with an "A VALIDER" badge to admins only.
            $table->boolean('needs_validation')->default(false);
            $table->timestamps();

            $table->index(['module_id', 'order']);
            $table->index(['lesson_id', 'order']);
            $table->index('needs_validation');
        });

        // The AR translation of the v2.0 content is pending a human pass.
        Schema::table('modules', fn (Blueprint $t) => $t->string('title_ar')->nullable()->change());
        Schema::table('lessons', fn (Blueprint $t) => $t->string('title_ar')->nullable()->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');

        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('circuit_map_path'));
        Schema::table('lessons', fn (Blueprint $t) => $t->dropColumn(['key_points_fr', 'key_points_ar']));
        Schema::table('modules', fn (Blueprint $t) => $t->dropColumn(['description_fr', 'description_ar']));
    }
};
