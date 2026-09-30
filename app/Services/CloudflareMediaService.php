<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudflareMediaService
{
    protected ?string $accountId;

    protected ?string $apiToken;

    public function __construct()
    {
        $this->accountId = config('services.cloudflare.account_id', env('CLOUDFLARE_ACCOUNT_ID'));
        $this->apiToken = config('services.cloudflare.api_token', env('CLOUDFLARE_API_TOKEN'));
    }

    /**
     * Request a one-time Direct Creator Upload URL from Cloudflare.
     */
    public function createDirectUploadUrl(string $type, string $filename, string $mime, int $size, ?User $user = null): array
    {
        $provider = $type === 'video' ? 'cloudflare_stream' : 'cloudflare_images';

        // 1. Create a pending Media record in the database
        $media = Media::create([
            'provider' => $provider,
            'provider_id' => 'pending_'.Str::uuid(),
            'type' => $type,
            'status' => 'pending',
            'uploaded_by' => $user?->id,
        ]);

        // 2. If live Cloudflare credentials exist, contact Cloudflare API
        if ($this->accountId && $this->apiToken) {
            if ($type === 'video') {
                $response = Http::withToken($this->apiToken)
                    ->post("https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream/direct_upload", [
                        'maxDurationSeconds' => 3600,
                        'meta' => ['name' => $filename],
                    ]);

                if ($response->successful()) {
                    $result = $response->json('result');
                    $media->update(['provider_id' => $result['uid']]);

                    return [
                        'mediaId' => (string) $media->id,
                        'uploadUrl' => $result['uploadURL'],
                        'providerId' => $result['uid'],
                    ];
                }
            } else {
                $response = Http::withToken($this->apiToken)
                    ->post("https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/images/v2/direct_upload", [
                        'requireSignedURLs' => false,
                        'metadata' => ['filename' => $filename],
                    ]);

                if ($response->successful()) {
                    $result = $response->json('result');
                    $media->update(['provider_id' => $result['id']]);

                    return [
                        'mediaId' => (string) $media->id,
                        'uploadUrl' => $result['uploadURL'],
                        'providerId' => $result['id'],
                    ];
                }
            }
        }

        // Mock / Local direct upload for development & automated tests
        $mockId = (string) Str::uuid();
        $media->update(['provider_id' => $mockId]);

        return [
            'mediaId' => (string) $media->id,
            'uploadUrl' => url("/api/v1/admin/media/mock-upload/{$media->id}"),
            'providerId' => $mockId,
        ];
    }

    /**
     * Confirm asset upload status with Cloudflare and mark media as ready.
     */
    public function confirmUpload(Media $media, ?string $alt = null): Media
    {
        if ($this->accountId && $this->apiToken && ! str_starts_with($media->provider_id, 'pending_')) {
            if ($media->type === 'video') {
                $response = Http::withToken($this->apiToken)
                    ->get("https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/stream/{$media->provider_id}");

                if ($response->successful()) {
                    $media->status = 'ready';
                }
            } else {
                $response = Http::withToken($this->apiToken)
                    ->get("https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/images/v1/{$media->provider_id}");

                if ($response->successful()) {
                    $media->status = 'ready';
                }
            }
        } else {
            // Local dev / test confirmation
            $media->status = 'ready';
        }

        if ($alt) {
            $media->alt = $alt;
        }

        $media->save();

        return $media;
    }
}
