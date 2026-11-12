<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    public const SOURCES = ['home', 'calculator'];

    protected $fillable = [
        'email',
        'locale',
        'source',
        'status',
        'token',
        'consent_ip',
        'confirmation_sent_at',
        'confirmed_at',
        'confirmed_ip',
        'unsubscribed_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    /** The page that confirms or unsubscribes, in the app (the click happens there, not in a mail scanner). */
    public function link(string $action): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/newsletter?'.http_build_query(['action' => $action, 'token' => $this->token]);
    }
}
