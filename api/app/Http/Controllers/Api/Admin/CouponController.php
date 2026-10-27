<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CouponController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CouponResource::collection(Coupon::query()->latest()->get());
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());
        $coupon->refresh(); // pick up DB defaults (uses)

        AuditLog::record($request->user(), 'coupon.created', $coupon, [
            'code' => $coupon->code,
            'percent_off' => $coupon->percent_off,
        ]);

        return CouponResource::make($coupon)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        $before = $coupon->only(['code', 'percent_off', 'max_uses', 'expires_at']);
        $coupon->update($request->validated());

        // A discount is money: a change to one must be attributable.
        AuditLog::record($request->user(), 'coupon.updated', $coupon, [
            'before' => $before,
            'after' => $coupon->only(array_keys($before)),
        ]);

        return CouponResource::make($coupon);
    }

    public function destroy(Request $request, Coupon $coupon): Response
    {
        AuditLog::record($request->user(), 'coupon.deleted', $coupon, [
            'code' => $coupon->code,
            'uses' => $coupon->uses,
        ]);

        $coupon->delete();

        return response()->noContent();
    }
}
