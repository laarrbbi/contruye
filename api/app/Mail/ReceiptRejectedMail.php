<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $reason,
    ) {
        $this->locale($order->user->locale ?: 'fr');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your payment receipt could not be validated'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.receipt-rejected',
            with: [
                'order' => $this->order,
                'course' => $this->order->course,
                'user' => $this->order->user,
                'reason' => $this->reason,
            ],
        );
    }
}
