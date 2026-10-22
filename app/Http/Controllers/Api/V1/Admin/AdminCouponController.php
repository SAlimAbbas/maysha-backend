<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCouponController extends Controller
{
    use ApiResponse;

    /**
     * List all coupons with redemption statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::withCount('redemptions')
            ->orderBy('id', 'desc')
            ->get();

        $transformed = $coupons->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'value' => (int) $c->value,
                'minSubtotalRupees' => (int) round($c->min_subtotal_paise / 100),
                'maxDiscountRupees' => $c->max_discount_paise ? (int) round($c->max_discount_paise / 100) : null,
                'usageLimit' => $c->usage_limit,
                'perUserLimit' => $c->per_user_limit,
                'redemptionsCount' => $c->redemptions_count,
                'isActive' => (bool) $c->is_active,
                'firstOrderOnly' => (bool) $c->first_order_only,
                'startsAt' => $c->starts_at?->toIso8601String(),
                'endsAt' => $c->ends_at?->toIso8601String(),
                'createdAt' => $c->created_at?->toIso8601String(),
            ];
        });

        return $this->success($transformed, 'Coupons retrieved successfully.');
    }

    /**
     * Store new coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:percent,fixed,flat',
            'value' => 'required|numeric|min:1',
            'min_subtotal' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'first_order_only' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $minSubtotalPaise = isset($validated['min_subtotal']) ? (int) ($validated['min_subtotal'] * 100) : 0;
        $maxDiscountPaise = isset($validated['max_discount']) && $validated['max_discount'] > 0
            ? (int) ($validated['max_discount'] * 100)
            : null;

        // If fixed type, store value in paise; if percent, store plain integer percentage
        $value = $validated['type'] === 'fixed'
            ? (int) ($validated['value'] * 100)
            : (int) $validated['value'];

        $coupon = Coupon::create([
            'code' => Str::upper(trim($validated['code'])),
            'type' => $validated['type'],
            'value' => $value,
            'min_subtotal_paise' => $minSubtotalPaise,
            'max_discount_paise' => $maxDiscountPaise,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'per_user_limit' => $validated['per_user_limit'] ?? 1,
            'first_order_only' => $validated['first_order_only'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        AuditLog::record('coupon.created', $coupon, $request->user(), ['code' => $coupon->code]);

        return $this->success($coupon, "Coupon '{$coupon->code}' created successfully.", [], 201);
    }

    /**
     * Update existing coupon.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:coupons,code,' . $coupon->id,
            'type' => 'sometimes|required|in:percent,fixed,flat',
            'value' => 'sometimes|required|numeric|min:1',
            'min_subtotal' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'first_order_only' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
        ]);

        $updateData = [];

        if (array_key_exists('code', $validated)) {
            $updateData['code'] = Str::upper(trim($validated['code']));
        }
        if (array_key_exists('type', $validated)) {
            $updateData['type'] = $validated['type'];
        }
        if (array_key_exists('value', $validated)) {
            $type = $validated['type'] ?? $coupon->type;
            $updateData['value'] = $type === 'fixed'
                ? (int) ($validated['value'] * 100)
                : (int) $validated['value'];
        }
        if (array_key_exists('min_subtotal', $validated)) {
            $updateData['min_subtotal_paise'] = $validated['min_subtotal'] !== null
                ? (int) ($validated['min_subtotal'] * 100)
                : 0;
        }
        if (array_key_exists('max_discount', $validated)) {
            $updateData['max_discount_paise'] = ($validated['max_discount'] !== null && $validated['max_discount'] > 0)
                ? (int) ($validated['max_discount'] * 100)
                : null;
        }
        if (array_key_exists('usage_limit', $validated)) {
            $updateData['usage_limit'] = $validated['usage_limit'];
        }
        if (array_key_exists('per_user_limit', $validated)) {
            $updateData['per_user_limit'] = $validated['per_user_limit'];
        }
        if (array_key_exists('first_order_only', $validated)) {
            $updateData['first_order_only'] = (bool) $validated['first_order_only'];
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = (bool) $validated['is_active'];
        }
        if (array_key_exists('starts_at', $validated)) {
            $updateData['starts_at'] = $validated['starts_at'];
        }
        if (array_key_exists('ends_at', $validated)) {
            $updateData['ends_at'] = $validated['ends_at'];
        }

        $coupon->update($updateData);

        AuditLog::record('coupon.updated', $coupon, $request->user(), ['code' => $coupon->code]);

        return $this->success($coupon, "Coupon '{$coupon->code}' updated successfully.");
    }

    /**
     * Delete coupon.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();

        AuditLog::record('coupon.deleted', null, $request->user(), ['code' => $code]);

        return $this->success(null, "Coupon '{$code}' deleted successfully.");
    }
}
