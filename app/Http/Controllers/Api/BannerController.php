<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * Get active banners (up to 7) for storefront carousel, or all for admin.
     */
    public function index(Request $request)
    {
        $query = Banner::query();

        if (!$request->boolean('all')) {
            $query->where('is_active', true);
        }

        $banners = $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->take(7)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $banners->count(),
            'max_allowed' => 7,
            'data' => $banners
        ]);
    }

    /**
     * Admin create / upload a new banner (enforces maximum of 7 active banners).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'desktop_image' => 'required', // can be uploaded file or string URL
            'mobile_image' => 'nullable',
            'link_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $isActive = $request->boolean('is_active', true);

        if ($isActive) {
            $activeCount = Banner::where('is_active', true)->count();
            if ($activeCount >= 7) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maximum limit of 7 active carousel banners reached. Please deactivate or remove an existing banner before adding a new active banner.'
                ], 422);
            }
        }

        $desktopImageUrl = '';
        if ($request->hasFile('desktop_image')) {
            $path = $request->file('desktop_image')->store('banners', 'public');
            $desktopImageUrl = Storage::url($path);
        } else {
            $desktopImageUrl = (string)$request->input('desktop_image');
        }

        $mobileImageUrl = null;
        if ($request->hasFile('mobile_image')) {
            $path = $request->file('mobile_image')->store('banners', 'public');
            $mobileImageUrl = Storage::url($path);
        } elseif ($request->filled('mobile_image')) {
            $mobileImageUrl = (string)$request->input('mobile_image');
        }

        $banner = Banner::create([
            'title' => $request->input('title'),
            'subtitle' => $request->input('subtitle'),
            'desktop_image' => $desktopImageUrl,
            'mobile_image' => $mobileImageUrl,
            'link_url' => $request->input('link_url', '/shop'),
            'badge_text' => $request->input('badge_text'),
            'is_active' => $isActive,
            'sort_order' => $request->input('sort_order', Banner::count() + 1),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Banner created successfully.',
            'data' => $banner
        ], 201);
    }

    /**
     * Update an existing banner.
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['status' => 'error', 'message' => 'Banner not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'link_url' => 'nullable|string|max:500',
            'badge_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($request->has('is_active') && $request->boolean('is_active') && !$banner->is_active) {
            $activeCount = Banner::where('is_active', true)->where('id', '!=', $banner->id)->count();
            if ($activeCount >= 7) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot activate this banner: maximum 7 active carousel banners allowed.'
                ], 422);
            }
        }

        if ($request->hasFile('desktop_image')) {
            $path = $request->file('desktop_image')->store('banners', 'public');
            $banner->desktop_image = Storage::url($path);
        } elseif ($request->filled('desktop_image')) {
            $banner->desktop_image = $request->input('desktop_image');
        }

        if ($request->hasFile('mobile_image')) {
            $path = $request->file('mobile_image')->store('banners', 'public');
            $banner->mobile_image = Storage::url($path);
        } elseif ($request->filled('mobile_image')) {
            $banner->mobile_image = $request->input('mobile_image');
        }

        $banner->fill($request->only([
            'title',
            'subtitle',
            'link_url',
            'badge_text',
            'is_active',
            'sort_order',
        ]));

        $banner->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner updated successfully.',
            'data' => $banner
        ]);
    }

    /**
     * Delete a banner.
     */
    public function destroy($id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json(['status' => 'error', 'message' => 'Banner not found'], 404);
        }

        $banner->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner deleted successfully.'
        ]);
    }

    /**
     * Reorder banners array of IDs: [3, 1, 2].
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'banner_ids' => 'required|array',
            'banner_ids.*' => 'integer|exists:banners,id',
        ]);

        foreach ($request->input('banner_ids') as $index => $id) {
            Banner::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Banners reordered successfully.'
        ]);
    }
}
