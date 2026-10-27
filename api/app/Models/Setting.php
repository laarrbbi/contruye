<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getValue(string $key, array $default = []): array
    {
        return self::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, array $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
