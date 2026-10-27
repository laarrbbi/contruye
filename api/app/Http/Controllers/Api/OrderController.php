<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadReceiptRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\InvoicePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['course', 'orderModules.module', 'invoice'])
            ->latest()
            ->latest('id')
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $user = $request->user();

        // 404, not 403: a 403 confirms the order exists, and ids are sequential.
        abort_unless($user->id === $order->user_id || $user->isAdmin(), 404);

        return OrderResource::make($order->load(['course', 'orderModules.module', 'invoice']));
    }

    /**
     * The student's own copy of the invoice PDF — same bytes as the admin
     * download. 404 until the admin has issued one.
     */
    public function invoice(Request $request, Order $order): Response
    {
        $user = $request->user();

        // 404, not 403: a 403 confirms the order exists, and ids are sequential.
        abort_unless($user->id === $order->user_id || $user->isAdmin(), 404);

        $invoice = $order->invoice;

        abort_if($invoice === null, 404, __('No invoice has been issued for this order.'));

        return InvoicePdf::download($invoice);
    }

    public function uploadReceipt(UploadReceiptRequest $request, Order $order): OrderResource|JsonResponse
    {
        // Ownership is enforced by UploadReceiptRequest::authorize() (403).
        if (! in_array($order->method, ['manual_ccp', 'manual_baridimob'], true)) {
            return response()->json([
                'message' => __('This order does not accept receipt uploads.'),
            ], 422);
        }

        if (! in_array($order->status, ['pending', 'awaiting_review', 'failed'], true)) {
            return response()->json([
                'message' => __('This order can no longer be updated.'),
            ], 422);
        }

        $disk = Storage::disk('local');
        $previous = $order->receipt_path;

        // Store the new receipt first. Deleting the old one before a write
        // that then failed left the order pointing at a file that no longer
        // existed: the buyer's only proof of the transfer, gone (C24).
        $path = $disk->putFile("receipts/{$order->id}", $request->file('receipt'));

        if (! is_string($path) || $path === '') {
            return response()->json([
                'message' => __('The receipt could not be saved. Please try again.'),
            ], 500);
        }

        $order->attachReceipt($path);

        if ($previous !== null && $previous !== $path && $disk->exists($previous)) {
            $disk->delete($previous);
        }

        return OrderResource::make($order->load(['course', 'orderModules.module']));
    }
}
