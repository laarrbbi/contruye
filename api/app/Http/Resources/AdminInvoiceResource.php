<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of an issued invoice. Expects `order.user` and `order.course`
 * to be eager-loaded. Amounts are exposed in centimes, like they are stored.
 */
class AdminInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'issued_at' => $this->issued_at?->toDateString(),
            'order' => [
                'id' => $this->order->id,
                'user' => [
                    'id' => $this->order->user->id,
                    'name' => $this->order->user->name,
                    'email' => $this->order->user->email,
                ],
                'course_title' => $this->order->course->localizedTitle(),
                'scope' => $this->order->scope,
                'method' => $this->order->method,
            ],
            'buyer_name' => $this->buyer_name,
            'buyer_address' => $this->buyer_address,
            'buyer_rc' => $this->buyer_rc,
            'buyer_nif' => $this->buyer_nif,
            'buyer_nis' => $this->buyer_nis,
            'buyer_ai' => $this->buyer_ai,
            'lines' => $this->lines,
            'tva_rate' => (float) $this->tva_rate,
            'subtotal_ht_c' => $this->subtotal_ht_c,
            'discount_ht_c' => $this->discount_ht_c,
            'total_ht_c' => $this->total_ht_c,
            'tva_c' => $this->tva_c,
            'total_ttc_c' => $this->total_ttc_c,
            'currency' => $this->currency,
            'payment_method_label' => $this->payment_method_label,
            'payment_date' => $this->payment_date?->toDateString(),
            'notes' => $this->notes,
            'pdf_url' => url("/api/admin/invoices/{$this->id}/pdf"),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
