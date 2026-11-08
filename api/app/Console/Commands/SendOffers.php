<?php

namespace App\Console\Commands;

use App\Mail\OfferMail;
use App\Models\LessonProgress;
use App\Models\Offer;
use App\Models\Order;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserOffer;
use App\Services\OfferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Runs every ten minutes (routes/console.php). Records the moments of the
 * students active lately (a purchase, a finished module, the end of the
 * course), even those who have not opened the app since, then e-mails each
 * live offer to the students whose window has just opened.
 *
 * An e-mail goes once per student and offer, only while the offer is open
 * for them, only within EMAIL_WITHIN_HOURS of its opening (switching an
 * offer on never mails the whole school), and never to a student who used
 * the unsubscribe link.
 */
class SendOffers extends Command
{
    protected $signature = 'offers:send';

    protected $description = 'Record new offer moments and e-mail the offers whose window has opened';

    public function handle(OfferService $offers): int
    {
        $recent = Order::query()->where('status', 'paid')->where('paid_at', '>=', now()->subDays(7))->distinct()->pluck('user_id')
            ->merge(LessonProgress::query()->where('completed_at', '>=', now()->subDays(3))->distinct()->pluck('user_id'))
            ->merge(QuizAttempt::query()->where('passed', true)->where('submitted_at', '>=', now()->subDays(3))->distinct()->pluck('user_id'))
            ->unique();
        User::query()->whereIn('id', $recent)->each(fn (User $user) => $offers->sync($user));

        $sent = 0;
        foreach (Offer::query()->with('course')->get() as $offer) {
            if (! $offer->isLive() || ! $offer->send_email) {
                continue;
            }

            $now = now();
            UserOffer::query()
                ->where('stage', $offer->stage)
                ->whereNull('emailed_at')
                ->whereNull('dismissed_at')
                // Open now: its window started, and not more than EMAIL_WITHIN_HOURS ago.
                ->where('triggered_at', '<=', $now->copy()->subHours($offer->delay_hours))
                ->where('triggered_at', '>=', $now->copy()->subHours($offer->delay_hours + OfferService::EMAIL_WITHIN_HOURS))
                ->when($offer->available_hours !== null, fn ($query) => $query
                    ->where('triggered_at', '>', $now->copy()->subHours($offer->delay_hours + $offer->available_hours)))
                // Admins have no moments (OfferService::sync), so they are never mailed.
                ->whereHas('user', fn ($user) => $user->whereNull('offer_emails_opted_out_at'))
                ->with('user')
                ->chunkById(200, function ($moments) use ($offer, $offers, &$sent) {
                    foreach ($moments as $moment) {
                        if (! $offers->isOpen($offer, $moment, $moment->user)) {
                            continue;
                        }
                        // Claim it first: two runs at once cannot both send.
                        $claimed = UserOffer::query()->whereKey($moment->id)->whereNull('emailed_at')->update(['emailed_at' => now()]);
                        if ($claimed === 1) {
                            Mail::to($moment->user)->queue(new OfferMail($offer, $moment));
                            $sent++;
                        }
                    }
                });
        }

        $this->info("Offers: {$sent} e-mails queued.");

        return self::SUCCESS;
    }
}
