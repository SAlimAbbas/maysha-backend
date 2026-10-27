<?php

namespace Tests\Feature;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Exceptions\Shiprocket\ShiprocketAuthException;
use App\Exceptions\Shiprocket\ShiprocketValidationException;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShiprocketClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external');
        Config::set('services.shiprocket.email', 'test@mayshaindia.com');
        Config::set('services.shiprocket.password', 'secret-sr-pass');
    }

    public function test_get_token_logs_in_and_caches_token(): void
    {
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response([
                'token' => 'sr_bearer_token_xyz_123',
                'id' => 101,
            ], 200),
        ]);

        $client = new ShiprocketClient();
        $token = $client->getToken();

        $this->assertEquals('sr_bearer_token_xyz_123', $token);
        $this->assertEquals('sr_bearer_token_xyz_123', Cache::get('shiprocket:token'));

        // Subsequent call should hit cache without new HTTP call
        $secondToken = $client->getToken();
        $this->assertEquals('sr_bearer_token_xyz_123', $secondToken);

        Http::assertSentCount(1);
    }

    public function test_get_token_throws_when_credentials_missing(): void
    {
        Config::set('services.shiprocket.email', null);
        Config::set('services.shiprocket.password', null);

        $client = new ShiprocketClient();

        $this->expectException(ShiprocketAuthException::class);
        $this->expectExceptionMessage('Shiprocket API credentials');

        $client->getToken();
    }

    public function test_get_token_throws_on_invalid_credentials(): void
    {
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response([
                'message' => 'Invalid email or password',
                'status_code' => 401,
            ], 401),
        ]);

        $client = new ShiprocketClient();

        $this->expectException(ShiprocketAuthException::class);
        $client->getToken();
    }

    public function test_request_re_authenticates_on_401_and_retries(): void
    {
        Cache::put('shiprocket:token', 'stale_token_abc', 3600);

        $callCount = 0;
        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/auth/login' => Http::response([
                'token' => 'fresh_token_def',
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance' => function ($request) use (&$callCount) {
                $callCount++;
                if (in_array('Bearer stale_token_abc', $request->header('Authorization') ?? [])) {
                    return Http::response(['message' => 'Unauthenticated.'], 401);
                }
                return Http::response(['data' => ['balance_amount' => '1500.00']], 200);
            },
        ]);

        $client = new ShiprocketClient();
        $response = $client->getWalletBalance();

        $this->assertEquals('1500.00', $response['data']['balance_amount']);
        $this->assertEquals('fresh_token_def', Cache::get('shiprocket:token'));
        $this->assertEquals(2, $callCount);
    }

    public function test_request_throws_validation_exception_on_422(): void
    {
        Cache::put('shiprocket:token', 'valid_token', 3600);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'message' => 'Validation error',
                'errors' => ['billing_pincode' => ['Invalid pincode']],
            ], 422),
        ]);

        $client = new ShiprocketClient();

        $this->expectException(ShiprocketValidationException::class);
        $client->createOrder(['invalid' => 'payload']);
    }

    public function test_request_throws_api_exception_on_rate_limit_429(): void
    {
        Cache::put('shiprocket:token', 'valid_token', 3600);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/courier/serviceability*' => Http::response([
                'message' => 'Too Many Requests',
            ], 429),
        ]);

        $client = new ShiprocketClient();

        $this->expectException(ShiprocketApiException::class);
        $this->expectExceptionMessage('rate limit');

        $client->checkServiceability('560001', '110001', 0.5, false);
    }

    public function test_check_serviceability_success(): void
    {
        Cache::put('shiprocket:token', 'valid_token', 3600);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/courier/serviceability*' => Http::response([
                'status' => 200,
                'data' => [
                    'available_courier_companies' => [
                        [
                            'courier_company_id' => 1,
                            'courier_name' => 'Blue Dart Air',
                            'rate' => 95.0,
                            'etd' => '2-3 days',
                            'cod' => 1,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new ShiprocketClient();
        $result = $client->checkServiceability('560001', '110001', 0.5, false);

        $this->assertEquals(200, $result['status']);
        $this->assertNotEmpty($result['data']['available_courier_companies']);
    }

    public function test_create_order_assign_courier_and_label(): void
    {
        Cache::put('shiprocket:token', 'valid_token', 3600);

        Http::fake([
            'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc' => Http::response([
                'order_id' => 9901,
                'shipment_id' => 8801,
                'status' => 'NEW',
                'status_code' => 1,
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/assign/awb' => Http::response([
                'response' => [
                    'data' => [
                        'awb_code' => 'AWB123456789',
                        'courier_company_id' => 10,
                        'courier_name' => 'Delhivery Surface',
                    ],
                ],
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/generate/label' => Http::response([
                'label_url' => 'https://shiprocket.co/label/AWB123456789.pdf',
            ], 200),
            'https://apiv2.shiprocket.in/v1/external/courier/track/awb/AWB123456789' => Http::response([
                'tracking_data' => [
                    'track_status' => 1,
                    'shipment_status' => 6,
                    'shipment_track' => [
                        [
                            'id' => 1,
                            'current_status' => 'IN_TRANSIT',
                            'awb_code' => 'AWB123456789',
                        ],
                    ],
                    'shipment_track_activities' => [
                        [
                            'date' => '2026-10-27 12:00:00',
                            'status' => 'Picked Up',
                            'activity' => 'Package has been picked up',
                            'location' => 'Bengaluru Hub',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new ShiprocketClient();

        $orderRes = $client->createOrder(['order_id' => 'MAY-20261027-001']);
        $this->assertEquals(9901, $orderRes['order_id']);

        $assignRes = $client->assignCourier(8801, 10);
        $this->assertEquals('AWB123456789', $assignRes['response']['data']['awb_code']);

        $labelRes = $client->generateLabel([8801]);
        $this->assertEquals('https://shiprocket.co/label/AWB123456789.pdf', $labelRes['label_url']);

        $trackRes = $client->trackByAwb('AWB123456789');
        $this->assertEquals(1, $trackRes['tracking_data']['track_status']);
    }
}
