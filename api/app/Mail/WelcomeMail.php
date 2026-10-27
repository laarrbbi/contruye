<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        $this->locale($user->locale ?? 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Welcome to Ebni Academy', locale: $this->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
            with: ['user' => $this->user],
        );
    }
}
