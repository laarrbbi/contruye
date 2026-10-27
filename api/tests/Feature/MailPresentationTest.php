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
 * reading every e-mail on a phone in French and Arabic:
 * - Arabic amounts read backwards ("DZD 000 72"): a plain space split the
 *   number and the right-to-left line reversed the pieces.
 * - Gmail drops the attributes of <html>, the only place the direction was
 *   set, so Arabic e-mails would be laid out left to right there.
 * - The invoice number broke across lines and reordered in Arabic.
 * - A French rejection reason inside an Arabic e-mail lost its order.
 * - "Start learning" opened the sales page instead of the course.
 */

function paidOrderFor(string $locale): Order
{
    return Order::factory()->create([
        'user_id' => User::factory()->create(['locale' => $locale])->id,
        'course_id' => Course::factory()->create(['status' => 'published', 'slug' => "cours-{$locale}"])->id,
        'amount' => 72000,
        'currency' => 'DZD',
        'status' => 'paid',
        'paid_at' => now(),
    ]);
}

it('formats amounts like the site: no breakable space, so no reordering', function () {
    // web/src/lib/utils.ts formatPrice uses Intl.NumberFormat('fr-DZ'|'ar-DZ'|'en-DZ').
    // ICU builds that same pattern for PHP.
    foreach (['fr', 'ar', 'en'] as $locale) {
        expect(Money::format(72000, 'DZD', $locale))->not->toContain(' ');
    }

    expect(Money::format(72000, 'DZD', 'fr'))->toMatch('/^72\x{202F}000\x{00A0}DA$/u')
        ->and(Money::format(75630.25, 'DZD', 'fr'))->toMatch('/^75\x{202F}630,25\x{00A0}DA$/u')
        ->and(Money::format(72000, 'DZD', 'ar'))->toContain('72.000')
        ->and(Money::format(240, 'EUR', 'fr'))->toMatch('/^240\x{00A0}€$/u');
});

it('sets the Arabic direction on the content, not only on <html>', function () {
    $order = paidOrderFor('ar');
    $invoice = Invoice::factory()->create(['order_id' => $order->id]);

    $rendered = [
        'welcome' => (new WelcomeMail($order->user))->render(),
        'payment-confirmed' => (new PaymentConfirmedMail($order))->render(),
        'receipt-rejected' => (new ReceiptRejectedMail($order, 'سبب'))->render(),
        'invoice-issued' => (new InvoiceIssuedMail($invoice))->render(),
        'reset-password' => (function () use ($order) {
            app()->setLocale('ar');

            return (string) (new ResetPasswordNotification('token'))->toMail($order->user)->render();
        })(),
    ];

    foreach ($rendered as $name => $html) {
        expect($html)->toMatch('/<body[^>]*>\s*<div[^>]*dir="rtl"/', "{$name}: the wrapper inside <body> must carry dir=\"rtl\"");
    }
});

it('shows an Arabic buyer the amount and invoice number in reading order', function () {
    $order = paidOrderFor('ar');
    $invoice = Invoice::factory()->create(['order_id' => $order->id, 'number' => 'FAC-2026-00042']);

    expect((new PaymentConfirmedMail($order))->render())
        ->toContain(Money::format(72000, 'DZD', 'ar'))
        ->not->toContain('72 000 DZD');

    expect((new InvoiceIssuedMail($invoice))->render())
        ->toContain('<span dir="ltr" style="white-space:nowrap;">FAC-2026-00042</span>')
        ->toContain(Money::format(90000, 'DZD', 'ar'));
});

it('gives a rejection reason its own direction, whatever the e-mail is in', function () {
    $order = paidOrderFor('ar');

    $french = (new ReceiptRejectedMail($order, 'Le montant du reçu (30 000 DA) ne correspond pas.'))->render();
    $arabic = (new ReceiptRejectedMail($order, 'المبلغ لا يطابق الطلب.'))->render();

    expect($french)->toMatch('/<div dir="ltr"[^>]*>\s*Le montant du reçu \(30 000 DA\)/u')
        ->and($arabic)->toMatch('/<div dir="rtl"[^>]*>\s*المبلغ لا يطابق الطلب\./u');
});

it('takes a new student straight into the course they paid for', function () {
    $order = paidOrderFor('fr');

    expect((new PaymentConfirmedMail($order))->render())
        ->toContain('href="'.rtrim(config('app.frontend_url'), '/').'/learn/cours-fr"');
});

it('punctuates French the French way', function () {
    // "Montant payé : 72 000 DA", with the space before the colon unbreakable.
    expect((new PaymentConfirmedMail(paidOrderFor('fr')))->render())->toContain("Montant payé\u{00A0}:");
});
