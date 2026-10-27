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
            'buyer_nif' => $this->buyer_nif,
            'lines' => $this->lines,
            'iva_rate' => (float) $this->iva_rate,
            'subtotal_net_c' => $this->subtotal_net_c,
            'discount_net_c' => $this->discount_net_c,
            'total_net_c' => $this->total_net_c,
            'iva_c' => $this->iva_c,
            'total_gross_c' => $this->total_gross_c,
            'currency' => $this->currency,
            'payment_method_label' => $this->payment_method_label,
            'payment_date' => $this->payment_date?->toDateString(),
            'notes' => $this->notes,
            'pdf_url' => url("/api/admin/invoices/{$this->id}/pdf"),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
