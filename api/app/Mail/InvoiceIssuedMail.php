<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
        $this->locale($invoice->order->user->locale ?: 'es');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Construye Academy invoice :number', ['number' => $this->invoice->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invoice-issued',
            with: [
                'invoice' => $this->invoice,
                'order' => $this->invoice->order,
                'user' => $this->invoice->order->user,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => InvoicePdf::data($this->invoice),
                InvoicePdf::filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }
}
