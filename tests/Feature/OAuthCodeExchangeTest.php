<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthCodeExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_code_succeeds_once_and_invalidates_subsequent_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'oauthuser@example.com',
            'role' => 'customer',
        ]);

        $code = bin2hex(random_bytes(32));
        cache()->put("oauth_code_{$code}", [
            'user_id' => $user->id,
            'created_at' => now()->timestamp,
        ], now()->addSeconds(60));

        // 1. First exchange succeeds
        $res1 = $this->postJson('/api/v1/auth/exchange-code', [
            'code' => $code,
        ]);

        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);

        $this->assertEquals($user->id, $res1->json('data.user.id'));

        // 2. Second exchange with same code must fail with 401 (single-use)
        $res2 = $this->postJson('/api/v1/auth/exchange-code', [
            'code' => $code,
        ]);

        $res2->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_exchange_code_rejects_invalid_or_expired_code(): void
    {
        $res = $this->postJson('/api/v1/auth/exchange-code', [
            'code' => str_repeat('a', 64),
        ]);

        $res->assertStatus(401);
    }
}
