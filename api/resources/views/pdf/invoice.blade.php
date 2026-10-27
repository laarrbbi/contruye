@php
    $symbol = $invoice->currency === 'DZD' ? 'DA' : '€';
    $money = fn (int $centimes) => number_format($centimes / 100, 2, ',', ' ').' '.$symbol;
    $seller = $invoice->seller;
    $rate = rtrim(rtrim(number_format((float) $invoice->tva_rate, 2, ',', ' '), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $invoice->number }}</title>
    <style>
        @page { margin: 16mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1c2434; line-height: 1.45; }

        .top { width: 100%; border-bottom: 3px solid #006FFE; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 17pt; font-weight: bold; color: #223A5E; letter-spacing: .04em; }
        .brand span { color: #006FFE; }
        .brand-sub { font-size: 8pt; color: #6b7280; letter-spacing: .14em; text-transform: uppercase; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 17pt; margin: 0; color: #223A5E; text-transform: uppercase; letter-spacing: .06em; }
        .doc-meta { font-size: 9pt; color: #4b5563; }

        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; }

        .party { width: 49%; border: 1px solid #d5d9e2; border-radius: 4px; padding: 9px 11px; }
        .party h2 { font-size: 8pt; text-transform: uppercase; letter-spacing: .1em; color: #006FFE; margin: 0 0 5px; }
        .party .name { font-weight: bold; font-size: 10.5pt; }
        .party .row { font-size: 8.8pt; color: #374151; }
        .party .row strong { color: #1c2434; }

        table.lines { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.lines th { background: #223A5E; color: #ffffff; font-size: 8.4pt; text-transform: uppercase; letter-spacing: .05em; padding: 6px 8px; text-align: left; }
        table.lines th.num, table.lines td.num { text-align: right; white-space: nowrap; }
        table.lines td { border-bottom: 1px solid #e2e6ee; padding: 7px 8px; }

        table.totals { border-collapse: collapse; width: 62mm; margin-top: 8px; margin-left: auto; }
        table.totals td { padding: 4px 8px; font-size: 9.3pt; }
        table.totals td.num { text-align: right; white-space: nowrap; }
        table.totals tr.grand td { border-top: 2px solid #223A5E; font-weight: bold; font-size: 10.5pt; color: #223A5E; padding-top: 6px; }

        .words { margin-top: 12px; background: #f4f6fa; border-left: 4px solid #006FFE; padding: 8px 10px; font-size: 9pt; }
        .payment { margin-top: 10px; font-size: 9pt; }
        .payment strong { color: #223A5E; }
        .notes { margin-top: 8px; font-size: 8.8pt; color: #4b5563; font-style: italic; }

        .footer { position: fixed; bottom: -6mm; left: 0; right: 0; border-top: 1px solid #d5d9e2; padding-top: 5px; font-size: 7.4pt; color: #6b7280; text-align: center; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <table class="layout top">
        <tr>
            <td>
                <div class="brand">EBNI <span>ACADEMY</span></div>
                <div class="brand-sub">Formation en promotion immobilière</div>
            </td>
            <td class="doc-title">
                <h1>Facture</h1>
                <div class="doc-meta">
                    N° <strong>{{ $invoice->number }}</strong><br>
                    Date d'émission : {{ $invoice->issued_at->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="layout">
        <tr>
            <td class="party">
                <h2>Émetteur</h2>
                <div class="name">{{ $seller['business_name'] }}</div>
                <div class="row">{{ $seller['legal_form'] }} au capital de {{ $seller['capital'] }}</div>
                <div class="row">{{ $seller['address'] }}</div>
                {{-- One identifier per line: paired in a narrow column, "NIF :" ended
                     a line with its number on the next. --}}
                <div class="row"><strong>RC :</strong> {{ $seller['rc'] }}</div>
                <div class="row"><strong>NIF :</strong> {{ $seller['nif'] }}</div>
                <div class="row"><strong>NIS :</strong> {{ $seller['nis'] }}</div>
                <div class="row"><strong>AI :</strong> {{ $seller['ai'] }}</div>
                @if ($seller['phone'] !== '' || $seller['email'] !== '')
                    <div class="row">
                        {{ $seller['phone'] }}@if ($seller['phone'] !== '' && $seller['email'] !== '') — @endif{{ $seller['email'] }}
                    </div>
                @endif
                @if ($seller['bank_details'] !== '')
                    <div class="row"><strong>Banque :</strong> {{ $seller['bank_details'] }}</div>
                @endif
            </td>
            <td style="width: 2%;"></td>
            <td class="party">
                <h2>Client</h2>
                <div class="name">{{ $invoice->buyer_name }}</div>
                <div class="row">{{ $invoice->buyer_address }}</div>
                @if ($invoice->buyer_rc)
                    <div class="row"><strong>RC :</strong> {{ $invoice->buyer_rc }}</div>
                @endif
                @if ($invoice->buyer_nif)
                    <div class="row"><strong>NIF :</strong> {{ $invoice->buyer_nif }}</div>
                @endif
                @if ($invoice->buyer_nis)
                    <div class="row"><strong>NIS :</strong> {{ $invoice->buyer_nis }}</div>
                @endif
                @if ($invoice->buyer_ai)
                    <div class="row"><strong>AI :</strong> {{ $invoice->buyer_ai }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="num">Qté</th>
                <th class="num">PU HT</th>
                <th class="num">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line['designation'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $money($line['unit_ht_c']) }}</td>
                    <td class="num">{{ $money($line['total_ht_c']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total HT</td>
            <td class="num">{{ $money($invoice->subtotal_ht_c) }}</td>
        </tr>
        @if ($invoice->discount_ht_c > 0)
            <tr>
                <td>Remise</td>
                <td class="num">- {{ $money($invoice->discount_ht_c) }}</td>
            </tr>
            <tr>
                <td>Net HT</td>
                <td class="num">{{ $money($invoice->total_ht_c) }}</td>
            </tr>
        @endif
        <tr>
            <td>TVA ({{ $rate }} %)</td>
            <td class="num">{{ $money($invoice->tva_c) }}</td>
        </tr>
        <tr class="grand">
            <td>Total TTC</td>
            <td class="num">{{ $money($invoice->total_ttc_c) }}</td>
        </tr>
    </table>

    <div class="words">
        Arrêtée la présente facture à la somme de : <strong>{{ $amountInWords }}</strong>.
    </div>

    <div class="payment">
        <strong>Mode de règlement :</strong> {{ $invoice->payment_method_label }}
        @if ($invoice->payment_date)
            — réglée le {{ $invoice->payment_date->format('d/m/Y') }}
        @endif
    </div>

    @if ((float) $invoice->tva_rate === 0.0)
        <div class="notes">TVA non applicable — opération exonérée.</div>
    @endif

    @if ($invoice->notes)
        <div class="notes">{{ $invoice->notes }}</div>
    @endif

    <div class="footer">
        {{ $seller['business_name'] }} — {{ $seller['legal_form'] }} au capital de {{ $seller['capital'] }} —
        <span class="nowrap">RC {{ $seller['rc'] }}</span> — <span class="nowrap">NIF {{ $seller['nif'] }}</span> — <span class="nowrap">NIS {{ $seller['nis'] }}</span> — <span class="nowrap">AI {{ $seller['ai'] }}</span>
    </div>
</body>
</html>
