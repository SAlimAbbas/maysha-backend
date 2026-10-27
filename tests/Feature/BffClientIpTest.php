<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BffClientIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.bff.secret', 'test-bff-secret-12345');

        Route::get('/api/test-ip-check', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'ip' => $request->ip(),
            ]);
        });
    }

    public function test_bff_edge_ip_is_trusted_when_valid_secret_provided(): void
    {
        $response = $this->withHeaders([
            'X-Maysha-BFF-Secret' => 'test-bff-secret-12345',
            'X-Maysha-Client-IP' => '203.0.113.195',
        ])->getJson('/api/test-ip-check');

        $response->assertStatus(200)
            ->assertJson([
                'ip' => '203.0.113.195',
            ]);
    }

    public function test_bff_edge_ip_is_ignored_when_secret_is_invalid(): void
    {
        $response = $this->withHeaders([
            'X-Maysha-BFF-Secret' => 'invalid-secret',
            'X-Maysha-Client-IP' => '203.0.113.195',
        ])->getJson('/api/test-ip-check');

        $response->assertStatus(200);
        $this->assertNotEquals('203.0.113.195', $response->json('ip'));
    }

    public function test_bff_edge_ip_is_ignored_when_secret_is_missing(): void
    {
        $response = $this->withHeaders([
            'X-Maysha-Client-IP' => '203.0.113.195',
        ])->getJson('/api/test-ip-check');

        $response->assertStatus(200);
        $this->assertNotEquals('203.0.113.195', $response->json('ip'));
    }
}
