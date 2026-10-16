<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    // -------------------------------------------------------------
    // Addresses
    // -------------------------------------------------------------

    public function getAddresses(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderBy('is_default', 'desc')->get();

        return $this->success($addresses, 'Customer addresses retrieved.');
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'is_default' => 'nullable|boolean',
        ]);

        $user = $request->user();

        if (! empty($validated['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($validated);

        return $this->success($address, 'Address saved successfully.', [], 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
            'line1' => 'sometimes|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => ['sometimes', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return $this->success($address, 'Address updated successfully.');
    }

    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return $this->success(null, 'Address deleted successfully.');
    }

    // -------------------------------------------------------------
    // Wishlist
    // -------------------------------------------------------------

    public function getWishlist(Request $request): JsonResponse
    {
        $user = $request->user();
        $products = Product::active()
            ->whereIn('id', $user->wishlist()->pluck('product_id'))
            ->with(['category', 'concern', 'variants' => fn ($q) => $q->where('is_active', true), 'media'])
            ->get();

        return $this->success(
            ProductResource::collection($products),
            'Wishlist items retrieved.'
        );
    }

    public function addToWishlist(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();
        Product::active()->findOrFail($productId);

        Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        return $this->success(null, 'Product added to wishlist.');
    }

    public function syncWishlist(Request $request): JsonResponse
    {
        $user = $request->user();
        $productIds = $request->input('product_ids', []);

        foreach ($productIds as $pid) {
            if ($prod = Product::find($pid)) {
                Wishlist::firstOrCreate([
                    'user_id' => $user->id,
                    'product_id' => $prod->id,
                ]);
            }
        }

        return $this->getWishlist($request);
    }

    public function removeFromWishlist(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();
        $user->wishlist()->where('product_id', $productId)->delete();

        return $this->success(null, 'Product removed from wishlist.');
    }

    // -------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------

    public function getOrders(Request $request): JsonResponse
    {
        $paginator = $request->user()->orders()
            ->with(['items.variant', 'events'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        return $this->success(
            OrderResource::collection($paginator->items()),
            'Customer order history retrieved.',
            [
                'pagination' => [
                    'currentPage' => $paginator->currentPage(),
                    'lastPage' => $paginator->lastPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'hasMore' => $paginator->hasMorePages(),
                ],
            ]
        );
    }

    public function getOrderDetail(Request $request, string $reference): JsonResponse
    {
        $order = $request->user()->orders()
            ->where('reference', $reference)
            ->with(['items.variant', 'events', 'payments'])
            ->firstOrFail();

        return $this->success(new OrderResource($order), 'Order details retrieved.');
    }
}
