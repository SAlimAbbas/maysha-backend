<?php

namespace App\Services\Shiprocket;

use App\Exceptions\Shiprocket\ShiprocketApiException;
use App\Exceptions\Shiprocket\ShiprocketAuthException;
use App\Exceptions\Shiprocket\ShiprocketValidationException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketClient
{
    protected string $baseUrl;

    protected ?string $email;

    protected ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external'), '/');
        $this->email = config('services.shiprocket.email');
        $this->password = config('services.shiprocket.password');
    }

    /**
     * Get active Bearer token with 23h cache TTL and atomic lock.
     */
    public function getToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'shiprocket:token';

        if (! $forceRefresh) {
            $cachedToken = Cache::get($cacheKey);
            if ($cachedToken) {
                return $cachedToken;
            }
        }

        if (empty($this->email) || empty($this->password)) {
            throw new ShiprocketAuthException('Shiprocket API credentials (email/password) are not configured.');
        }

        // Use cache lock to prevent thundering herd on concurrent token expiry
        return Cache::lock('shiprocket:token:lock', 10)->block(5, function () use ($cacheKey) {
            $cachedToken = Cache::get($cacheKey);
            if ($cachedToken) {
                return $cachedToken;
            }

            try {
                $response = Http::withOptions(['verify' => true])
                    ->timeout(15)
                    ->retry(2, 500, throw: false)
                    ->post($this->baseUrl.'/auth/login', [
                        'email' => $this->email,
                        'password' => $this->password,
                    ]);
            } catch (\Throwable $e) {
                Log::error('Shiprocket login network exception', ['error' => $e->getMessage()]);
                throw new ShiprocketAuthException('Unable to reach Shiprocket login service: '.$e->getMessage(), 0, null, $e instanceof \Exception ? $e : null);
            }

            if (! $response->successful()) {
                Log::error('Shiprocket login authentication failed', [
                    'status' => $response->status(),
                    'message' => $response->json('message') ?? 'Authentication failed',
                ]);
                throw new ShiprocketAuthException(
                    $response->json('message') ?: 'Shiprocket authentication failed with status '.$response->status(),
                    $response->status(),
                    $response->json()
                );
            }

            $token = $response->json('token');
            if (empty($token)) {
                throw new ShiprocketAuthException('Shiprocket login response did not contain an authentication token.');
            }

            // Cache token for 23 hours (82,800 seconds)
            Cache::put($cacheKey, $token, now()->addSeconds(82800));

            return $token;
        });
    }

    /**
     * Send HTTP request to Shiprocket API with token injection, 401 re-auth, and 5xx retries.
     */
    public function request(string $method, string $uri, array $data = [], bool $isRetryOn401 = false): array
    {
        $token = $this->getToken();
        $uri = '/'.ltrim($uri, '/');
        $url = $this->baseUrl.$uri;

        $client = Http::withToken($token)
            ->withOptions(['verify' => true])
            ->timeout(15)
            ->acceptJson();

        // Retry only on 5xx or connection timeouts (up to 2 times with 500ms backoff)
        $client->retry(2, 500, function (\Exception $exception, $request) {
            return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                ($request instanceof Response && $request->serverError());
        }, throw: false);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $client->get($url, $data),
                'POST' => $client->post($url, $data),
                'PUT' => $client->put($url, $data),
                'PATCH' => $client->patch($url, $data),
                'DELETE' => $client->delete($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };
        } catch (\Throwable $e) {
            Log::error('Shiprocket API network request failed', [
                'endpoint' => $uri,
                'error' => $e->getMessage(),
            ]);
            throw new ShiprocketApiException("Shiprocket connection failure on {$uri}: ".$e->getMessage(), 0, null, $e instanceof \Exception ? $e : null);
        }

        // Handle 401 Unauthenticated: Invalidate token, re-login once, and retry
        if ($response->status() === 401 && ! $isRetryOn401) {
            Log::warning('Shiprocket token expired (401). Refreshing token and retrying request.', ['endpoint' => $uri]);
            Cache::forget('shiprocket:token');
            $this->getToken(true);

            return $this->request($method, $uri, $data, true);
        }

        // Rate limiting handling (429)
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After') ?: '60';
            Log::warning('Shiprocket rate limit encountered', ['retry_after' => $retryAfter, 'endpoint' => $uri]);
            throw new ShiprocketApiException("Shiprocket rate limit reached. Retry after {$retryAfter} seconds.", 429);
        }

        // 401 after retry
        if ($response->status() === 401) {
            throw new ShiprocketAuthException('Shiprocket authentication rejected (401). Please check credentials.', 401, $response->json());
        }

        // 422 or 400 Validation Errors
        if (in_array($response->status(), [400, 422], true)) {
            $msg = $this->extractErrorMessage($response);
            $this->logRedacted('Shiprocket validation failure', ['endpoint' => $uri, 'status' => $response->status(), 'msg' => $msg]);
            throw new ShiprocketValidationException("Shiprocket validation error: {$msg}", $response->status(), $response->json());
        }

        if (! $response->successful()) {
            $msg = $this->extractErrorMessage($response);
            $this->logRedacted('Shiprocket API error response', ['endpoint' => $uri, 'status' => $response->status(), 'msg' => $msg]);
            throw new ShiprocketApiException("Shiprocket API error ({$response->status()}): {$msg}", $response->status(), $response->json());
        }

        return $response->json() ?? [];
    }

    /**
     * Check courier serviceability and rates.
     */
    public function checkServiceability(string $pickupPincode, string $deliveryPincode, float $weightKg, bool $cod = false): array
    {
        return $this->request('GET', '/courier/serviceability/', [
            'pickup_postcode' => $pickupPincode,
            'delivery_postcode' => $deliveryPincode,
            'weight' => max(0.1, round($weightKg, 2)),
            'cod' => $cod ? 1 : 0,
        ]);
    }

    /**
     * Create shipment / adhoc order on Shiprocket.
     */
    public function createOrder(array $payload): array
    {
        return $this->request('POST', '/orders/create/adhoc', $payload);
    }

    /**
     * Get order details from Shiprocket.
     */
    public function getOrderDetails(string|int $srOrderId): array
    {
        return $this->request('GET', "/orders/show/{$srOrderId}");
    }

    /**
     * Assign courier / generate AWB code.
     */
    public function assignCourier(int|string $shipmentId, ?int $courierId = null): array
    {
        $payload = ['shipment_id' => $shipmentId];
        if ($courierId) {
            $payload['courier_id'] = $courierId;
        }

        return $this->request('POST', '/courier/assign/awb', $payload);
    }

    /**
     * Schedule courier pickup.
     */
    public function schedulePickup(array $shipmentIds): array
    {
        return $this->request('POST', '/courier/generate/pickup', [
            'shipment_id' => array_values($shipmentIds),
        ]);
    }

    /**
     * Generate shipping label.
     */
    public function generateLabel(array $shipmentIds): array
    {
        return $this->request('POST', '/courier/generate/label', [
            'shipment_id' => array_values($shipmentIds),
        ]);
    }

    /**
     * Generate pickup manifest.
     */
    public function generateManifest(array $shipmentIds): array
    {
        return $this->request('POST', '/manifests/generate', [
            'shipment_id' => array_values($shipmentIds),
        ]);
    }

    /**
     * Print invoice.
     */
    public function generateInvoice(array $srOrderIds): array
    {
        return $this->request('POST', '/orders/print/invoice', [
            'ids' => array_values($srOrderIds),
        ]);
    }

    /**
     * Track shipment live by AWB number.
     */
    public function trackByAwb(string $awb): array
    {
        return $this->request('GET', "/courier/track/awb/{$awb}");
    }

    /**
     * Cancel orders on Shiprocket by Shiprocket order ID.
     */
    public function cancelOrder(array $srOrderIds): array
    {
        return $this->request('POST', '/orders/cancel', [
            'ids' => array_values($srOrderIds),
        ]);
    }

    /**
     * Cancel shipment by AWB.
     */
    public function cancelShipmentByAwb(array $awbs): array
    {
        return $this->request('POST', '/orders/cancel/shipment/awbs', [
            'awbs' => array_values($awbs),
        ]);
    }

    /**
     * Get list of Non-Delivery Reports (NDR).
     */
    public function getNdrList(): array
    {
        return $this->request('GET', '/ndr/all');
    }

    /**
     * Re-attempt delivery on an NDR shipment.
     */
    public function reattemptNdr(string $awb, array $details): array
    {
        return $this->request('POST', '/ndr/reattempt', array_merge(['awb' => $awb], $details));
    }

    /**
     * Create reverse pickup / return order.
     */
    public function createReturnOrder(array $payload): array
    {
        return $this->request('POST', '/orders/create/return', $payload);
    }

    /**
     * Get current wallet balance.
     */
    public function getWalletBalance(): array
    {
        return $this->request('GET', '/account/details/wallet-balance');
    }

    /**
     * Extract human-readable error message from response.
     */
    protected function extractErrorMessage(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            if (! empty($json['message'])) {
                if (is_string($json['message'])) {
                    return $json['message'];
                }
                if (is_array($json['message'])) {
                    return implode(', ', array_map(fn ($m) => is_array($m) ? implode(', ', $m) : (string) $m, $json['message']));
                }
            }
            if (! empty($json['error'])) {
                return is_string($json['error']) ? $json['error'] : json_encode($json['error']);
            }
            if (! empty($json['errors'])) {
                return json_encode($json['errors']);
            }
        }

        return $response->body() ?: 'Unknown response from logistics provider.';
    }

    /**
     * Redact customer PII before writing to logs.
     */
    protected function logRedacted(string $message, array $context): void
    {
        array_walk_recursive($context, function (&$value, $key) {
            if (is_string($value)) {
                if (stripos($key, 'email') !== false && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $parts = explode('@', $value);
                    $value = substr($parts[0], 0, 2).'***@'.$parts[1];
                } elseif (stripos($key, 'phone') !== false || stripos($key, 'mobile') !== false) {
                    $value = strlen($value) > 4 ? '******'.substr($value, -4) : '***';
                } elseif (stripos($key, 'token') !== false || stripos($key, 'password') !== false) {
                    $value = '***REDACTED***';
                }
            }
        });

        Log::info($message, $context);
    }
}
