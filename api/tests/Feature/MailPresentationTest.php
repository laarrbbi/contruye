<?php

use App\Mail\InvoiceIssuedMail;
use App\Mail\PaymentConfirmedMail;
use App\Mail\ReceiptRejectedMail;
use App\Mail\WelcomeMail;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Money;

/*
 * What a buyer reads in their inbox, checked for the defects found by
 * reading every e-mail on a phone (on the platform this one was adapted
 * from, and kept here because each one can come back):
 * - An amount split by a plain space wrapped in the middle of the number.
 * - Gmail drops the attributes of <html>, so the direction set only there
 *   was lost: it is set on the content as well.
 * - The invoice number broke across lines.
 * - A reason typed in another script inside the e-mail lost its order.
 * - "Start learning" opened the sales page instead of the course.
 */

function paidOrderFor(string $locale): Order
{
    return Order::factory()->create([
        'user_id' => User::factory()->create(['locale' => $locale])->id,
        'course_id' => Course::factory()->create(['status' => 'published', 'slug' => "curso-{$locale}"])->id,
        'amount' => 1490,
        'currency' => 'EUR',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

it('formats amounts like the site: no breakable space, so no wrapping', function () {
    // web/src/lib/utils.ts formatPrice uses Intl.NumberFormat('es-ES'|'en-GB').
    // ICU builds that same pattern for PHP.
    foreach (config('locales.supported') as $locale) {
        expect(Money::format(1490, 'EUR', $locale))->not->toContain(' ');
    }

    expect(Money::format(1490, 'EUR', 'es'))->toBe("1.490\u{00A0}€")
        ->and(Money::format(490, 'EUR', 'es'))->toBe("490\u{00A0}€")
        ->and(Money::format(40.5, 'EUR', 'es'))->toBe("40,50\u{00A0}€")
        ->and(Money::format(1490, 'EUR', 'en'))->toBe('€1,490');
});

it('sets the direction on the content, not only on <html>', function () {
    $order = paidOrderFor('es');
    $invoice = Invoice::factory()->create(['order_id' => $order->id]);

    $rendered = [
        'welcome' => (new WelcomeMail($order->user))->render(),
        'payment-confirmed' => (new PaymentConfirmedMail($order))->render(),
        'receipt-rejected' => (new ReceiptRejectedMail($order, 'Motivo'))->render(),
        'invoice-issued' => (new InvoiceIssuedMail($invoice))->render(),
        'reset-password' => (function () use ($order) {
            app()->setLocale('es');

            return (string) (new ResetPasswordNotification('token'))->toMail($order->user)->render();
        })(),
    ];

    foreach ($rendered as $name => $html) {
        expect($html)->toMatch('/<body[^>]*>\s*<div[^>]*dir="ltr"/', "{$name}: the wrapper inside <body> must carry dir=\"ltr\"");
    }
});

it('keeps the amount and the invoice number in one piece', function () {
    $order = paidOrderFor('es');
    $invoice = Invoice::factory()->create(['order_id' => $order->id, 'number' => 'FAC-2026-00042']);

    expect((new PaymentConfirmedMail($order))->render())
        ->toContain(Money::format(1490, 'EUR', 'es'))
        ->not->toContain('1490 EUR');

    expect((new InvoiceIssuedMail($invoice))->render())
        ->toContain('<span dir="ltr" style="white-space:nowrap;">FAC-2026-00042</span>')
        ->toContain(Money::format(490, 'EUR', 'es'));
});

it('gives a rejection reason its own direction, whatever the e-mail is in', function () {
    $order = paidOrderFor('es');

    $spanish = (new ReceiptRejectedMail($order, 'El importe del justificante (300 €) no coincide.'))->render();
    $arabic = (new ReceiptRejectedMail($order, 'المبلغ لا يطابق الطلب.'))->render();

    expect($spanish)->toMatch('/<div dir="ltr"[^>]*>\s*El importe del justificante \(300 €\)/u')
        ->and($arabic)->toMatch('/<div dir="rtl"[^>]*>\s*المبلغ لا يطابق الطلب\./u');
});

it('takes a new student straight into the course they paid for', function () {
    $order = paidOrderFor('es');

    expect((new PaymentConfirmedMail($order))->render())
        ->toContain('href="'.rtrim(config('app.frontend_url'), '/').'/learn/curso-es"');
});

it('writes the e-mail in the buyer\'s language, whatever the admin works in', function () {
    app()->setLocale('en');
    $html = (new PaymentConfirmedMail(paidOrderFor('es')))->render();

    expect($html)->toContain('Importe pagado:')
        ->and($html)->toContain('<html lang="es"');
});
