<?php

namespace App\Mail;

use App\Models\Offer;
use App\Models\UserOffer;
use App\Support\Markdown;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * One of the three offers, by e-mail, in the student's language (or the
 * Spanish it falls back to). Every one carries its unsubscribe link, in the
 * text and in the List-Unsubscribe header.
 */
class OfferMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Offer $offer, public UserOffer $moment)
    {
        $this->locale($moment->user->locale ?: config('locales.default'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) $this->offer->localizedTitle());
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $closes = $this->offer->closesAt($this->moment->triggered_at);
        // The offer's text keeps its own language and direction: the Spanish it
        // falls back to reads left to right inside an Arabic e-mail.
        $textLang = $this->offer->localizedLocale('title');
        $textDir = in_array($textLang, config('locales.rtl', []), true) ? 'rtl' : 'ltr';

        return new Content(
            view: 'mail.offer',
            with: [
                'user' => $this->moment->user,
                'title' => $this->offer->localizedTitle(),
                'textLang' => $textLang,
                'textDir' => $textDir,
                'bodyHtml' => Markdown::toHtml((string) $this->offer->localizedBody()),
                'price' => $this->offer->course_id === null && $this->offer->price_eur !== null
                    ? Money::format($this->offer->price_eur, 'EUR')
                    : null,
                'closesAt' => $closes?->copy()->timezone(config('app.business_timezone'))->locale(app()->getLocale())->isoFormat('LLL'),
                'ctaUrl' => URL::signedRoute('offers.open', ['moment' => $this->moment->id]),
                'ctaLabel' => $this->offer->localizedCtaLabel() ?: __('See the offer'),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('offers.unsubscribe', ['user' => $this->moment->user_id]);
    }
}
