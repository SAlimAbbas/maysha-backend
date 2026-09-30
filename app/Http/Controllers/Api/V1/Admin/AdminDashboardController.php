<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get aggregate administrative dashboard statistics.
     */
    public function stats(): JsonResponse
    {
        $revenuePaise = (int) Order::whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total_paise');
        $ordersCount = Order::count();
        $customersCount = User::where('role', 'customer')->count();
        $pendingOrdersCount = Order::where('status', 'pending_payment')->count();
        $lowStockVariantsCount = ProductVariant::where('stock', '<=', 5)->where('is_active', true)->count();

        $recentOrders = Order::with('items')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentAudits = AuditLog::with('actor')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        return $this->success([
            'totalRevenueRupees' => (int) round($revenuePaise / 100),
            'totalOrders' => $ordersCount,
            'totalCustomers' => $customersCount,
            'pendingOrders' => $pendingOrdersCount,
            'lowStockCount' => $lowStockVariantsCount,
            'recentOrders' => OrderResource::collection($recentOrders),
            'recentAudits' => $recentAudits->map(fn ($a) => [
                'id' => (string) $a->id,
                'action' => $a->action,
                'actor' => $a->actor?->name ?? 'System',
                'ip' => $a->ip,
                'timestamp' => $a->created_at?->toIso8601String(),
            ]),
        ], 'Dashboard statistics retrieved successfully.');
    }

    /**
     * List customers (read-only).
     */
    public function customers(): JsonResponse
    {
        $customers = User::where('role', 'customer')
            ->withCount('orders')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->success(
            $customers->items(),
            'Customers retrieved successfully.',
            [
                'total' => $customers->total(),
                'currentPage' => $customers->currentPage(),
                'lastPage' => $customers->lastPage(),
            ]
        );
    }
}
