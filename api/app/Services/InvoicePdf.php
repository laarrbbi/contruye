<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single rendering path for the invoice PDF — the admin download, the
 * student download and the emailed attachment must always be byte-identical.
 */
class InvoicePdf
{
    public static function download(Invoice $invoice): Response
    {
        return self::make($invoice)->download(self::filename($invoice));
    }

    public static function data(Invoice $invoice): string
    {
        return self::make($invoice)->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return 'factura-'.$invoice->number.'.pdf';
    }

    private static function make(Invoice $invoice)
    {
        $invoice->loadMissing(['order.user', 'order.course']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'amountInWords' => SpanishNumberSpeller::amountInWords(
                $invoice->total_gross_c,
                $invoice->currency,
            ),
        ])->setPaper('a4');
    }
}
