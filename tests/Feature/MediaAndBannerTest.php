<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaAndBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_request_media_upload_url(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/admin/media/upload-url', [
            'type' => 'image',
            'filename' => 'product.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024000,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_request_direct_upload_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/media/upload-url', [
            'type' => 'image',
            'filename' => 'serum-banner.jpg',
            'mime' => 'image/jpeg',
            'size' => 2048000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['mediaId', 'uploadUrl', 'providerId'],
            ]);

        $mediaId = $response->json('data.mediaId');
        $this->assertDatabaseHas('media', [
            'id' => $mediaId,
            'status' => 'pending',
        ]);
    }

    public function test_request_upload_url_rejects_invalid_mime_and_oversized_files(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Invalid MIME type
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/media/upload-url', [
            'type' => 'image',
            'filename' => 'malicious.exe',
            'mime' => 'application/x-msdownload',
            'size' => 1024,
        ]);

        $response->assertStatus(422);

        // Oversized image (> 10MB)
        $oversizedResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/media/upload-url', [
            'type' => 'image',
            'filename' => 'giant.jpg',
            'mime' => 'image/jpeg',
            'size' => 15000000,
        ]);

        $oversizedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['size']);
    }

    public function test_admin_can_confirm_media(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $media = Media::create([
            'provider' => 'cloudflare_images',
            'provider_id' => 'mock-asset-id',
            'type' => 'image',
            'status' => 'pending',
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/media/{$media->id}/confirm", [
            'alt' => 'Maysha Hydrating Essence Bottle',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ready',
                    'alt' => 'Maysha Hydrating Essence Bottle',
                ],
            ]);

        $this->assertEquals('ready', $media->fresh()->status);
        $this->assertEquals('Maysha Hydrating Essence Bottle', $media->fresh()->alt);
    }

    public function test_storefront_can_fetch_active_banners(): void
    {
        Banner::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/banners');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'desktopImage', 'linkUrl', 'isActive', 'sortOrder'],
                ],
                'meta' => ['count', 'maxAllowed'],
            ]);
    }

    public function test_enforces_maximum_of_7_active_banners(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create 7 active banners
        for ($i = 1; $i <= 7; $i++) {
            Banner::create([
                'title' => "Banner {$i}",
                'desktop_image' => "/banners/banner-{$i}.jpg",
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        // Attempting to create an 8th active banner must be rejected with 422
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/banners', [
            'title' => 'Banner 8',
            'desktop_image' => '/banners/banner-8.jpg',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
