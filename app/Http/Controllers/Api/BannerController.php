<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Traits\ApiResponse;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BannerController extends Controller
{
    use ApiResponse;

    /**
     * Get active banners (up to 7) for storefront carousel.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Banner::with(['desktopMedia', 'mobileMedia']);

        if (! $request->boolean('all')) {
            $query->where('is_active', true);
        }

        $banners = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->take(7)
            ->get();

        return $this->success(
            BannerResource::collection($banners),
            'Banners retrieved successfully.',
            [
                'count' => $banners->count(),
                'maxAllowed' => 7,
            ]
        );
    }

    /**
     * Admin create a new banner (strictly capped at 7 active banners via database transaction).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'desktop_media_id' => 'nullable|exists:media,id',
            'mobile_media_id' => 'nullable|exists:media,id',
            'desktop_image' => 'nullable|string|max:500',
            'mobile_image' => 'nullable|string|max:500',
            'link_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if (empty($validated['desktop_media_id']) && empty($validated['desktop_image'])) {
            return $this->error('A valid desktop media asset or image must be provided.', 422, [
                'desktop_media_id' => ['Please upload or select a desktop media asset.'],
            ]);
        }

        $isActive = $request->boolean('is_active', true);

        $banner = DB::transaction(function () use ($validated, $isActive) {
            if ($isActive) {
                // Lock rows to prevent concurrent race condition exceeding 7 active banners
                $activeCount = Banner::where('is_active', true)->lockForUpdate()->count();
                if ($activeCount >= 7) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Maximum limit of 7 active carousel banners reached. Please deactivate or remove an existing banner before creating a new active banner.',
                    ], 422));
                }
            }

            return Banner::create([
                'title' => $validated['title'],
                'subtitle' => $validated['subtitle'] ?? null,
                'desktop_media_id' => $validated['desktop_media_id'] ?? null,
                'mobile_media_id' => $validated['mobile_media_id'] ?? null,
                'desktop_image' => $validated['desktop_image'] ?? null,
                'mobile_image' => $validated['mobile_image'] ?? null,
                'link_url' => $validated['link_url'] ?? '/shop',
                'badge_text' => $validated['badge_text'] ?? null,
                'is_active' => $isActive,
                'sort_order' => $validated['sort_order'] ?? (Banner::count() + 1),
            ]);
        });

        return $this->success(
            new BannerResource($banner),
            'Banner created successfully.',
            [],
            201
        );
    }

    /**
     * Admin update an existing banner.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'desktop_media_id' => 'nullable|exists:media,id',
            'mobile_media_id' => 'nullable|exists:media,id',
            'desktop_image' => 'nullable|string|max:500',
            'mobile_image' => 'nullable|string|max:500',
            'link_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($request, $banner, $validated) {
            if ($request->has('is_active') && $request->boolean('is_active') && ! $banner->is_active) {
                $activeCount = Banner::where('is_active', true)
                    ->where('id', '!=', $banner->id)
                    ->lockForUpdate()
                    ->count();

                if ($activeCount >= 7) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Cannot activate banner: maximum of 7 active carousel banners allowed.',
                    ], 422));
                }
            }

            $banner->update($validated);
        });

        return $this->success(
            new BannerResource($banner->fresh(['desktopMedia', 'mobileMedia'])),
            'Banner updated successfully.'
        );
    }

    /**
     * Admin delete a banner.
     */
    public function destroy(string $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return $this->success(null, 'Banner deleted successfully.');
    }

    /**
     * Admin reorder banners.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'banner_ids' => 'required|array',
            'banner_ids.*' => 'integer|exists:banners,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('banner_ids') as $index => $id) {
                Banner::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });

        return $this->success(null, 'Banners reordered successfully.');
    }
}
