<?php

namespace App\Http\Resources;

use App\Models\Company;
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
use App\Support\Financials\TemplateCatalog;
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
 *
 * Without content (what /learn sends) it is the course's outline: titles,
 * locks, progress, block roles — no lesson text, no module text. Those come
 * one lesson and one module at a time (lessonContent, moduleContent), each
 * request behind the access gate and the content meter, so the whole course
 * never leaves the server in a single response.
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
        private readonly bool $withContent = true,
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

        // The course's own modules: Financials is the extra, outside every plan.
        $included = $this->modules->reject(fn (Module $module) => $module->isFinancials());
        $ownedIncluded = $included->filter(fn (Module $module) => $this->accessibleModuleIds->contains($module->id))->count();
        $fullAccess = $included->isNotEmpty() && $ownedIncluded >= $included->count();
        $finalExam = $this->quizzes->firstWhere('kind', 'final');
        $templates = collect(TemplateCatalog::fromConfig()->small())
            ->mapWithKeys(fn (array $t) => [$t['module_order'] => ['number' => $t['number'], 'title' => $t['title']]]);

        return [
            'course' => [
                'id' => $this->id,
                'slug' => $this->slug,
                'title' => $this->localizedTitle(),
                'progress_percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                'full_access' => $fullAccess,
                'owned_modules_count' => $ownedIncluded,
                'total_modules_count' => $included->count(),
                // Titles only: the texts come one at a time, behind their own gate.
                'documents' => $this->documents->map(fn (CourseDocument $document) => [
                    'slug' => $document->slug,
                    'title' => $document->localizedTitle(),
                    'access' => $document->access,
                    'is_locked' => $document->access === 'full' && ! $fullAccess,
                ])->values(),
                // The course's downloads and whether this student may take them.
                'files' => collect(CourseFiles::FILES)->map(fn (array $file, string $key) => [
                    'key' => $key,
                    'name' => $file['name'],
                    'access' => $file['access'],
                    // The phase module (its number) a `module` file belongs to.
                    'module' => $file['module'] ?? null,
                    'is_locked' => match ($file['access']) {
                        'full' => ! $fullAccess,
                        'module' => ! $this->modules->contains(fn (Module $module) => $module->kind === 'phase' && $module->order === $file['module'] && $this->isAccessible($module)),
                        default => ! $this->modules->contains(fn (Module $module) => $module->isFinancials() && $this->isAccessible($module)),
                    },
                ])->values(),
                'final_exam' => $finalExam === null ? null : [
                    ...$this->quizSummary($finalExam),
                    'duration_minutes' => $finalExam->duration_minutes,
                    'is_locked' => ! $fullAccess,
                ],
            ],
            'modules' => $this->modules->map(fn (Module $module) => [
                // The small template the module carries (1 to 5), if any: its calculator is the module's.
                'template' => $module->kind === 'phase' ? ($templates[$module->order] ?? null) : null,
                'id' => $module->id,
                'order' => $module->order,
                'kind' => $module->kind,
                'title' => $module->localizedTitle(),
                'description' => $this->withContent ? $module->localizedDescription() : null,
                // "**Objetivo:** …" / "**Duración:** …", one per line as written.
                'description_html' => $this->withContent ? $this->descriptionHtml($module) : null,
                // The language each text is read in: the student's, or the
                // Spanish it falls back to while its translation is pending.
                'description_locale' => $module->localizedLocale('description'),
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
                            // The reader's language once written, the authored Spanish meanwhile.
                            'note' => filled($partner->pivot->{'note_'.app()->getLocale()} ?? null)
                                ? $partner->pivot->{'note_'.app()->getLocale()}
                                : $partner->pivot->note_es,
                        ])->values()
                    : [],
                'partners_count' => $module->partners->where('is_active', true)->count(),
                // Companies of the directory this module sends the promoter to (shown, not hidden ones).
                'companies_count' => $module->kind === 'phase' ? Company::visibleCountForPhase($module->order) : 0,
                'blocks' => $this->isAccessible($module)
                    ? $this->blocks($module->contentBlocks, $module, $this->withContent)
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
            'content' => $playable && $this->withContent ? $lesson->localizedContent() : null,
            'content_html' => $playable && $this->withContent ? Markdown::toHtml($lesson->localizedContent(), glossary: true) : null,
            'content_locale' => $lesson->localizedLocale('content'),
            // Bullets of the lesson. Students never see the "A VALIDER" state:
            // that badge is an admin-only editorial signal.
            'key_points' => $playable && $this->withContent ? $lesson->localizedKeyPoints() : [],
            'blocks' => $playable && $this->withContent ? $this->blocks($lesson->contentBlocks, $module) : [],
            'has_reflex_sheet' => $playable && $lesson->is_reflex_sheet,
            // False while the lesson's video has not been shot yet, so the
            // player can say "próximamente" instead of showing an error.
            'has_video' => $lesson->videoFor(app()->getLocale()) !== null,
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
    private function blocks(Collection $blocks, Module $module, bool $withBodies = true): array
    {
        return $blocks->sortBy('order')->map(function (ContentBlock $block) use ($module, $withBodies) {
            $payload = [
                'id' => $block->id,
                'type' => $block->type,
                // Without bodies (the outline), a block says only what it is.
                'body' => $withBodies ? $block->localizedBody() : null,
                'body_html' => $withBodies ? Markdown::toHtml($block->localizedBody(), glossary: true) : null,
                'body_locale' => $block->localizedLocale('body'),
                // Which of the module's sections this is, read from the
                // instructor's Spanish heading: the player shows them apart
                // (the laws, where to go, and the closing "Partners en tu
                // provincia" on the module's Partners page).
                'role' => match (true) {
                    $block->type === 'overview' && str_starts_with(ltrim((string) $block->body_es), '## Leyes') => 'laws',
                    $block->type === 'overview' && str_starts_with(ltrim((string) $block->body_es), '## Dónde') => 'where',
                    $block->type === 'section' && str_starts_with(ltrim((string) $block->body_es), '## Partners') => 'partners',
                    default => null,
                },
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
     * One lesson's text, bullets and blocks, for the player page that shows it;
     * null when the student may not read it (a locked lesson that is not a
     * free preview).
     *
     * @return array<string, mixed>|null
     */
    public function lessonContent(Lesson $lesson, Module $module): ?array
    {
        if (! $this->isAccessible($module) && ! $lesson->is_free_preview) {
            return null;
        }

        return [
            'id' => $lesson->id,
            'content_html' => Markdown::toHtml($lesson->localizedContent(), glossary: true),
            'content_locale' => $lesson->localizedLocale('content'),
            'key_points' => $lesson->localizedKeyPoints(),
            'blocks' => $this->blocks($lesson->contentBlocks, $module),
        ];
    }

    /**
     * One module's introduction (shown for every module, as on the sales
     * page) and, for an owned module, its sections: the laws, where to go,
     * the partners in your province.
     *
     * @return array<string, mixed>
     */
    public function moduleContent(Module $module): array
    {
        return [
            'id' => $module->id,
            'description_html' => $this->descriptionHtml($module),
            'description_locale' => $module->localizedLocale('description'),
            'blocks' => $this->isAccessible($module) ? $this->blocks($module->contentBlocks, $module) : [],
        ];
    }

    private function descriptionHtml(Module $module): ?string
    {
        return Markdown::toHtml($module->localizedDescription(), lineBreaks: true, glossary: true);
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
