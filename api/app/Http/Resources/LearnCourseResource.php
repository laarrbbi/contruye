<?php

namespace App\Http\Resources;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Full course-player payload for a student (or admin): localized
 * course/modules/lessons plus the user's per-lesson progress.
 *
 * Modules the user doesn't own are LOCKED: their lessons are listed
 * (titles/durations tease the content) but progress, resume and next-lesson
 * logic only consider accessible lessons.
 *
 * Expects `modules.lessons` eager-loaded (ordered), the user's progress rows
 * keyed by lesson_id, and the set of accessible module ids.
 */
class LearnCourseResource extends JsonResource
{
    /**
     * @param  Collection<int, LessonProgress>  $progressByLesson  keyed by lesson_id
     * @param  Collection<int, int>  $accessibleModuleIds
     */
    public function __construct(
        Course $course,
        private readonly Collection $progressByLesson,
        private readonly Collection $accessibleModuleIds,
    ) {
        parent::__construct($course);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Lesson> $accessible ordered by module order, then lesson order */
        $accessible = $this->modules
            ->filter(fn (Module $module) => $this->isAccessible($module))
            ->flatMap->lessons
            ->values();

        $total = $accessible->count();
        $completed = $accessible
            ->filter(fn (Lesson $lesson) => $this->isCompleted($lesson))
            ->count();

        $nextLesson = $accessible->first(fn (Lesson $lesson) => ! $this->isCompleted($lesson))
            ?? $accessible->first();

        return [
            'course' => [
                'id' => $this->id,
                'slug' => $this->slug,
                'title' => $this->localizedTitle(),
                'progress_percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'full_access' => $this->accessibleModuleIds->count() >= $this->modules->count(),
                'owned_modules_count' => $this->accessibleModuleIds->count(),
                'total_modules_count' => $this->modules->count(),
            ],
            'modules' => $this->modules->map(fn (Module $module) => [
                'id' => $module->id,
                'order' => $module->order,
                'title' => $module->localizedTitle(),
                'description' => $module->localizedDescription(),
                'is_locked' => ! $this->isAccessible($module),
                'price_dzd' => $module->price_dzd,
                'price_eur' => $module->price_eur,
                // Partner contact details are a paid perk: only listed for
                // modules the student actually owns. Locked modules expose
                // just the count as a teaser.
                'partners' => $this->isAccessible($module)
                    ? $module->partners
                        ->where('is_active', true)
                        ->map(fn (Partner $partner) => [
                            'id' => $partner->id,
                            'name' => $partner->name,
                            'category' => $partner->category,
                            'description' => $partner->localizedDescription(),
                            'website' => $partner->website,
                            'phone' => $partner->phone,
                            'email' => $partner->email,
                            'is_national' => $partner->is_national,
                            'wilayas' => $this->wilayas($partner),
                            'note' => app()->getLocale() === 'ar'
                                ? $partner->pivot->note_ar
                                : $partner->pivot->note_fr,
                        ])->values()
                    : [],
                'partners_count' => $module->partners->where('is_active', true)->count(),
                'blocks' => $this->isAccessible($module)
                    ? $this->blocks($module->contentBlocks, $module)
                    : [],
                'lessons' => $module->lessons
                    ->map(fn (Lesson $lesson) => $this->lessonArray($lesson, $this->isAccessible($module), $module))
                    ->values(),
            ])->values(),
            'next_lesson_id' => $nextLesson?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonArray(Lesson $lesson, bool $accessible, Module $module): array
    {
        // A free preview is playable even in a locked module, so its progress
        // must round-trip; attachments stay gated on ownership alone.
        $playable = $accessible || $lesson->is_free_preview;
        $progress = $playable ? $this->progressByLesson->get($lesson->id) : null;

        return [
            'id' => $lesson->id,
            'order' => $lesson->order,
            'title' => $lesson->localizedTitle(),
            'duration_seconds' => $lesson->duration_seconds,
            'is_free_preview' => $lesson->is_free_preview,
            'is_locked' => ! $accessible && ! $lesson->is_free_preview,
            // Written lesson content follows the same access rule as playback.
            'content' => $playable ? $lesson->localizedContent() : null,
            // Bullets of the lesson. Students never see the "A VALIDER" state:
            // that badge is an admin-only editorial signal.
            'key_points' => $playable ? $lesson->localizedKeyPoints() : [],
            'blocks' => $playable ? $this->blocks($lesson->contentBlocks, $module) : [],
            'has_reflex_sheet' => $playable && $lesson->is_reflex_sheet,
            // False while the lesson's video has not been shot yet, so the
            // player can say "bientôt disponible" instead of showing an error.
            'has_video' => $lesson->bunny_video_id !== null,
            'is_completed' => $progress?->completed_at !== null,
            'last_position_seconds' => (int) ($progress?->last_position_seconds ?? 0),
            // Name + index only: storage paths must never reach the client.
            // Locked modules expose only the attachment COUNT as a teaser.
            'attachments' => $accessible
                ? collect($lesson->attachments ?? [])->values()
                    ->map(fn (array $attachment, int $index) => [
                        'name' => $attachment['name'],
                        'index' => $index,
                    ])
                : [],
            'attachments_count' => count($lesson->attachments ?? []),
        ];
    }

    /**
     * Serialise tagged content blocks. A `partner_slot` resolves to the real
     * partners attached to the module in that category — and to nothing at
     * all when none exist, so the UI shows its placeholder. Contacts are
     * never invented here.
     *
     * @param  Collection<int, ContentBlock>  $blocks
     * @return list<array<string, mixed>>
     */
    private function blocks(Collection $blocks, Module $module): array
    {
        return $blocks->sortBy('order')->map(function (ContentBlock $block) use ($module) {
            $payload = [
                'id' => $block->id,
                'type' => $block->type,
                'body' => $block->localizedBody(),
            ];

            if ($block->type === 'partner_slot') {
                $payload['partner_category'] = $block->partner_category;
                // Contacts follow module ownership, never lesson playability:
                // a free-preview lesson inside an unowned module must not hand
                // out the paid perk. The slot still renders its count tease.
                $slotPartners = $this->isAccessible($module)
                    ? $module->partners
                    : new Collection;
                $payload['partners'] = $slotPartners
                    ->where('is_active', true)
                    ->when(
                        $block->partner_category !== null,
                        fn ($partners) => $partners->where('category', $block->partner_category),
                    )
                    ->map(fn (Partner $partner) => [
                        'id' => $partner->id,
                        'name' => $partner->name,
                        'category' => $partner->category,
                        'phone' => $partner->phone,
                        'email' => $partner->email,
                        'website' => $partner->website,
                        'is_national' => $partner->is_national,
                        'wilayas' => $this->wilayas($partner),
                    ])->values()->all();
                $payload['partners_count'] = $module->partners
                    ->where('is_active', true)
                    ->when(
                        $block->partner_category !== null,
                        fn ($partners) => $partners->where('category', $block->partner_category),
                    )->count();
            }

            return $payload;
        })->values()->all();
    }

    /**
     * The wilayas a partner covers, for the student-side filter. A national
     * partner sends an empty list — `is_national` is what makes it match.
     *
     * @return list<array{id: int, code: int, name: string}>
     */
    private function wilayas(Partner $partner): array
    {
        return $partner->wilayas->map(fn ($wilaya) => [
            'id' => $wilaya->id,
            'code' => $wilaya->code,
            'name' => $wilaya->localizedName(),
        ])->values()->all();
    }

    private function isAccessible(Module $module): bool
    {
        return $this->accessibleModuleIds->contains($module->id);
    }

    private function isCompleted(Lesson $lesson): bool
    {
        return $this->progressByLesson->get($lesson->id)?->completed_at !== null;
    }
}
