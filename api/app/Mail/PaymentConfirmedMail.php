<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale($order->user->locale ?: 'es');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your enrollment at Construye Academy is confirmed'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-confirmed',
            with: [
                'order' => $this->order,
                'course' => $this->order->course,
                'user' => $this->order->user,
            ],
        );
    }
}
