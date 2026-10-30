<?php

namespace App\Actions\Shiprocket;

use App\Models\Pincode;
use App\Models\SiteSetting;
use App\Services\Shiprocket\ShiprocketClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckServiceabilityAction
{
    public function __construct(
        protected ?ShiprocketClient $client = null
    ) {
        $this->client = $client ?? app(ShiprocketClient::class);
    }

    /**
     * Check delivery serviceability, courier rates, and COD availability.
     * Caches result for 6 hours; falls back to local pincodes table if SR is unavailable.
     */
    public function execute(string $deliveryPincode, float $weightKg = 0.5, bool $cod = false, ?string $pickupPincode = null): array
    {
        $deliveryPincode = trim($deliveryPincode);
        $pickupPincode = trim($pickupPincode ?: (SiteSetting::get('pickup_pincode', '132103') ?? '132103'));
        $weightKg = max(0.1, round($weightKg, 2));
        $weightBucket = round($weightKg, 1);
        $codFlag = $cod ? 1 : 0;

        $cacheKey = "shiprocket:serviceability:{$pickupPincode}:{$deliveryPincode}:{$weightBucket}:{$codFlag}";

        return Cache::remember($cacheKey, 21600, function () use ($pickupPincode, $deliveryPincode, $weightKg, $cod) {
            $isSrEnabled = SiteSetting::get('shiprocket_enabled', true);

            if ($isSrEnabled && config('services.shiprocket.email') && config('services.shiprocket.password')) {
                try {
                    $response = $this->client->checkServiceability($pickupPincode, $deliveryPincode, $weightKg, $cod);

                    $couriers = $response['data']['available_courier_companies'] ?? [];

                    if (! empty($couriers)) {
                        // Sort by rate (cheapest) and etd
                        $cheapest = collect($couriers)->sortBy('rate')->first();
                        $fastest = collect($couriers)->sortBy(function ($c) {
                            $etdStr = (string) ($c['etd'] ?? '5');
                            preg_match('/\d+/', $etdStr, $m);
                            return isset($m[0]) ? (int) $m[0] : 99;
                        })->first();

                        $minEtd = 3;
                        if (! empty($fastest['etd'])) {
                            preg_match('/\d+/', (string) $fastest['etd'], $m);
                            if (isset($m[0])) {
                                $minEtd = (int) $m[0];
                            }
                        }

                        $codAvailable = collect($couriers)->contains(fn ($c) => ! empty($c['cod']) && $c['cod'] == 1);

                        return [
                            'serviceable' => true,
                            'pincode' => $deliveryPincode,
                            'source' => 'shiprocket',
                            'couriers' => $couriers,
                            'cheapest_courier' => $cheapest,
                            'fastest_courier' => $fastest,
                            'estimated_days' => $minEtd,
                            'cod_available' => $cod ? $codAvailable : true,
                            'message' => "Delivery available in approx {$minEtd} days.",
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Shiprocket serviceability check failed, using fallback', [
                        'delivery_pincode' => $deliveryPincode,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Fallback to local pincodes table
            return $this->fallbackLocalPincode($deliveryPincode, $cod);
        });
    }

    protected function fallbackLocalPincode(string $pincode, bool $cod): array
    {
        $record = Pincode::where('pincode', $pincode)->first();

        if ($record) {
            return [
                'serviceable' => (bool) $record->serviceable,
                'pincode' => $pincode,
                'source' => 'local_db',
                'couriers' => [],
                'cheapest_courier' => null,
                'fastest_courier' => null,
                'estimated_days' => (int) ($record->eta_days ?: 4),
                'cod_available' => (bool) $record->cod_available,
                'message' => $record->serviceable
                    ? "Delivery available to {$record->city}, {$record->state}."
                    : 'Delivery is currently not available for this pincode.',
            ];
        }

        // Generic fallback
        return [
            'serviceable' => true,
            'pincode' => $pincode,
            'source' => 'default',
            'couriers' => [],
            'cheapest_courier' => null,
            'fastest_courier' => null,
            'estimated_days' => 4,
            'cod_available' => true,
            'message' => 'Delivery available.',
        ];
    }
}
