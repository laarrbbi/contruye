<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of an order: adds the buyer, provider ids and the private
 * receipt-streaming URL. Expects `user` and `course` to be eager-loaded.
 */
class AdminOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'course' => [
                'id' => $this->course->id,
                'slug' => $this->course->slug,
                'title' => $this->course->localizedTitle(),
            ],
            'coupon_id' => $this->coupon_id,
            'amount' => $this->amount,
            'discount_percent' => (int) $this->discount_percent,
            // How this price was reached. Null on orders placed before the
            // snapshot existed, so the UI must treat it as optional.
            'pricing_snapshot' => $this->pricing_snapshot,
            'currency' => $this->currency,
            'method' => $this->method,
            'scope' => $this->scope,
            'modules' => $this->whenLoaded('orderModules', fn () => $this->orderModules
                ->map(fn ($orderModule) => [
                    'id' => $orderModule->module_id,
                    'title' => $orderModule->module?->localizedTitle(),
                    'unit_price' => $orderModule->unit_price,
                ])->values()),
            'status' => $this->status,
            'chargily_checkout_id' => $this->chargily_checkout_id,
            'stripe_session_id' => $this->stripe_session_id,
            'admin_note' => $this->admin_note,
            'has_receipt' => $this->receipt_path !== null,
            'receipt_uploaded_at' => $this->receipt_uploaded_at?->toIso8601String(),
            'receipt_url' => $this->receipt_path !== null
                ? url("/api/admin/orders/{$this->id}/receipt")
                : null,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice === null ? null : [
                'id' => $this->invoice->id,
                'number' => $this->invoice->number,
                'issued_at' => $this->invoice->issued_at?->toDateString(),
                'pdf_url' => url("/api/admin/invoices/{$this->invoice->id}/pdf"),
            ]),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
