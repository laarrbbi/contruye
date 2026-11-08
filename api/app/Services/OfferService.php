<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Order;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserOffer;
use App\Support\LocalizedUrl;
use App\Support\Markdown;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * When each offer reaches a student, and what it says.
 *
 * Three moments, each read from the record, never guessed:
 * - after_purchase: the student's first paid order;
 * - milestone: the Nth module finished (every lesson of it completed);
 * - course_end: the final exam passed, or every lesson of a course completed.
 *
 * A moment is stored once (user_offers) with the time it actually happened,
 * so an offer's window (from delay_hours after it, for available_hours) is
 * the same whenever it is computed, and a deadline never moves.
 */
class OfferService
{
    /** A moment older than this is shown in the app but never e-mailed. */
    public const EMAIL_WITHIN_HOURS = 72;

    /** Record the moments that have happened and are not recorded yet. */
    public function sync(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $missing = array_values(array_diff(Offer::STAGES, UserOffer::query()->where('user_id', $user->id)->pluck('stage')->all()));
        if ($missing === []) {
            return;
        }

        $offers = Offer::query()->whereIn('stage', $missing)->get()->keyBy('stage');

        foreach ($missing as $stage) {
            $at = match ($stage) {
                'after_purchase' => $this->purchasedAt($user),
                'milestone' => $this->milestoneAt($user, max(1, $offers->get($stage)->modules_required ?? 1)),
                'course_end' => $this->courseEndAt($user),
            };

            if ($at !== null) {
                UserOffer::query()->firstOrCreate(['user_id' => $user->id, 'stage' => $stage], ['triggered_at' => $at]);
            }
        }
    }

    /**
     * The offers open for the student now, the latest stage first.
     *
     * @return Collection<int, array{offer: Offer, moment: UserOffer}>
     */
    public function open(User $user): Collection
    {
        $moments = UserOffer::query()->where('user_id', $user->id)->whereNull('dismissed_at')->get()->keyBy('stage');
        if ($moments->isEmpty()) {
            return new Collection;
        }

        $now = now();

        return Offer::query()->with('course')->whereIn('stage', $moments->keys())->get()
            ->filter(fn (Offer $offer) => $this->isOpen($offer, $moments[$offer->stage], $user, $now))
            ->sortByDesc(fn (Offer $offer) => array_search($offer->stage, Offer::STAGES, true))
            ->map(fn (Offer $offer) => ['offer' => $offer, 'moment' => $moments[$offer->stage]])
            ->values();
    }

    public function isOpen(Offer $offer, UserOffer $moment, User $user, ?CarbonInterface $now = null): bool
    {
        $now ??= now();
        $closes = $offer->closesAt($moment->triggered_at);

        // A closed offer never gets here: both callers read only undismissed moments.
        return $offer->isLive()
            && $offer->opensAt($moment->triggered_at)->lessThanOrEqualTo($now)
            && ($closes === null || $closes->greaterThan($now))
            // Never sell a course the student already has in full.
            && ! ($offer->course !== null && $user->hasFullAccessTo($offer->course));
    }

    /** What the student's app shows. */
    public function present(Offer $offer, UserOffer $moment): array
    {
        return [
            'stage' => $offer->stage,
            'title' => $offer->localizedTitle(),
            'body_html' => Markdown::toHtml((string) $offer->localizedBody()),
            'cta_label' => $offer->localizedCtaLabel(),
            // The language the text is read in: the student's, or the Spanish it falls back to.
            'lang' => $offer->localizedLocale('title'),
            'course' => $offer->course === null ? null : [
                'slug' => $offer->course->slug,
                'title' => $offer->course->localizedTitle(),
            ],
            // A course shows its own price on its page; a link shows the one the admin wrote.
            'url' => $offer->course === null ? $offer->url : null,
            'price_eur' => $offer->course === null ? $offer->price_eur : null,
            'closes_at' => $offer->closesAt($moment->triggered_at)?->toIso8601String(),
        ];
    }

    /** Where the offer's button leads, for a reader of $locale (e-mail links). */
    public function targetUrl(Offer $offer, string $locale): ?string
    {
        if ($offer->course === null) {
            return $offer->url;
        }

        return rtrim((string) config('app.frontend_url'), '/').LocalizedUrl::path("/cursos/{$offer->course->slug}", $locale);
    }

    private function purchasedAt(User $user): ?Carbon
    {
        $at = Order::query()->where('user_id', $user->id)->where('status', 'paid')->min('paid_at');

        return $at === null ? null : Carbon::parse($at);
    }

    /** When the student finished their Nth module. */
    private function milestoneAt(User $user, int $modules): ?Carbon
    {
        $finished = $this->finishedGroups($user, 'lessons.module_id');

        return $finished->count() >= $modules ? Carbon::parse($finished[$modules - 1]) : null;
    }

    /** The final exam passed, or every lesson of a course completed: whichever came first. */
    private function courseEndAt(User $user): ?Carbon
    {
        $exam = QuizAttempt::query()
            ->where('user_id', $user->id)->where('passed', true)->whereNotNull('submitted_at')
            ->whereHas('quiz', fn ($quiz) => $quiz->where('kind', 'final'))
            ->min('submitted_at');
        $lessons = $this->finishedGroups($user, 'modules.course_id')->first();

        $times = array_filter([$exam, $lessons]);

        return $times === [] ? null : Carbon::parse(min($times));
    }

    /**
     * When each group of lessons (a module, a course) was finished by the
     * student, oldest first: the time of the last lesson completed in it.
     *
     * @return Collection<int, string>
     */
    private function finishedGroups(User $user, string $group): Collection
    {
        return DB::table('lessons')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->leftJoin('lesson_progress as p', function ($join) use ($user) {
                $join->on('p.lesson_id', '=', 'lessons.id')
                    ->where('p.user_id', '=', $user->id)
                    ->whereNotNull('p.completed_at');
            })
            ->groupBy($group)
            ->havingRaw('count(*) = count(p.id)')
            ->selectRaw('max(p.completed_at) as finished_at')
            ->orderBy('finished_at')
            ->pluck('finished_at');
    }
}
