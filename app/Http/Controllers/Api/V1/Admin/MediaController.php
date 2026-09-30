<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RequestUploadUrlRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Media;
use App\Services\CloudflareMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    use ApiResponse;

    protected CloudflareMediaService $mediaService;

    public function __construct(CloudflareMediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Request a one-time Direct Creator Upload URL.
     */
    public function requestUploadUrl(RequestUploadUrlRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->mediaService->createDirectUploadUrl(
            $validated['type'],
            $validated['filename'],
            $validated['mime'],
            $validated['size'],
            $request->user()
        );

        return $this->success($result, 'Direct upload URL generated successfully.', [], 201);
    }

    /**
     * Confirm media upload and mark as ready.
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $alt = $request->input('alt');

        $confirmed = $this->mediaService->confirmUpload($media, $alt);

        return $this->success([
            'id' => (string) $confirmed->id,
            'provider' => $confirmed->provider,
            'providerId' => $confirmed->provider_id,
            'status' => $confirmed->status,
            'alt' => $confirmed->alt,
            'deliveryUrl' => $confirmed->getDeliveryUrl('gallery'),
        ], 'Media confirmed and ready for use.');
    }

    /**
     * List all ready media assets.
     */
    public function index(Request $request): JsonResponse
    {
        $media = Media::where('status', 'ready')
            ->orderBy('id', 'desc')
            ->paginate(24);

        return $this->success(
            $media->items(),
            'Media assets retrieved successfully.',
            [
                'total' => $media->total(),
                'currentPage' => $media->currentPage(),
                'lastPage' => $media->lastPage(),
            ]
        );
    }
}
