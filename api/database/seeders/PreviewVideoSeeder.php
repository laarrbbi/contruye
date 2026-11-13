<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * The Vercel preview only: the lesson videos of Modules 1 and 2 (package v5,
 * 4_videos/videos.json), served as files with their cover and subtitles
 * (web/public/video/<id>.mp4, .jpg, .vtt; VIDEO_FILES_BASE=/video). Each goes
 * to the lesson whose heading videos.json gives, with its length. The ids end
 * in the package's version, so a new cut of a video is a new address and no
 * browser or CDN keeps serving the old one. On the real server the videos are
 * on Bunny Stream, set per lesson in the admin.
 */
class PreviewVideoSeeder extends Seeder
{
    /**
     * Module => [the lesson's heading (its title), the video's id, its length in seconds].
     *
     * @var array<int, list<array{0: string, 1: string, 2: int}>>
     */
    public const VIDEOS = [
        1 => [
            ['Lección 1.1 — Define el producto y encuentra el solar', 'construye-m1-l1-1-v5', 230],
            ['Lección 1.2 — Análisis urbanístico: qué puedes construir', 'construye-m1-l1-2-v5', 182],
            ['Lección 1.3 — Due diligence: comprar sin sorpresas', 'construye-m1-l1-3-v5', 154],
            ['Lección 1.4 — La compra: forma, fiscalidad y sociedad', 'construye-m1-l1-4-v5', 187],
        ],
        2 => [
            ['Lección 2.1 — La Cuenta de Resultado Promotor (CRP)', 'construye-m2-l2-1-v5', 113],
            ['Lección 2.2 — Encaje e ingresos: el estudio de mercado', 'construye-m2-l2-2-v5', 189],
            ['Lección 2.3 — Costes con precisión', 'construye-m2-l2-3-v5', 179],
            ['Lección 2.4 — Ratios y decisión', 'construye-m2-l2-4-v5', 135],
            ['Lección 2.5 — Negociación, estructura y aprobación de la inversión', 'construye-m2-l2-5-v5', 176],
        ],
    ];

    public function run(): void
    {
        $placed = 0;
        foreach (self::VIDEOS as $module => $videos) {
            foreach ($videos as [$title, $id, $seconds]) {
                $lesson = Lesson::query()
                    ->whereHas('module', fn ($q) => $q->where('kind', 'phase')->where('order', $module))
                    ->where('title_es', $title)
                    ->first();

                if ($lesson === null) {
                    $this->command?->warn("No lesson «{$title}» in Module {$module}: its video is not placed.");

                    continue;
                }

                $lesson->update(['bunny_video_id' => $id, 'duration_seconds' => $seconds]);
                $placed++;
            }
        }

        $this->command?->info("{$placed} lesson videos placed (Modules 1 and 2).");
    }
}
