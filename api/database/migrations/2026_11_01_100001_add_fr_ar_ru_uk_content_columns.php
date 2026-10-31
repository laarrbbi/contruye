<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * French, Arabic, Russian and Ukrainian, beside Spanish and English: every
 * text a visitor or a student reads gets a column per language. They are all
 * nullable, and an empty one reads the authored Spanish (HasLocalizedAttributes),
 * so a language can ship before every text in it is written.
 *
 * The locales are listed here rather than read from config/locales.php: a
 * migration must build the same schema whatever the config says later.
 */
return new class extends Migration
{
    private const LOCALES = ['fr', 'ar', 'ru', 'uk'];

    /** table => [column base => column type] */
    private const COLUMNS = [
        'courses' => ['title' => 'string', 'description' => 'text'],
        'modules' => ['title' => 'string', 'description' => 'text'],
        'lessons' => ['title' => 'string', 'content' => 'longText', 'key_points' => 'json'],
        'content_blocks' => ['body' => 'text'],
        'checklist_items' => ['text' => 'string500'],
        'partners' => ['description' => 'text'],
        'module_partner' => ['note' => 'string'],
        'campaigns' => ['label' => 'string'],
        'course_documents' => ['title' => 'string', 'body' => 'longText'],
        'quizzes' => ['title' => 'string', 'questions' => 'json'],
        'provinces' => ['name' => 'string'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $base => $type) {
                    foreach (self::LOCALES as $locale) {
                        $name = "{$base}_{$locale}";
                        match ($type) {
                            'string500' => $table->string($name, 500)->nullable(),
                            default => $table->{$type}($name)->nullable(),
                        };
                    }
                }
            });
        }

        // Province names are reference data, not content: a database migrated
        // before this one gets them now rather than on the next seed.
        foreach (require database_path('seeders/data/provinces.php') as $province) {
            DB::table('provinces')->where('code', $province['code'])->update(array_intersect_key(
                $province,
                array_flip(array_map(fn (string $locale) => "name_{$locale}", self::LOCALES)),
            ));
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $names = [];
                foreach (array_keys($columns) as $base) {
                    foreach (self::LOCALES as $locale) {
                        $names[] = "{$base}_{$locale}";
                    }
                }
                $table->dropColumn($names);
            });
        }
    }
};
