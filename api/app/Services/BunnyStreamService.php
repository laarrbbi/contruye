<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Generates short-lived signed embed URLs for Bunny Stream videos
 * (Embed View Token Authentication). The raw video URL is never exposed:
 * the token binds the library token key, the video id and the expiry.
 */
class BunnyStreamService
{
    public function __construct(
        private readonly string $libraryId,
        private readonly string $tokenKey,
        private readonly int $ttlSeconds,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('services.bunny.library_id'),
            (string) config('services.bunny.token_key'),
            (int) config('services.bunny.embed_ttl', 14400),
        );
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function signedEmbedUrl(string $videoId): array
    {
        $expires = Carbon::now()->addSeconds($this->ttlSeconds);
        $token = hash('sha256', $this->tokenKey.$videoId.$expires->timestamp);

        $url = sprintf(
            'https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d&autoplay=false',
            $this->libraryId,
            $videoId,
            $token,
            $expires->timestamp,
        );

        return [
            'url' => $url,
            'expires_at' => $expires->toISOString(),
        ];
    }
}
