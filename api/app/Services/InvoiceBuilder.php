<?php

namespace App\Services;

use App\Models\Order;

/**
 * Computes the financial body of an invoice from a paid order.
 *
 * All course prices include IVA (the final price a consumer must be shown,
 * and what the order stores), so the net / IVA breakdown is derived
 * backwards from the gross amounts at the chosen rate. Everything is computed
 * in integer céntimos; totals are built so that net + IVA == gross exactly,
 * with any rounding remainder carried by the IVA line.
 */
class InvoiceBuilder
{
    /**
     * @return array{
     *     lines: list<array{designation: string, quantity: int, unit_gross_c: int, unit_net_c: int, total_net_c: int}>,
     *     iva_rate: float,
     *     subtotal_net_c: int,
     *     discount_net_c: int,
     *     total_net_c: int,
     *     iva_c: int,
     *     total_gross_c: int,
     * }
     */
    public function build(Order $order, float $ivaRate): array
    {
        $order->loadMissing(['course', 'orderModules.module']);

        $amountGrossC = $order->amount * 100;
        $factor = 1 + $ivaRate / 100;

        $lines = [];
        $subtotalGrossC = 0;

        if ($order->scope === 'modules' && $order->orderModules->isNotEmpty()) {
            foreach ($order->orderModules as $orderModule) {
                $unitGrossC = (int) $orderModule->unit_price * 100;
                $subtotalGrossC += $unitGrossC;

                $lines[] = $this->line(
                    sprintf(
                        'Curso online — %s — Módulo %d: %s (acceso de por vida)',
                        $order->course->localizedTitle('es'),
                        $orderModule->module?->order ?? 0,
                        $orderModule->module?->localizedTitle('es') ?? '',
                    ),
                    $unitGrossC,
                    $factor,
                );
            }
        } else {
            // Full access: the order stores only the final agreed amount —
            // discount_percent holds the volume tier alone, while coupon and
            // campaign reductions are folded into the amount without being
            // stored. Any pre-discount price "reconstructed" from it would be
            // a fabricated figure on a fiscal document, so the invoice simply
            // bills the price actually agreed, with no discount line.
            $lines[] = $this->line(
                sprintf(
                    'Curso online — %s — Acceso completo (acceso de por vida)',
                    $order->course->localizedTitle('es'),
                ),
                $amountGrossC,
                $factor,
            );
            $subtotalGrossC = $amountGrossC;
        }

        $discountGrossC = max(0, $subtotalGrossC - $amountGrossC);

        $subtotalNetC = array_sum(array_column($lines, 'total_net_c'));
        $discountNetC = (int) round($discountGrossC / $factor);
        $totalNetC = $subtotalNetC - $discountNetC;

        // The IVA line absorbs the per-line rounding so the gross is exact.
        $ivaC = $amountGrossC - $totalNetC;

        return [
            'lines' => $lines,
            'iva_rate' => $ivaRate,
            'subtotal_net_c' => $subtotalNetC,
            'discount_net_c' => $discountNetC,
            'total_net_c' => $totalNetC,
            'iva_c' => $ivaC,
            'total_gross_c' => $amountGrossC,
        ];
    }

    /**
     * Recompute the net/IVA breakdown of an ALREADY-ISSUED invoice from its
     * stored lines. Corrections must never rebuild from the live order — the
     * catalogue may have changed (or a module may have been deleted) since
     * issuance, and the printed designations are part of the legal snapshot.
     *
     * @param  list<array{designation: string, quantity: int, unit_gross_c: int}>  $storedLines
     * @return array{
     *     lines: list<array{designation: string, quantity: int, unit_gross_c: int, unit_net_c: int, total_net_c: int}>,
     *     iva_rate: float,
     *     subtotal_net_c: int,
     *     discount_net_c: int,
     *     total_net_c: int,
     *     iva_c: int,
     *     total_gross_c: int,
     * }
     */
    public function retotal(array $storedLines, float $ivaRate, int $totalGrossC): array
    {
        $factor = 1 + $ivaRate / 100;

        $lines = array_map(
            fn (array $line) => $this->line($line['designation'], (int) $line['unit_gross_c'], $factor),
            $storedLines,
        );

        $subtotalGrossC = array_sum(array_column($lines, 'unit_gross_c'));
        $discountGrossC = max(0, $subtotalGrossC - $totalGrossC);

        $subtotalNetC = array_sum(array_column($lines, 'total_net_c'));
        $discountNetC = (int) round($discountGrossC / $factor);
        $totalNetC = $subtotalNetC - $discountNetC;

        return [
            'lines' => $lines,
            'iva_rate' => $ivaRate,
            'subtotal_net_c' => $subtotalNetC,
            'discount_net_c' => $discountNetC,
            'total_net_c' => $totalNetC,
            'iva_c' => $totalGrossC - $totalNetC,
            'total_gross_c' => $totalGrossC,
        ];
    }

    /**
     * @return array{designation: string, quantity: int, unit_gross_c: int, unit_net_c: int, total_net_c: int}
     */
    private function line(string $designation, int $unitGrossC, float $factor): array
    {
        $unitNetC = (int) round($unitGrossC / $factor);

        return [
            'designation' => $designation,
            'quantity' => 1,
            'unit_gross_c' => $unitGrossC,
            'unit_net_c' => $unitNetC,
            'total_net_c' => $unitNetC,
        ];
    }

    public static function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'stripe' => 'Tarjeta bancaria (pago online)',
            'manual_transfer' => 'Transferencia bancaria',
            'manual_bizum' => 'Bizum',
            'cash' => 'Efectivo',
            default => $method,
        };
    }
}
