<?php

namespace App\Services;

use App\Models\Order;

/**
 * Computes the financial body of an invoice from a paid order.
 *
 * All course prices are TTC (that is what students are quoted and what the
 * order stores), so the HT/TVA breakdown is derived backwards from the TTC
 * amounts at the chosen rate. Everything is computed in integer centimes;
 * totals are built so that HT + TVA == TTC exactly, with any rounding
 * remainder carried by the TVA line.
 */
class InvoiceBuilder
{
    /**
     * @return array{
     *     lines: list<array{designation: string, quantity: int, unit_ttc_c: int, unit_ht_c: int, total_ht_c: int}>,
     *     tva_rate: float,
     *     subtotal_ht_c: int,
     *     discount_ht_c: int,
     *     total_ht_c: int,
     *     tva_c: int,
     *     total_ttc_c: int,
     * }
     */
    public function build(Order $order, float $tvaRate): array
    {
        $order->loadMissing(['course', 'orderModules.module']);

        $amountTtcC = $order->amount * 100;
        $factor = 1 + $tvaRate / 100;

        $lines = [];
        $subtotalTtcC = 0;

        if ($order->scope === 'modules' && $order->orderModules->isNotEmpty()) {
            foreach ($order->orderModules as $orderModule) {
                $unitTtcC = (int) $orderModule->unit_price * 100;
                $subtotalTtcC += $unitTtcC;

                $lines[] = $this->line(
                    sprintf(
                        'Formation en ligne — %s — Module %d : %s (accès à vie)',
                        $order->course->localizedTitle('fr'),
                        $orderModule->module?->order ?? 0,
                        $orderModule->module?->localizedTitle('fr') ?? '',
                    ),
                    $unitTtcC,
                    $factor,
                );
            }
        } else {
            // Full access: the order stores only the final agreed amount —
            // discount_percent holds the volume tier alone, while coupon and
            // campaign reductions are folded into the amount without being
            // stored. Any pre-discount price "reconstructed" from it would be
            // a fabricated figure on a fiscal document, so the invoice simply
            // bills the price actually agreed, with no remise line.
            $lines[] = $this->line(
                sprintf(
                    'Formation en ligne — %s — Accès complet (accès à vie)',
                    $order->course->localizedTitle('fr'),
                ),
                $amountTtcC,
                $factor,
            );
            $subtotalTtcC = $amountTtcC;
        }

        $discountTtcC = max(0, $subtotalTtcC - $amountTtcC);

        $subtotalHtC = array_sum(array_column($lines, 'total_ht_c'));
        $discountHtC = (int) round($discountTtcC / $factor);
        $totalHtC = $subtotalHtC - $discountHtC;

        // The TVA line absorbs the per-line rounding so TTC is exact.
        $tvaC = $amountTtcC - $totalHtC;

        return [
            'lines' => $lines,
            'tva_rate' => $tvaRate,
            'subtotal_ht_c' => $subtotalHtC,
            'discount_ht_c' => $discountHtC,
            'total_ht_c' => $totalHtC,
            'tva_c' => $tvaC,
            'total_ttc_c' => $amountTtcC,
        ];
    }

    /**
     * Recompute the HT/TVA breakdown of an ALREADY-ISSUED invoice from its
     * stored lines. Corrections must never rebuild from the live order — the
     * catalogue may have changed (or a module may have been deleted) since
     * issuance, and the printed designations are part of the legal snapshot.
     *
     * @param  list<array{designation: string, quantity: int, unit_ttc_c: int}>  $storedLines
     * @return array{
     *     lines: list<array{designation: string, quantity: int, unit_ttc_c: int, unit_ht_c: int, total_ht_c: int}>,
     *     tva_rate: float,
     *     subtotal_ht_c: int,
     *     discount_ht_c: int,
     *     total_ht_c: int,
     *     tva_c: int,
     *     total_ttc_c: int,
     * }
     */
    public function retotal(array $storedLines, float $tvaRate, int $totalTtcC): array
    {
        $factor = 1 + $tvaRate / 100;

        $lines = array_map(
            fn (array $line) => $this->line($line['designation'], (int) $line['unit_ttc_c'], $factor),
            $storedLines,
        );

        $subtotalTtcC = array_sum(array_column($lines, 'unit_ttc_c'));
        $discountTtcC = max(0, $subtotalTtcC - $totalTtcC);

        $subtotalHtC = array_sum(array_column($lines, 'total_ht_c'));
        $discountHtC = (int) round($discountTtcC / $factor);
        $totalHtC = $subtotalHtC - $discountHtC;

        return [
            'lines' => $lines,
            'tva_rate' => $tvaRate,
            'subtotal_ht_c' => $subtotalHtC,
            'discount_ht_c' => $discountHtC,
            'total_ht_c' => $totalHtC,
            'tva_c' => $totalTtcC - $totalHtC,
            'total_ttc_c' => $totalTtcC,
        ];
    }

    /**
     * @return array{designation: string, quantity: int, unit_ttc_c: int, unit_ht_c: int, total_ht_c: int}
     */
    private function line(string $designation, int $unitTtcC, float $factor): array
    {
        $unitHtC = (int) round($unitTtcC / $factor);

        return [
            'designation' => $designation,
            'quantity' => 1,
            'unit_ttc_c' => $unitTtcC,
            'unit_ht_c' => $unitHtC,
            'total_ht_c' => $unitHtC,
        ];
    }

    public static function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'chargily' => 'Paiement en ligne — carte CIB / Edahabia',
            'stripe' => 'Carte bancaire internationale',
            'manual_ccp' => 'Virement CCP',
            'manual_baridimob' => 'Virement BaridiMob',
            'cash' => 'Espèces',
            default => $method,
        };
    }
}
