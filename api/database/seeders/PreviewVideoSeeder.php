<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * The Vercel preview only: Lección 1.1's own video (Spanish, 3 min 36 s), served
 * as a file with its cover and subtitles (web/public/video/modulo-1-leccion-1-1-es.mp4,
 * .jpg, .vtt; VIDEO_FILES_BASE=/video). On the real server the videos are on Bunny
 * Stream, set per lesson in the admin.
 */
class PreviewVideoSeeder extends Seeder
{
    public const FILM = 'modulo-1-leccion-1-1-es';

    /** Its length, for the course outline. */
    public const SECONDS = 216;

    public function run(): void
    {
        $lesson = Lesson::query()
            ->whereHas('module', fn ($q) => $q->where('kind', 'phase')->where('order', 1))
            ->where('order', 1)
            ->first();

        $lesson?->update(['bunny_video_id' => self::FILM, 'duration_seconds' => self::SECONDS]);
        $this->command?->info($lesson ? 'Lección 1.1 plays its video.' : 'No Lección 1.1 to put the video on.');
    }
}
