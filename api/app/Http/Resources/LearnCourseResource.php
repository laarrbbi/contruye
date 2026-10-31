<?php

namespace App\Http\Resources;

use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Partner;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Support\Course\CourseFiles;
use App\Support\Markdown;
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
     * @param  Collection<int, Collection<int, QuizAttempt>>  $attemptsByQuiz  submitted attempts, keyed by quiz_id
     */
    public function __construct(
        Course $course,
        private readonly Collection $progressByLesson,
        private readonly Collection $accessibleModuleIds,
        private readonly Collection $attemptsByQuiz = new Collection,
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

        $fullAccess = $this->accessibleModuleIds->count() >= $this->modules->count();
        $finalExam = $this->quizzes->firstWhere('kind', 'final');

        return [
            'course' => [
                'id' => $this->id,
                'slug' => $this->slug,
                'title' => $this->localizedTitle(),
                'progress_percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'full_access' => $fullAccess,
                'owned_modules_count' => $this->accessibleModuleIds->count(),
                'total_modules_count' => $this->modules->count(),
                // Titles only: the texts come one at a time, behind their own gate.
                'documents' => $this->documents->map(fn (CourseDocument $document) => [
                    'slug' => $document->slug,
                    'title' => $document->localizedTitle(),
                    'access' => $document->access,
                    'is_locked' => $document->access === 'full' && ! $fullAccess,
                ])->values(),
                // The package's downloads and whether this student may take them.
                'files' => collect(CourseFiles::FILES)->map(fn (array $file, string $key) => [
                    'key' => $key,
                    'name' => $file['name'],
                    'access' => $file['access'],
                    'is_locked' => $file['access'] === 'full'
                        ? ! $fullAccess
                        : ! $this->modules->contains(fn (Module $module) => $module->isFinancials() && $this->isAccessible($module)),
                ])->values(),
                'final_exam' => $finalExam === null ? null : [
                    ...$this->quizSummary($finalExam),
                    'duration_minutes' => $finalExam->duration_minutes,
                    'is_locked' => ! $fullAccess,
                ],
            ],
            'modules' => $this->modules->map(fn (Module $module) => [
                'id' => $module->id,
                'order' => $module->order,
                'kind' => $module->kind,
                'title' => $module->localizedTitle(),
                'description' => $module->localizedDescription(),
                // "**Objetivo:** …" / "**Duración:** …", one per line as written.
                'description_html' => Markdown::toHtml($module->localizedDescription(), lineBreaks: true),
                'is_locked' => ! $this->isAccessible($module),
                // The quiz is listed for every module (title, size, the
                // student's result); its questions come behind the module's gate.
                'quiz' => $module->quiz === null ? null : $this->quizSummary($module->quiz),
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
                            'provinces' => $this->provinces($partner),
                            // English once written, the authored Spanish meanwhile.
                            'note' => app()->getLocale() === 'en' && filled($partner->pivot->note_en)
                                ? $partner->pivot->note_en
                                : $partner->pivot->note_es,
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
            'content_html' => $playable ? Markdown::toHtml($lesson->localizedContent()) : null,
            // Bullets of the lesson. Students never see the "A VALIDER" state:
            // that badge is an admin-only editorial signal.
            'key_points' => $playable ? $lesson->localizedKeyPoints() : [],
            'blocks' => $playable ? $this->blocks($lesson->contentBlocks, $module) : [],
            'has_reflex_sheet' => $playable && $lesson->is_reflex_sheet,
            // False while the lesson's video has not been shot yet, so the
            // player can say "próximamente" instead of showing an error.
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
                'body_html' => Markdown::toHtml($block->localizedBody()),
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
                        'provinces' => $this->provinces($partner),
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
     * The provinces a partner covers, for the student-side filter. A national
     * partner sends an empty list — `is_national` is what makes it match.
     *
     * @return list<array{id: int, code: int, name: string}>
     */
    private function provinces(Partner $partner): array
    {
        return $partner->provinces->map(fn ($province) => [
            'id' => $province->id,
            'code' => $province->code,
            'name' => $province->localizedName(),
        ])->values()->all();
    }

    /**
     * What the player shows about a quiz before it is opened. Never the
     * questions, never the answers.
     *
     * @return array<string, mixed>
     */
    private function quizSummary(Quiz $quiz): array
    {
        $attempts = $this->attemptsByQuiz->get($quiz->id, new Collection);

        return [
            'id' => $quiz->id,
            'title' => $quiz->localizedTitle(),
            'pass_mark' => $quiz->pass_mark,
            'questions_count' => $quiz->questionCount(),
            'attempts_count' => $attempts->count(),
            'best_score' => $attempts->max('score'),
            'passed' => $attempts->contains('passed', true),
        ];
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
