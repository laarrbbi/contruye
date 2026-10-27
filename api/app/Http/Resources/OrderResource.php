<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's own view of an order. `payment_instructions` only appears for
 * manual (bank transfer / Bizum) orders that are still actionable, so the frontend
 * can re-display the transfer details until the order is paid.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course' => [
                'id' => $this->course->id,
                'slug' => $this->course->slug,
                'title' => $this->course->localizedTitle(),
                'cover_image' => $this->course->cover_image,
            ],
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'scope' => $this->scope,
            'discount_percent' => (int) $this->discount_percent,
            'modules' => $this->whenLoaded('orderModules', fn () => $this->orderModules
                ->map(fn ($orderModule) => [
                    'id' => $orderModule->module_id,
                    'title' => $orderModule->module?->localizedTitle(),
                    'unit_price' => $orderModule->unit_price,
                ])->values()),
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'has_receipt' => $this->receipt_path !== null,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice === null ? null : [
                'number' => $this->invoice->number,
                'issued_at' => $this->invoice->issued_at?->toDateString(),
                'url' => url("/api/orders/{$this->id}/invoice"),
            ]),
            'payment_instructions' => $this->when(
                self::needsPaymentInstructions($this->resource),
                fn () => self::paymentInstructions($this->resource),
            ),
        ];
    }

    public static function needsPaymentInstructions(Order $order): bool
    {
        return in_array($order->method, Order::MANUAL_METHODS, true)
            && in_array($order->status, ['pending', 'awaiting_review', 'failed'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paymentInstructions(Order $order): array
    {
        return [
            'method' => $order->method,
            'amount' => $order->amount,
            'currency' => $order->currency,
            // What the buyer writes as the transfer's concept or the Bizum
            // note, so the owner can match the money to this order.
            'reference' => $order->paymentReference(),
            'iban' => config('services.manual_payment.iban'),
            'holder' => config('services.manual_payment.holder'),
            'bizum_phone' => config('services.manual_payment.bizum_phone'),
        ];
    }
}
