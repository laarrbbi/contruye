<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The one e-mail sent before consent: the link that confirms it. */
class NewsletterConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
        $this->locale($subscriber->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Confirm your subscription to the CONSTRUYE newsletter', locale: $this->locale));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.newsletter-confirm', with: [
            'confirmUrl' => $this->subscriber->link('confirm'),
        ]);
    }
}
