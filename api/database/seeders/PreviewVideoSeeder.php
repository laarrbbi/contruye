<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * The Vercel preview only: the Module 1 film (Spanish) on Lección 1.1, served as
 * a file (web/public/video/modulo-1-es.mp4, VIDEO_FILES_BASE=/video). The other
 * films go on Bunny Stream once the content is final (video/modulo-1/README.md).
 */
class PreviewVideoSeeder extends Seeder
{
    public const FILM = 'modulo-1-es';

    public function run(): void
    {
        $lesson = Lesson::query()
            ->whereHas('module', fn ($q) => $q->where('kind', 'phase')->where('order', 1))
            ->where('order', 1)
            ->first();

        $lesson?->update(['bunny_video_id' => self::FILM]);
        $this->command?->info($lesson ? 'Lección 1.1 plays the Module 1 film.' : 'No Lección 1.1 to put the film on.');
    }
}
